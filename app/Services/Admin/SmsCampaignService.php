<?php

namespace App\Services\Admin;

use App\Jobs\SendSmsJob;
use App\Models\Customer;
use App\Models\SmsCampaign;
use App\Support\SmsSegments;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SmsCampaignService
{
    /**
     * Appended to promotional messages. Required for marketing SMS, and the
     * thing that keeps a complaint from becoming a suspended sender ID.
     */
    public const OPT_OUT_LINE = ' Reply STOP to opt out.';

    public function __construct(private readonly SmsAudienceService $audience) {}

    /**
     * The full message as it will be sent, opt-out line included.
     *
     * Composed in one place so the cost preview and the actual send can never
     * disagree about what is being counted.
     */
    public function compose(string $message, string $kind): string
    {
        $message = trim($message);

        if ($kind !== 'promo') {
            return $message;
        }

        // Do not append twice if the author wrote their own.
        return str_contains(strtoupper($message), 'STOP')
            ? $message
            : $message.self::OPT_OUT_LINE;
    }

    /**
     * What this send will cost, before committing to it.
     *
     * @param  array<int, int>  $selectedIds
     * @return array<string, mixed>
     */
    public function preview(string $message, string $kind, string $audience, array $selectedIds = []): array
    {
        $body = $this->compose($message, $kind);
        $measure = SmsSegments::measure($body);

        $recipients = $this->audience->resolve($audience, $selectedIds)->count();
        // Only promos are gated on consent, so only they report a skip count.
        $skipped = $kind === 'promo'
            ? $this->audience->optedOutCount($audience, $selectedIds)
            : 0;

        return [
            'body' => $body,
            'encoding' => $measure['encoding'],
            'units' => $measure['units'],
            'segments' => $measure['segments'],
            'remaining' => $measure['remaining'],
            'offenders' => $measure['offenders'],
            'recipients' => $recipients,
            'skipped_opted_out' => $skipped,
            'total_segments' => $measure['segments'] * $recipients,
        ];
    }

    /**
     * Queue the send.
     *
     * The campaign row and its recipient list are written in one transaction
     * before a single job is dispatched: an SMS cannot be recalled, so the
     * record of who was texted must exist before anyone is.
     *
     * @param  array<int, int>  $selectedIds
     */
    public function dispatchCampaign(
        string $title,
        string $message,
        string $kind,
        string $audience,
        array $selectedIds,
        ?int $adminId,
    ): SmsCampaign {
        $body = $this->compose($message, $kind);
        $recipients = $this->audience->resolve($audience, $selectedIds);

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'audience' => 'This audience matched nobody who can be texted.',
            ]);
        }

        $segments = SmsSegments::segments($body);

        $campaign = DB::transaction(function () use (
            $title, $body, $kind, $audience, $selectedIds, $adminId, $recipients, $segments
        ): SmsCampaign {
            $campaign = SmsCampaign::create([
                'admin_id' => $adminId,
                'title' => $title,
                'message' => $body,
                'kind' => $kind,
                'audience' => $audience,
                'recipient_count' => $recipients->count(),
                'skipped_opted_out' => $kind === 'promo'
                    ? $this->audience->optedOutCount($audience, $selectedIds)
                    : 0,
                'segments_per_message' => $segments,
                'total_segments' => $segments * $recipients->count(),
                'status' => 'queued',
                'queued_at' => now(),
            ]);

            $this->recordRecipients($campaign, $recipients);

            return $campaign;
        });

        foreach ($recipients as $customer) {
            SendSmsJob::dispatch(
                $customer->phone,
                $body,
                "campaign:{$campaign->id}",
                'customer',
                $customer->id,
            // Bulk marketing must never delay an order confirmation or a
            // rider assignment, which run on `high`.
            )->onQueue('bulk');
        }

        Log::info("[SMS] Campaign #{$campaign->id} queued", [
            'title' => $title,
            'recipients' => $recipients->count(),
            'segments_each' => $segments,
            'total_segments' => $campaign->total_segments,
        ]);

        return $campaign;
    }

    /**
     * @param  Collection<int, Customer>  $recipients
     */
    private function recordRecipients(SmsCampaign $campaign, Collection $recipients): void
    {
        $now = now();

        $recipients
            ->map(fn (Customer $c) => [
                'sms_campaign_id' => $campaign->id,
                'customer_id' => $c->id,
                'phone' => $c->phone,
                'created_at' => $now,
            ])
            // Chunked: a few thousand customers is one insert too many for a
            // single statement on MySQL's default packet size.
            ->chunk(500)
            ->each(fn (Collection $rows) => DB::table('sms_campaign_recipients')->insert($rows->all()));
    }
}
