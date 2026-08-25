<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes every order and everything derived from one, leaving customers, riders,
 * the catalogue and settings untouched. Written for the transition from test
 * data to real trading.
 *
 * Deleting the orders alone is not enough. Several tables carry denormalised
 * totals that were incremented as orders moved through their lifecycle and are
 * never recalculated from scratch — rider delivery counts, GasPoints balances,
 * streaks, stock levels. Remove the orders without resetting those and the
 * panel reports deliveries by riders who have made none, and customers keep
 * points they earned on orders that no longer exist.
 */
class PurgeOrders extends Command
{
    use ConfirmableTrait;

    protected $signature = 'orders:purge
        {--dry-run       : Report what would be removed, change nothing}
        {--restore-stock : Add back the cylinders these orders deducted}
        {--keep-points   : Leave GasPoints transactions and balances alone}
        {--force         : Skip the production confirmation prompt}';

    protected $description = 'Delete all orders and reset every counter derived from them';

    public function handle(): int
    {
        $counts = $this->survey();

        $this->report($counts);

        if ($counts['orders'] === 0) {
            $this->components->info('No orders to purge.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->warn('Dry run — nothing was changed.');

            return self::SUCCESS;
        }

        // Same guard `migrate:fresh` uses: refuses to run in production unless
        // the operator confirms or passes --force.
        if (! $this->confirmToProceed('This permanently deletes every order')) {
            return self::FAILURE;
        }

        DB::transaction(function (): void {
            $this->purgePoints();
            $this->purgeStockAudit();
            $this->purgeOrders();
            $this->resetRiderStats();
            $this->resetGamification();
            $this->resetAutoIncrement();
        });

        // Outside the transaction: this reads the audit rows the purge removed,
        // so it works off the survey taken before anything was deleted.
        if ($this->option('restore-stock')) {
            $this->restoreStock($counts['stock_deltas']);
        }

        $this->newLine();
        $this->components->info('Orders purged. The next order will be EG-'.now()->format('Ymd').'-00001.');

        if (! $this->option('restore-stock') && $counts['stock_deltas']->isNotEmpty()) {
            $this->components->warn(
                'Stock levels were NOT changed. Set the real counts in Admin → Stock after a physical count.'
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return array{orders:int, addons:int, history:int, ratings:int, declines:int, points:int, stock_audit:int, stock_deltas:\Illuminate\Support\Collection}
     */
    private function survey(): array
    {
        return [
            'orders' => DB::table('orders')->count(),
            'addons' => DB::table('order_addons')->count(),
            'history' => DB::table('order_status_history')->count(),
            'ratings' => DB::table('order_ratings')->count(),
            'declines' => Schema::hasTable('order_rider_declines')
                ? DB::table('order_rider_declines')->count()
                : 0,
            'points' => DB::table('gaspoints_transactions')->whereNotNull('order_id')->count(),
            'stock_audit' => DB::table('stock_audit_logs')->whereNotNull('order_id')->count(),
            // Net units these orders took out of stock, per size. Negative means
            // stock went down. Derived from the audit trail rather than the order
            // count so cancellations that already returned a cylinder net out.
            'stock_deltas' => DB::table('stock_audit_logs')
                ->whereNotNull('order_id')
                ->groupBy('size_id')
                ->pluck(DB::raw('SUM(change_amount)'), 'size_id'),
        ];
    }

    /**
     * @param array<string, mixed> $counts
     */
    private function report(array $counts): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>Table</>', '<fg=yellow>Rows affected</>');
        $this->components->twoColumnDetail('orders', (string) $counts['orders']);
        $this->components->twoColumnDetail('  order_addons (cascade)', (string) $counts['addons']);
        $this->components->twoColumnDetail('  order_status_history (cascade)', (string) $counts['history']);
        $this->components->twoColumnDetail('  order_ratings (cascade)', (string) $counts['ratings']);
        $this->components->twoColumnDetail('  order_rider_declines (cascade)', (string) $counts['declines']);
        $this->components->twoColumnDetail(
            'gaspoints_transactions',
            $this->option('keep-points') ? 'kept (--keep-points)' : (string) $counts['points'],
        );
        $this->components->twoColumnDetail('stock_audit_logs (order-linked)', (string) $counts['stock_audit']);

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green>Preserved</>', '<fg=green>customers, riders, admins, addresses,</>');
        $this->components->twoColumnDetail('', '<fg=green>catalogue, pricing, stock levels, settings</>');

        if ($counts['stock_deltas']->isNotEmpty()) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=yellow>Stock taken by these orders</>', '');
            foreach ($counts['stock_deltas'] as $sizeId => $delta) {
                $name = DB::table('cylinder_sizes')->where('id', $sizeId)->value('name') ?? "size #{$sizeId}";
                $this->components->twoColumnDetail("  {$name}", sprintf('%+d filled', (int) $delta));
            }
        }
    }

    private function purgePoints(): void
    {
        if ($this->option('keep-points')) {
            // The FK is nullOnDelete, so these rows survive the order purge with
            // order_id set to NULL. Balances stay as they are.
            return;
        }

        DB::table('gaspoints_transactions')->whereNotNull('order_id')->delete();

        // balance_after on each row is a running total, so the surviving rows no
        // longer chain correctly. Recompute each balance from what is left
        // rather than trying to patch the chain.
        $totals = DB::table('gaspoints_transactions')
            ->groupBy('customer_id')
            ->pluck(DB::raw('SUM(points)'), 'customer_id');

        DB::table('customers')->update(['gaspoints_balance' => 0]);

        foreach ($totals as $customerId => $sum) {
            DB::table('customers')
                ->where('id', $customerId)
                ->update(['gaspoints_balance' => max(0, (int) $sum)]);
        }

        // Rewrite the running totals so the customer's history reads correctly.
        foreach ($totals->keys() as $customerId) {
            $running = 0;
            $rows = DB::table('gaspoints_transactions')
                ->where('customer_id', $customerId)
                ->orderBy('id')
                ->pluck('points', 'id');

            foreach ($rows as $id => $points) {
                $running = max(0, $running + (int) $points);
                DB::table('gaspoints_transactions')->where('id', $id)->update(['balance_after' => $running]);
            }
        }
    }

    private function purgeStockAudit(): void
    {
        // Only the order-driven entries. Rows with a null order_id are manual
        // admin adjustments — a real record of real stock decisions.
        DB::table('stock_audit_logs')->whereNotNull('order_id')->delete();
    }

    private function purgeOrders(): void
    {
        // DELETE, not TRUNCATE: truncate does not fire ON DELETE CASCADE and is
        // refused outright while foreign keys point at the table.
        DB::table('orders')->delete();
    }

    private function resetRiderStats(): void
    {
        // total_deliveries and avg_rating are recomputed from orders by
        // RiderStatsService, but incident_count is only ever incremented — all
        // three have to be zeroed by hand.
        DB::table('riders')->update([
            'total_deliveries' => 0,
            'avg_rating' => 0,
            'incident_count' => 0,
        ]);
    }

    private function resetGamification(): void
    {
        // Streaks and badges are earned from delivered orders. Left alone they
        // would advertise milestones nobody reached.
        if (Schema::hasTable('customer_streaks')) {
            DB::table('customer_streaks')->delete();
        }

        if (Schema::hasTable('customer_badges')) {
            DB::table('customer_badges')->delete();
        }
    }

    private function resetAutoIncrement(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Order numbers embed the row id (EG-20260825-00042), so without this
        // the first real order carries on from the test data's last id.
        foreach (['orders', 'order_addons', 'order_status_history', 'order_ratings'] as $table) {
            DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
        }
    }

    private function restoreStock(\Illuminate\Support\Collection $deltas): void
    {
        foreach ($deltas as $sizeId => $delta) {
            $delta = (int) $delta;

            if ($delta === 0) {
                continue;
            }

            $current = (int) DB::table('stock_levels')->where('size_id', $sizeId)->value('filled_count');

            DB::table('stock_levels')
                ->where('size_id', $sizeId)
                ->update(['filled_count' => max(0, $current - $delta)]);
        }

        $this->components->info('Stock levels restored to their pre-order values.');
    }
}
