<?php

namespace App\Console\Commands;

use App\Events\TestOrderAlertEvent;
use Illuminate\Console\Command;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Support\Facades\DB;

/**
 * Fires a fake new-order alert at the admin panel.
 *
 * The alert chain has four links, and when it fails the browser looks identical
 * for all of them: queue worker -> Reverb daemon -> Nginx proxy -> browser
 * socket. This command reports the resolved config and then lets you cut the
 * chain in half with --now, which publishes straight from this process and
 * skips the queue entirely.
 *
 *   alert appears with --now but not without  -> queue worker is the problem
 *   alert appears with neither                -> Reverb or Nginx is the problem
 *   alert appears with both                   -> broadcasting is fine; the
 *                                                problem is upstream in the
 *                                                order-placing code path
 */
class TestOrderAlert extends Command
{
    protected $signature = 'orders:test-alert
        {--now : Publish directly, bypassing the queue worker}';

    protected $description = 'Send a fake new-order alert to the admin panel to test sound and banner';

    public function handle(BroadcastFactory $broadcast): int
    {
        $this->reportConfig();

        $payload = $this->payload();

        if ($this->option('now')) {
            // Straight to Reverb's HTTP API from this process. If this works and
            // the queued path does not, the worker is the broken link.
            $broadcast->connection()->broadcast(
                ['private-admin.orders'],
                'order.placed',
                $payload,
            );

            $this->components->info('Published directly (queue bypassed).');
        } else {
            TestOrderAlertEvent::dispatch($payload);

            $this->components->info('Queued. A worker on the "default" queue must pick it up.');
        }

        $this->newLine();
        $this->line("  Sent as order <fg=yellow>{$payload['order_number']}</>");
        $this->newLine();
        $this->line('  On an open admin page you should now see a banner and hear a chime.');
        $this->line('  No sound but a banner? Click the bell in the top bar once — browsers');
        $this->line('  block audio until the page has been interacted with.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function reportConfig(): void
    {
        $app = config('broadcasting.connections.reverb');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>Setting</>', '<fg=yellow>Value</>');
        $this->components->twoColumnDetail('BROADCAST_CONNECTION', (string) config('broadcasting.default'));
        $this->components->twoColumnDetail('QUEUE_CONNECTION', (string) config('queue.default'));
        $this->components->twoColumnDetail('Reverb public host', sprintf(
            '%s://%s:%s',
            config('reverb.apps.apps.0.options.scheme'),
            config('reverb.apps.apps.0.options.host'),
            config('reverb.apps.apps.0.options.port'),
        ));
        $this->components->twoColumnDetail('Reverb bind socket', sprintf(
            '%s:%s',
            config('reverb.servers.reverb.host'),
            config('reverb.servers.reverb.port'),
        ));
        $this->components->twoColumnDetail(
            'Allowed origins',
            implode(', ', (array) config('reverb.apps.apps.0.allowed_origins')),
        );

        $pending = $this->pendingJobs();
        if ($pending !== null) {
            $this->components->twoColumnDetail('Jobs waiting in queue', (string) $pending);
        }

        $failed = $this->failedJobs();
        if ($failed !== null) {
            $this->components->twoColumnDetail(
                'Failed jobs',
                $failed > 0 ? "<fg=red>{$failed}</> (run queue:failed)" : '0',
            );
        }

        $this->newLine();

        $host = (string) config('reverb.apps.apps.0.options.host');
        if (in_array($host, ['0.0.0.0', '127.0.0.1', 'localhost', ''], true)) {
            $this->components->error(
                "REVERB_HOST is \"{$host}\". That is a bind address, not a destination — ".
                'every broadcast will fail to publish. Set it to the public domain.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        // A high random id: the panel shows each order id once, so a fixed id
        // would be silently swallowed as a duplicate on the second run.
        $id = random_int(900_000_000, 999_999_999);

        return [
            'id' => $id,
            'order_number' => 'TEST-'.now()->format('Hi').'-'.substr((string) $id, -4),
            'status' => 'pending',
            'order_type' => 'swap',
            'total_amount' => 2450,
            'payment_method' => 'cash',
            'size_name' => '13kg',
            'brand_name' => 'Test Brand',
            'customer_name' => 'Alert Test',
            'customer_phone' => '+254700000000',
            'address' => 'Test delivery address, Eldoret',
            'created_ago' => 'just now',
            'is_reoffer' => false,
        ];
    }

    private function pendingJobs(): ?int
    {
        if (config('queue.default') !== 'database') {
            return null;
        }

        try {
            return DB::table('jobs')->count();
        } catch (\Throwable) {
            return null;
        }
    }

    private function failedJobs(): ?int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return null;
        }
    }
}
