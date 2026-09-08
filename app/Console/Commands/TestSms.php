<?php

namespace App\Console\Commands;

use App\Jobs\SendSmsJob;
use App\Services\Sms\SmsServiceInterface;
use App\Support\SmsSegments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * End-to-end SMS check.
 *
 * There are four places a message can die and they look identical from the
 * outside: the queue worker is not covering the queue, the gateway refuses the
 * call, the gateway accepts and then rejects downstream, or the sender ID is
 * not approved. This walks the chain and says which one it is.
 */
class TestSms extends Command
{
    protected $signature = 'sms:test
        {phone? : Number to text. Defaults to the first configured manager phone}
        {--queue : Send through the bulk queue instead of directly, to prove the worker picks it up}';

    protected $description = 'Send a test SMS and report exactly where it succeeded or failed';

    public function handle(SmsServiceInterface $sms): int
    {
        $phone = $this->argument('phone') ?: $this->firstManagerPhone();

        if (! $phone) {
            $this->components->error('No phone given and SHOP_MANAGER_PHONES is empty. Pass one: sms:test +2547XXXXXXXX');

            return self::FAILURE;
        }

        $this->reportConfig();
        $this->reportQueue();

        $message = 'EldoGas test '.now()->format('H:i:s').'. If you received this, delivery works.';

        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>Sending to</>', $phone);
        $this->components->twoColumnDetail(
            'Segments',
            (string) SmsSegments::segments($message),
        );

        return $this->option('queue')
            ? $this->sendViaQueue($phone, $message)
            : $this->sendDirect($sms, $phone, $message);
    }

    /**
     * Straight to the gateway from this process. Isolates "the API refuses us"
     * from "the worker never ran the job".
     */
    private function sendDirect(SmsServiceInterface $sms, string $phone, string $message): int
    {
        $this->newLine();
        $this->line('  Sending directly (queue bypassed)...');

        $ok = $sms->send($phone, $message);

        $this->newLine();

        if ($ok) {
            $this->components->info('The gateway ACCEPTED the message.');
            $this->line('  That is not the same as delivered. If it does not arrive:');
            $this->line('   - check the TalkSasa dashboard for this message');
            $this->line('   - a "source_address filter mismatch" there means the sender ID');
            $this->line('     is not approved on the account, which the API does not tell us');

            return self::SUCCESS;
        }

        $reason = method_exists($sms, 'lastError') ? $sms->lastError() : null;

        $this->components->error('The gateway REFUSED the message.');

        if ($reason) {
            $this->line("  Reason: <fg=red>{$reason}</>");
        }

        $this->line('  Check TALKSASA_API_TOKEN and TALKSASA_SENDER_ID, then storage/logs.');

        return self::FAILURE;
    }

    /**
     * Through the bulk queue, which is where campaigns go. Proves the worker is
     * actually covering that queue — the failure that leaves a campaign sitting
     * at "queued" forever with no error anywhere.
     */
    private function sendViaQueue(string $phone, string $message): int
    {
        $before = $this->pending('bulk');

        SendSmsJob::dispatch($phone, $message, 'sms_test', 'admin', 0)->onQueue('bulk');

        $this->newLine();
        $this->components->info('Queued on the "bulk" queue.');
        $this->line("  Jobs waiting there before: {$before}, now: ".$this->pending('bulk'));
        $this->newLine();
        $this->line('  Watch it drain:');
        $this->line('    watch -n1 \'php8.4 artisan tinker --execute="echo DB::table(\\\'jobs\\\')->where(\\\'queue\\\',\\\'bulk\\\')->count();"\'');
        $this->newLine();
        $this->line('  If the count does NOT fall, the worker is not covering `bulk`.');
        $this->line('  Its command needs: <fg=yellow>--queue=high,default,bulk</>');

        return self::SUCCESS;
    }

    private function reportConfig(): void
    {
        $token = (string) config('services.talksasa.api_token');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>Setting</>', '<fg=yellow>Value</>');
        $this->components->twoColumnDetail('Sender ID', (string) config('services.talksasa.sender_id'));
        $this->components->twoColumnDetail(
            'API token',
            $token === '' ? '<fg=red>MISSING — messages are only logged, never sent</>' : 'set ('.strlen($token).' chars)',
        );
        $this->components->twoColumnDetail('API URL', (string) config('services.talksasa.api_url'));
        $this->components->twoColumnDetail('QUEUE_CONNECTION', (string) config('queue.default'));
    }

    private function reportQueue(): void
    {
        if (config('queue.default') !== 'database') {
            return;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>Queue</>', '<fg=yellow>Waiting</>');

        try {
            $rows = DB::table('jobs')->selectRaw('queue, COUNT(*) as total')->groupBy('queue')->get();

            if ($rows->isEmpty()) {
                $this->components->twoColumnDetail('(all queues)', '0');
            }

            foreach ($rows as $row) {
                $this->components->twoColumnDetail(
                    $row->queue,
                    // A pile on `bulk` is the signature of a worker that is not
                    // covering it.
                    $row->queue === 'bulk' && $row->total > 0
                        ? "<fg=red>{$row->total}</> (is the worker covering `bulk`?)"
                        : (string) $row->total,
                );
            }

            $failed = DB::table('failed_jobs')->count();
            $this->components->twoColumnDetail(
                'failed_jobs',
                $failed > 0 ? "<fg=red>{$failed}</> (run queue:failed)" : '0',
            );
        } catch (\Throwable $e) {
            $this->components->warn('Could not read the queue tables: '.$e->getMessage());
        }
    }

    private function pending(string $queue): int
    {
        try {
            return DB::table('jobs')->where('queue', $queue)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function firstManagerPhone(): ?string
    {
        return \App\Support\ManagerContacts::phones()[0] ?? null;
    }
}
