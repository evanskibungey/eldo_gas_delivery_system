<?php

namespace App\Services\Admin;

use App\Events\LowStockAlertEvent;
use App\Events\CriticalStockAlertEvent;
use App\Events\StockDepletedEvent;
use App\Events\StockRestoredEvent;
use App\Models\CylinderSize;
use App\Models\Order;
use App\Models\StockAuditLog;
use App\Models\StockLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StockService
{
    public function allStock(): Collection
    {
        return StockLevel::with('size')
            ->join('cylinder_sizes', 'cylinder_sizes.id', '=', 'stock_levels.size_id')
            ->orderBy('cylinder_sizes.sort_order')
            ->select('stock_levels.*')
            ->get();
    }

    public function forSize(CylinderSize $size): StockLevel
    {
        return $size->stockLevel ?? new StockLevel([
            'size_id'             => $size->id,
            'filled_count'        => 0,
            'empty_count'         => 0,
            'low_stock_threshold' => 5,
        ]);
    }

    public function manualAdjust(CylinderSize $size, array $data, int $adminId): StockLevel
    {
        $stock = StockLevel::firstOrCreate(
            ['size_id' => $size->id],
            ['filled_count' => 0, 'empty_count' => 0, 'low_stock_threshold' => 5],
        );

        $wasEmpty   = $stock->isEmpty();
        $oldFilled  = $stock->filled_count;
        $newFilled  = (int) $data['filled_count'];

        $stock->update([
            'filled_count'        => $newFilled,
            'empty_count'         => (int) $data['empty_count'],
            'low_stock_threshold' => isset($data['threshold']) ? (int) $data['threshold'] : $stock->low_stock_threshold,
            'updated_by'          => $adminId,
        ]);

        StockAuditLog::create([
            'size_id'       => $size->id,
            'change_type'   => 'manual_adjustment',
            'change_amount' => $newFilled - $oldFilled,
            'new_count'     => $newFilled,
            'admin_id'      => $adminId,
            'note'          => $data['note'] ?? null,
            'created_at'    => now(),
        ]);

        $stock->refresh();

        if ($stock->isEmpty()) {
            event(new StockDepletedEvent($stock));
        } elseif ($wasEmpty && ! $stock->isEmpty()) {
            event(new StockRestoredEvent($stock));
        }

        return $stock;
    }

    public function auditLog(CylinderSize $size, array $filters): LengthAwarePaginator
    {
        return StockAuditLog::with('admin')
            ->where('size_id', $size->id)
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to']   ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filters['change_type'] ?? null, fn ($q, $v) => $q->where('change_type', $v))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();
    }

    public function deductForOrder(Order $order): void
    {
        $stock = StockLevel::where('size_id', $order->size_id)->lockForUpdate()->first();

        if (! $stock || $stock->filled_count <= 0) {
            return;
        }

        $oldFilled = $stock->filled_count;
        $newFilled = $oldFilled - 1;

        $stock->update(['filled_count' => $newFilled]);

        StockAuditLog::create([
            'size_id'       => $order->size_id,
            'change_type'   => 'auto_deduction',
            'change_amount' => -1,
            'new_count'     => $newFilled,
            'order_id'      => $order->id,
            'created_at'    => now(),
        ]);

        $stock->refresh();

        if ($stock->isEmpty()) {
            event(new StockDepletedEvent($stock));
        } elseif ($stock->isCritical()) {
            event(new CriticalStockAlertEvent($stock));
        } elseif ($stock->isLow()) {
            event(new LowStockAlertEvent($stock));
        }
    }

    public function restoreForOrder(Order $order): void
    {
        $stock = StockLevel::where('size_id', $order->size_id)->lockForUpdate()->first();

        if (! $stock) {
            return;
        }

        $wasEmpty  = $stock->isEmpty();
        $newFilled = $stock->filled_count + 1;

        $stock->update(['filled_count' => $newFilled]);

        StockAuditLog::create([
            'size_id'       => $order->size_id,
            'change_type'   => 'auto_return',
            'change_amount' => 1,
            'new_count'     => $newFilled,
            'order_id'      => $order->id,
            'created_at'    => now(),
        ]);

        if ($wasEmpty) {
            $stock->refresh();
            event(new StockRestoredEvent($stock));
        }
    }

    public function lowStockCount(): int
    {
        return StockLevel::whereRaw('filled_count <= low_stock_threshold')->count();
    }
}
