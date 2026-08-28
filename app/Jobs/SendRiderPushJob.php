<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\NotificationLog;
use App\Services\Push\FcmException;
use App\Services\Push\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push delivery for riders. Assignments are time-boxed by
 * rider_acceptance_deadline, so this runs on the `high` queue alongside the
 * assignment SMS rather than behind ordinary work.
 */
class SendRiderPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * Must match the channel the rider app creates in
     * NotificationService._setupLocalNotifications(). Android drops a
     * notification addressed to a channel that does not exist.
     */
    private const ANDROID_CHANNEL_ID = 'eldogas_orders';

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function __construct(
        public readonly int $riderId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly string $trigger = 'rider.notification',
    ) {
        // Assignments are time-boxed by rider_acceptance_deadline, so this
        // must not queue behind ordinary work. Set here rather than as a
        // typed property — the Queueable trait already declares $queue.
        $this->onQueue('high');
    }

    public function handle(FcmService $fcm): void
    {
        $devices = Device::where('rider_id', $this->riderId)
            ->orderByDesc('last_seen_at')
            ->get();

        $this->logToInbox();

        foreach ($devices as $device) {
            try {
                // The rider app creates this channel at startup with
                // Importance.max, so an assignment gets a heads-up banner and
                // a sound. Without an explicit channel the message lands on
                // Android's low-importance default and a rider looking at
                // their phone can still miss it inside the 60-second window.
                $fcm->send(
                    $device->token,
                    $this->title,
                    $this->body,
                    $this->data,
                    androidChannelId: self::ANDROID_CHANNEL_ID,
                );
            } catch (FcmException $exception) {
                if ($exception->isUnregistered()) {
                    // Dead token — the app was uninstalled or the token
                    // rotated. Prune it so we stop paying for the attempt.
                    $device->delete();

                    continue;
                }

                Log::warning('[push] rider FCM send failed', [
                    'rider_id'  => $this->riderId,
                    'device_id' => $device->id,
                    'error'     => $exception->getMessage(),
                ]);
            }
        }
    }

    private function logToInbox(): void
    {
        NotificationLog::create([
            'recipient_type' => 'rider',
            'recipient_id'   => $this->riderId,
            'channel'        => 'push',
            'trigger'        => $this->trigger,
            'title'          => $this->title,
            'message'        => $this->body,
            'data'           => $this->data,
            'sent_at'        => now(),
            'created_at'     => now(),
        ]);
    }
}
