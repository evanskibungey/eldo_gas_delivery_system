<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Stock\AdjustStockRequest;
use App\Models\CylinderSize;
use App\Services\Admin\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Stock/Index', [
            'stocks' => $this->stock->allStock()->map(fn ($s) => [
                'id'                  => $s->id,
                'size_id'             => $s->size_id,
                'size_name'           => $s->size->name,
                'filled_count'        => $s->filled_count,
                'empty_count'         => $s->empty_count,
                'low_stock_threshold' => $s->low_stock_threshold,
                'status'              => $s->status,
                'updated_at'          => $s->updated_at?->toDateTimeString(),
            ]),
        ]);
    }

    public function adjust(CylinderSize $size): Response
    {
        $stock = $this->stock->forSize($size);

        return Inertia::render('Admin/Stock/Adjust', [
            'size'  => $size->only('id', 'name'),
            'stock' => [
                'filled_count'        => $stock->filled_count,
                'empty_count'         => $stock->empty_count,
                'low_stock_threshold' => $stock->low_stock_threshold,
            ],
        ]);
    }

    public function update(AdjustStockRequest $request, CylinderSize $size): RedirectResponse
    {
        $this->stock->manualAdjust($size, $request->validated(), auth('admin')->id());

        return redirect()->route('admin.stock.index')
            ->with('success', "Stock for {$size->name} updated successfully.");
    }

    public function auditLog(Request $request, CylinderSize $size): Response
    {
        $filters = $request->only(['date_from', 'date_to', 'change_type']);
        $logs    = $this->stock->auditLog($size, $filters);

        return Inertia::render('Admin/Stock/AuditLog', [
            'size'    => $size->only('id', 'name'),
            'logs'    => $logs->through(fn ($log) => [
                'id'            => $log->id,
                'change_type'   => $log->change_type,
                'change_amount' => $log->change_amount,
                'new_count'     => $log->new_count,
                'note'          => $log->note,
                'admin_name'    => $log->admin?->name ?? 'System',
                'order_id'      => $log->order_id,
                'created_at'    => $log->created_at?->toDateTimeString(),
            ]),
            'filters' => $filters,
        ]);
    }
}
