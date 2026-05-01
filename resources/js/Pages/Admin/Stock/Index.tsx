import AdminLayout from '@/Layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { Pencil, FileText, Package, AlertTriangle } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

interface StockRow {
    id:                  number;
    size_id:             number;
    size_name:           string;
    filled_count:        number;
    empty_count:         number;
    low_stock_threshold: number;
    status:              'ok' | 'low' | 'critical' | 'out';
    updated_at:          string | null;
}

const STATUS_CONFIG = {
    ok:       { label: 'OK',       dot: 'bg-emerald-500', chip: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    low:      { label: 'Low',      dot: 'bg-amber-500',   chip: 'border-amber-200 bg-amber-50 text-amber-700' },
    critical: { label: 'Critical', dot: 'bg-orange-500',  chip: 'border-orange-200 bg-orange-50 text-orange-700' },
    out:      { label: 'Out',      dot: 'bg-red-500',     chip: 'border-red-200 bg-red-50 text-red-700' },
};

function StatusBadge({ status }: { status: StockRow['status'] }) {
    const cfg = STATUS_CONFIG[status];
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold', cfg.chip)}>
            <span className={cn('h-1.5 w-1.5 rounded-full', cfg.dot)} />
            {cfg.label}
        </span>
    );
}

function CountBadge({ value, threshold, isLow }: { value: number; threshold: number; isLow: boolean }) {
    return (
        <div className="flex items-baseline gap-1.5">
            <span className={cn('text-base font-bold tabular-nums', isLow ? 'text-red-600' : 'text-slate-800')}>
                {value}
            </span>
            <span className="text-[10px] text-slate-400">/ {threshold} threshold</span>
        </div>
    );
}

export default function StockIndex({ stocks }: { stocks: StockRow[] }) {
    const alertCount = stocks.filter(s => s.status !== 'ok').length;

    return (
        <AdminLayout title="Stock Management" subtitle="Monitor and adjust cylinder inventory levels">

            {alertCount > 0 && (
                <div className="mb-5 flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                    <p className="text-sm text-amber-700">
                        <span className="font-semibold">{alertCount} size{alertCount !== 1 ? 's' : ''}</span> {alertCount === 1 ? 'is' : 'are'} below threshold or out of stock.
                    </p>
                </div>
            )}

            <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/80">
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Size</th>
                            <th className="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Filled</th>
                            <th className="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Empty</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Last Updated</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {stocks.length === 0 && (
                            <tr>
                                <td colSpan={6} className="py-16 text-center">
                                    <div className="flex flex-col items-center gap-2">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                            <Package className="h-6 w-6 text-slate-300" />
                                        </div>
                                        <p className="text-sm font-medium text-slate-400">No stock records yet</p>
                                        <p className="text-xs text-slate-300">Add cylinder sizes to begin tracking stock.</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {stocks.map(s => (
                            <tr key={s.id} className={cn('transition-colors group', s.status === 'out' ? 'bg-red-50/40 hover:bg-red-50/60' : 'hover:bg-slate-50/50')}>
                                <td className="px-5 py-4">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 text-white font-bold text-xs shadow-sm shadow-orange-500/20">
                                            {s.size_name}
                                        </div>
                                        <span className="font-semibold text-slate-900">{s.size_name}</span>
                                    </div>
                                </td>
                                <td className="px-5 py-4 text-center">
                                    <CountBadge value={s.filled_count} threshold={s.low_stock_threshold} isLow={s.status !== 'ok'} />
                                </td>
                                <td className="px-5 py-4 text-center">
                                    <span className="text-base font-semibold tabular-nums text-slate-600">{s.empty_count}</span>
                                </td>
                                <td className="px-5 py-4">
                                    <StatusBadge status={s.status} />
                                </td>
                                <td className="px-5 py-4 text-xs text-slate-400">
                                    {s.updated_at ?? <span className="italic">Never</span>}
                                </td>
                                <td className="px-5 py-4 text-right">
                                    <div className="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Button asChild variant="outline" size="sm" className="h-8 gap-1.5 text-xs">
                                            <Link href={`/admin/stock/${s.size_id}/adjust`}>
                                                <Pencil className="h-3 w-3" /> Adjust
                                            </Link>
                                        </Button>
                                        <Button asChild variant="ghost" size="sm" className="h-8 gap-1.5 text-xs text-slate-400 hover:text-slate-700">
                                            <Link href={`/admin/stock/${s.size_id}/audit`}>
                                                <FileText className="h-3 w-3" /> Log
                                            </Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
