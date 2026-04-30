import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Search, Eye, Download, RefreshCw, AlertCircle,
    Package, ChevronLeft, ChevronRight, ShoppingBag,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

// ── Types ─────────────────────────────────────────────────────────────────────

interface OrderRow {
    id:             number;
    order_number:   string;
    status:         string;
    order_type:     string;
    customer_name:  string | null;
    customer_phone: string | null;
    size_name:      string | null;
    brand_name:     string | null;
    rider_name:     string | null;
    total_amount:   number;
    payment_method: string;
    payment_status: string;
    has_issue:      boolean;
    issue_type:     string | null;
    created_at:     string;
}

interface Paginated {
    data:          OrderRow[];
    current_page:  number;
    last_page:     number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total:         number;
}

interface Size   { id: number; name: string }
interface Rider  { id: number; name: string }

interface Props {
    orders:  Paginated;
    sizes:   Size[];
    riders:  Rider[];
    filters: {
        from?: string; to?: string; status?: string;
        order_type?: string; rider_id?: string; size_id?: string;
    };
    summary: { total_orders: number; with_issues: number; exception_rate: number };
}

// ── Config ────────────────────────────────────────────────────────────────────

const STATUS_CHIP: Record<string, string> = {
    pending:        'border-amber-200   bg-amber-50   text-amber-700',
    rider_assigned: 'border-blue-200    bg-blue-50    text-blue-700',
    picked_up:      'border-indigo-200  bg-indigo-50  text-indigo-700',
    delivered:      'border-emerald-200 bg-emerald-50 text-emerald-700',
    cancelled:      'border-red-200     bg-red-50     text-red-600',
};

const STATUS_LABEL: Record<string, string> = {
    pending: 'Pending', rider_assigned: 'Assigned', picked_up: 'On the Way',
    delivered: 'Delivered', cancelled: 'Cancelled',
};

// ── Page ──────────────────────────────────────────────────────────────────────

