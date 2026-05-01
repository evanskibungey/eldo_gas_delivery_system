import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, FileText } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface LogEntry {
    id:            number;
    change_type:   string;
    change_amount: number;
    new_count:     number;
    note:          string | null;
    admin_name:    string;
    order_id:      number | null;
    created_at:    string | null;
}

interface PaginatedLogs {
    data:          LogEntry[];
    current_page:  number;
    last_page:     number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Props {
    size:    { id: number; name: string };
    logs:    PaginatedLogs;
    filters: { date_from?: string; date_to?: string; change_type?: string };
}

const CHANGE_TYPE_CONFIG: Record<string, { label: string; cls: string }> = {
    manual_adjustment:   { label: 'Manual Adjustment', cls: 'border-blue-200 bg-blue-50 text-blue-700' },
    auto_deduction:      { label: 'Auto Deduction',    cls: 'border-slate-200 bg-slate-100 text-slate-600' },
    auto_return:         { label: 'Auto Return',       cls: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    out_of_stock_cancel: { label: 'OOS Cancel',        cls: 'border-red-200 bg-red-50 text-red-700' },
};

function ChangeTypeBadge({ type }: { type: string }) {
    const cfg = CHANGE_TYPE_CONFIG[type] ?? { label: type, cls: 'border-slate-200 bg-slate-100 text-slate-600' };
    return (
        <span className={cn('inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-semibold', cfg.cls)}>
            {cfg.label}
        </span>
    );
}

export default function StockAuditLog({ size, logs, filters }: Props) {
    const [dateFrom, setDateFrom]   = useState(filters.date_from ?? '');
    const [dateTo, setDateTo]       = useState(filters.date_to ?? '');
    const [changeType, setChangeType] = useState(filters.change_type ?? '');

    function applyFilters(e: React.FormEvent) {
        e.preventDefault();
        router.get(`/admin/stock/${size.id}/audit`, {
            date_from:   dateFrom  || undefined,
            date_to:     dateTo    || undefined,
            change_type: changeType || undefined,
        }, { preserveState: true });
    }

    function clearFilters() {
        setDateFrom(''); setDateTo(''); setChangeType('');
        router.get(`/admin/stock/${size.id}/audit`, {}, { preserveState: true });
    }

    const hasFilters = dateFrom || dateTo || changeType;

    return (
        <AdminLayout title={`Audit Log — ${size.name}`} subtitle="Stock change history for this cylinder size">
            <div className="mb-6 flex items-center justify-between">
                <Link href="/admin/stock" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <ArrowLeft className="h-4 w-4" /> Back to Stock
                </Link>
                <Button asChild variant="outline" size="sm" className="h-8 gap-1.5 text-xs">
                    <Link href={`/admin/stock/${size.id}/adjust`}>Adjust Stock</Link>
                </Button>
            </div>

            {/* Filters */}
            <form onSubmit={applyFilters} className="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div>
                    <Label className="text-xs font-medium text-slate-600">From</Label>
                    <Input type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)}
                        className="mt-1 h-8 w-36 border-slate-200 bg-slate-50 text-xs focus:border-orange-400 focus:ring-orange-400/20" />
                </div>
                <div>
                    <Label className="text-xs font-medium text-slate-600">To</Label>
                    <Input type="date" value={dateTo} onChange={e => setDateTo(e.target.value)}
                        className="mt-1 h-8 w-36 border-slate-200 bg-slate-50 text-xs focus:border-orange-400 focus:ring-orange-400/20" />
                </div>
                <div>
                    <Label className="text-xs font-medium text-slate-600">Change Type</Label>
                    <select
                        value={changeType}
                        onChange={e => setChangeType(e.target.value)}
                        className="mt-1 h-8 rounded-md border border-slate-200 bg-slate-50 px-2 text-xs text-slate-800 focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20"
                    >
                        <option value="">All types</option>
                        <option value="manual_adjustment">Manual Adjustment</option>
                        <option value="auto_deduction">Auto Deduction</option>
                        <option value="auto_return">Auto Return</option>
                        <option value="out_of_stock_cancel">OOS Cancel</option>
                    </select>
                </div>
                <div className="flex gap-2 pb-0.5">
                    <Button type="submit" size="sm" className="h-8 bg-orange-500 hover:bg-orange-600 text-xs shadow-sm shadow-orange-500/20">Apply</Button>
                    {hasFilters && (
                        <Button type="button" variant="outline" size="sm" className="h-8 text-xs" onClick={clearFilters}>Clear</Button>
                    )}
                </div>
            </form>

            <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/80">
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date / Time</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th className="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Change</th>
                            <th className="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Resulting Count</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">By</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Note / Order</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {logs.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="py-16 text-center">
                                    <div className="flex flex-col items-center gap-2">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                            <FileText className="h-6 w-6 text-slate-300" />
                                        </div>
                                        <p className="text-sm font-medium text-slate-400">No audit log entries</p>
                                        <p className="text-xs text-slate-300">
                                            {hasFilters ? 'Try adjusting your filters.' : 'Stock changes will appear here.'}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {logs.data.map(log => (
                            <tr key={log.id} className="hover:bg-slate-50/50 transition-colors">
                                <td className="px-5 py-3.5 text-xs text-slate-500 whitespace-nowrap">{log.created_at ?? '—'}</td>
                                <td className="px-5 py-3.5"><ChangeTypeBadge type={log.change_type} /></td>
                                <td className="px-5 py-3.5 text-center">
                                    <span className={cn(
                                        'text-sm font-bold tabular-nums',
                                        log.change_amount > 0 ? 'text-emerald-600' : log.change_amount < 0 ? 'text-red-600' : 'text-slate-400',
                                    )}>
                                        {log.change_amount > 0 ? `+${log.change_amount}` : log.change_amount}
                                    </span>
                                </td>
                                <td className="px-5 py-3.5 text-center font-semibold text-slate-800 tabular-nums">{log.new_count}</td>
                                <td className="px-5 py-3.5 text-xs text-slate-600">{log.admin_name}</td>
                                <td className="px-5 py-3.5 text-xs text-slate-500 max-w-xs">
                                    {log.order_id && (
                                        <Link href={`/admin/orders/${log.order_id}`} className="font-medium text-orange-600 hover:text-orange-700">
                                            Order #{log.order_id}
                                        </Link>
                                    )}
                                    {log.note && <span className={log.order_id ? 'ml-2 text-slate-400' : ''}>{log.note}</span>}
                                    {!log.order_id && !log.note && <span className="text-slate-300 italic">—</span>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {/* Pagination */}
                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <p className="text-xs text-slate-400">Page {logs.current_page} of {logs.last_page}</p>
                        <div className="flex gap-2">
                            {logs.prev_page_url && (
                                <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                                    <Link href={logs.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {logs.next_page_url && (
                                <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                                    <Link href={logs.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