export default function OrdersReport({ orders, sizes, riders, filters, summary }: Props) {
    const [from,      setFrom]      = useState(filters.from      ?? '');
    const [to,        setTo]        = useState(filters.to        ?? '');
    const [status,    setStatus]    = useState(filters.status    ?? '');
    const [orderType, setOrderType] = useState(filters.order_type ?? '');
    const [riderId,   setRiderId]   = useState(filters.rider_id  ?? '');
    const [sizeId,    setSizeId]    = useState(filters.size_id   ?? '');

    function applyFilters() {
        router.get('/admin/reports/orders', {
            from:       from       || undefined,
            to:         to         || undefined,
            status:     status     || undefined,
            order_type: orderType  || undefined,
            rider_id:   riderId    || undefined,
            size_id:    sizeId     || undefined,
        }, { preserveState: true });
    }

    function buildExportUrl() {
        const p = new URLSearchParams();
        if (from)      p.set('from', from);
        if (to)        p.set('to', to);
        if (status)    p.set('status', status);
        if (orderType) p.set('order_type', orderType);
        if (riderId)   p.set('rider_id', riderId);
        if (sizeId)    p.set('size_id', sizeId);
        return `/admin/reports/orders/export?${p.toString()}`;
    }

    const sel = 'h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20';

    return (
        <AdminLayout title="Order Report" subtitle="Filter, analyse, and export order data">

            {/* Summary row */}
            <div className="mb-5 grid grid-cols-3 gap-3">
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm text-center">
                    <p className="text-2xl font-black text-slate-900 tabular-nums">{summary.total_orders.toLocaleString()}</p>
                    <p className="text-xs text-slate-500 font-medium">Total Orders</p>
                </div>
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm text-center">
                    <p className="text-2xl font-black text-amber-700 tabular-nums">{summary.with_issues}</p>
                    <p className="text-xs text-amber-600 font-medium">With Issues</p>
                </div>
                <div className={cn('rounded-xl border p-4 shadow-sm text-center',
                    summary.exception_rate > 10 ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white',
                )}>
                    <p className={cn('text-2xl font-black tabular-nums',
                        summary.exception_rate > 10 ? 'text-red-600' : 'text-slate-900',
                    )}>
                        {summary.exception_rate}%
                    </p>
                    <p className="text-xs text-slate-500 font-medium">Exception Rate</p>
                </div>
            </div>

            {/* Filters */}
            <div className="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div>
                    <p className="text-[10px] font-semibold uppercase text-slate-400 mb-1">From</p>
                    <input type="date" value={from} onChange={e => setFrom(e.target.value)} className={sel} />
                </div>
                <div>
                    <p className="text-[10px] font-semibold uppercase text-slate-400 mb-1">To</p>
                    <input type="date" value={to} onChange={e => setTo(e.target.value)} className={sel} />
                </div>
                <div>
                    <p className="text-[10px] font-semibold uppercase text-slate-400 mb-1">Status</p>
                    <select value={status} onChange={e => setStatus(e.target.value)} className={sel}>
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="rider_assigned">Assigned</option>
                        <option value="picked_up">On the Way</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <p className="text-[10px] font-semibold uppercase text-slate-400 mb-1">Type</p>
                    <select value={orderType} onChange={e => setOrderType(e.target.value)} className={sel}>
                        <option value="">All</option>
                        <option value="swap">Swap</option>
                        <option value="new_cylinder">New Cylinder</option>
                    </select>
                </div>
                <div>
                    <p className="text-[10px] font-semibold uppercase text-slate-400 mb-1">Size</p>
                    <select value={sizeId} onChange={e => setSizeId(e.target.value)} className={sel}>
                        <option value="">All</option>
                        {sizes.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                </div>
                <div>
                    <p className="text-[10px] font-semibold uppercase text-slate-400 mb-1">Rider</p>
                    <select value={riderId} onChange={e => setRiderId(e.target.value)} className={sel}>
                        <option value="">All</option>
                        {riders.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
                    </select>
                </div>
                <div className="flex items-end gap-2 ml-auto">
                    <Button onClick={applyFilters} className="h-9 bg-orange-500 hover:bg-orange-600 text-xs gap-1.5">
                        <RefreshCw className="h-3.5 w-3.5" /> Apply
                    </Button>
                    <a href={buildExportUrl()} className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                        <Download className="h-3.5 w-3.5" /> CSV
                    </a>
                </div>
            </div>

            {/* Table */}
            <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/80">
                            <th className="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Order</th>
                            <th className="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Customer</th>
                            <th className="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Details</th>
                            <th className="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Rider</th>
                            <th className="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                            <th className="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {orders.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="py-16 text-center">
                                    <div className="flex flex-col items-center gap-2">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                            <ShoppingBag className="h-6 w-6 text-slate-300" />
                                        </div>
                                        <p className="text-sm font-medium text-slate-400">No orders match the selected filters</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {orders.data.map(o => (
                            <tr key={o.id} className="hover:bg-slate-50/50 transition-colors group">
                                {/* Order */}
                                <td className="px-4 py-3.5">
                                    <div className="flex items-center gap-2">
                                        <div className={cn('flex h-7 w-7 shrink-0 items-center justify-center rounded-lg', o.order_type === 'swap' ? 'bg-orange-50' : 'bg-blue-50')}>
                                            {o.order_type === 'swap'
                                                ? <RefreshCw className="h-3 w-3 text-orange-500" />
                                                : <Package   className="h-3 w-3 text-blue-500" />}
                                        </div>
                                        <div>
                                            <p className="font-mono text-xs font-semibold text-slate-700">{o.order_number}</p>
                                            <p className="text-[10px] text-slate-400">{o.created_at}</p>
                                        </div>
                                        {o.has_issue && (
                                            <AlertCircle className="h-3.5 w-3.5 text-red-500 shrink-0" title={o.issue_type ?? 'Issue'} />
                                        )}
                                    </div>
                                </td>

                                {/* Customer */}
                                <td className="px-4 py-3.5">
                                    <p className="text-xs font-medium text-slate-800">{o.customer_name ?? '—'}</p>
                                    <p className="text-[10px] text-slate-400 font-mono">{o.customer_phone}</p>
                                </td>

                                {/* Details */}
                                <td className="px-4 py-3.5">
                                    <p className="text-xs font-semibold text-slate-700">{o.size_name}</p>
                                    <div className="flex items-center gap-1.5 mt-0.5">
                                        <span className={cn('text-[10px] font-bold uppercase', o.payment_method === 'mpesa' ? 'text-emerald-600' : 'text-slate-400')}>
                                            {o.payment_method}
                                        </span>
                                    </div>
                                </td>

                                {/* Rider */}
                                <td className="px-4 py-3.5">
                                    <p className="text-xs text-slate-600">{o.rider_name ?? <span className="italic text-slate-300">None</span>}</p>
                                </td>

                                {/* Status */}
                                <td className="px-4 py-3.5">
                                    <span className={cn('inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold', STATUS_CHIP[o.status] ?? STATUS_CHIP.cancelled)}>
                                        {STATUS_LABEL[o.status] ?? o.status}
                                    </span>
                                </td>

                                {/* Amount */}
                                <td className="px-4 py-3.5 text-right">
                                    <p className="text-sm font-bold text-slate-900 tabular-nums">KES {o.total_amount.toLocaleString()}</p>
                                </td>

                                {/* Action */}
                                <td className="px-4 py-3.5 text-right">
                                    <Button asChild variant="ghost" size="sm"
                                        className="h-8 w-8 p-0 opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-slate-700">
                                        <Link href={`/admin/orders/${o.id}`}>
                                            <Eye className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {/* Pagination */}
                {orders.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <p className="text-xs text-slate-400">
                            Page {orders.current_page} of {orders.last_page} · {orders.total} orders
                        </p>
                        <div className="flex gap-2">
                            {orders.prev_page_url && (
                                <Button asChild variant="outline" size="sm" className="h-8 gap-1 text-xs">
                                    <Link href={orders.prev_page_url}><ChevronLeft className="h-3 w-3" /> Prev</Link>
                                </Button>
                            )}
                            {orders.next_page_url && (
                                <Button asChild variant="outline" size="sm" className="h-8 gap-1 text-xs">
                                    <Link href={orders.next_page_url}>Next <ChevronRight className="h-3 w-3" /></Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

        </AdminLayout>
    );
}
