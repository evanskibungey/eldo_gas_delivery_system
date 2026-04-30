import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Eye, RefreshCw, Package, Clock, AlertCircle,
    Search, ShoppingBag, ChevronLeft, ChevronRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { OrderStatus } from '@/types/models';

// ── Types ─────────────────────────────────────────────────────────────────────

interface OrderRow {
    id:             number;
    order_number:   string;
    status:         OrderStatus;
    order_type:     'swap' | 'new_cylinder';
    size_name:      string | null;
    brand_name:     string | null;
    customer_name:  string | null;
    customer_phone: string | null;
    rider_name:     string | null;
    total_amount:   number;
    payment_method: 'cash' | 'mpesa';
    payment_status: string;
    has_issue:      boolean;
    issue_type:     string | null;
    created_at:     string;
    created_ago:    string;
}

interface Paginated {
    data:          OrderRow[];
    current_page:  number;
    last_page:     number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total:         number;
}

interface Props {
    orders:  Paginated;
    filters: { status?: string; search?: string; date?: string };
    counts:  { pending: number; active: number; delivered: number; cancelled: number };
}

// ── Config ────────────────────────────────────────────────────────────────────

const STATUS_CFG: Record<OrderStatus, { label: string; dot: string; chip: string }> = {
    pending:                 { label: 'Pending',        dot: 'bg-amber-400',   chip: 'border-amber-200   bg-amber-50   text-amber-700'   },
    rider_assigned:          { label: 'Assigned',       dot: 'bg-blue-400',    chip: 'border-blue-200    bg-blue-50    text-blue-700'    },
    picked_up:               { label: 'Picked Up',      dot: 'bg-indigo-400',  chip: 'border-indigo-200  bg-indigo-50  text-indigo-700'  },
    on_the_way:              { label: 'On the Way',     dot: 'bg-violet-400',  chip: 'border-violet-200  bg-violet-50  text-violet-700'  },
    correction_in_progress:  { label: 'Correction',     dot: 'bg-rose-400',    chip: 'border-rose-200    bg-rose-50    text-rose-700'    },
    delivered:               { label: 'Delivered',      dot: 'bg-emerald-400', chip: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    cancelled:               { label: 'Cancelled',      dot: 'bg-red-400',     chip: 'border-red-200     bg-red-50     text-red-600'     },
};

const PAYMENT_CHIP: Record<string, string> = {
    pending:   'bg-amber-50   text-amber-600   border-amber-200',
    collected: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    disputed:  'bg-red-50     text-red-600     border-red-200',
    refunded:  'bg-slate-100  text-slate-500   border-slate-200',
};

// ── Sub-components ────────────────────────────────────────────────────────────

function StatusBadge({ status }: { status: OrderStatus }) {
    const cfg = STATUS_CFG[status];
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold', cfg.chip)}>
            <span className={cn('h-1.5 w-1.5 rounded-full shrink-0', cfg.dot)} />
            {cfg.label}
        </span>
    );
}

function TabBtn({ label, count, active, onClick }: {
    label: string; count?: number; active: boolean; onClick: () => void;
}) {
    return (
        <button
            onClick={onClick}
            className={cn(
                'inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-medium transition-all duration-150 border',
                active
                    ? 'bg-orange-500 text-white border-orange-500 shadow-sm shadow-orange-500/20'
                    : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300 hover:text-slate-700',
            )}
        >
            {label}
            {count != null && count > 0 && (
                <span className={cn(
                    'flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold',
                    active ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600',
                )}>
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </button>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function OrdersIndex({ orders, filters, counts }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [newOrderFlash, setNewOrderFlash] = useState(false);

    // WebSocket: subscribe to admin.orders private channel for new order notifications
    useEffect(() => {
        const channel = window.Echo.private('admin.orders');

        channel.listen('.order.placed', () => {
            // Flash the tab/badge to alert admin, then reload the list
            setNewOrderFlash(true);
            router.reload({ only: ['orders', 'counts'] });
            setTimeout(() => setNewOrderFlash(false), 3000);
        });

        return () => {
            window.Echo.leave('admin.orders');
        };
    }, []);

    function applyFilter(status?: string) {
        router.get('/admin/orders', {
            status: status || undefined,
            search: search || undefined,
        }, { preserveState: true, preserveScroll: true });
    }

    function applySearch() {
        router.get('/admin/orders', {
            status:  filters.status  || undefined,
            search: search || undefined,
        }, { preserveState: true });
    }

    const activeTab = filters.status ?? 'all';
    const totalAll  = counts.pending + counts.active + counts.delivered + counts.cancelled;

    return (
        <AdminLayout title="Orders" subtitle="Live order feed and dispatch management">

            {/* New order flash banner */}
            {newOrderFlash && (
                <div className="mb-4 flex items-center gap-2 rounded-xl border border-orange-300 bg-orange-50 px-4 py-2.5 text-sm font-semibold text-orange-700 animate-pulse">
                    <span className="h-2 w-2 rounded-full bg-orange-500" />
                    New order received!
                </div>
            )}

            {/* Status tabs */}
            <div className="mb-5 flex flex-wrap items-center gap-2">
                <TabBtn label="All"       count={totalAll}       active={activeTab === 'all'}       onClick={() => applyFilter()} />
                <TabBtn label="Pending"   count={counts.pending} active={activeTab === 'pending'}   onClick={() => applyFilter('pending')} />
                <TabBtn label="Active"    count={counts.active}  active={activeTab === 'active'}    onClick={() => applyFilter('active')} />
                <TabBtn label="Delivered"                        active={activeTab === 'delivered'}  onClick={() => applyFilter('delivered')} />
                <TabBtn label="Cancelled"                        active={activeTab === 'cancelled'}  onClick={() => applyFilter('cancelled')} />

                <div className="ml-auto flex items-center gap-2">
                    <div className="relative">
                        <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400 pointer-events-none" />
                        <Input
                            placeholder="Order # or customer…"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applySearch()}
                            className="h-8 w-52 pl-8 border-slate-200 bg-white text-xs focus:border-orange-400 focus:ring-orange-400/20"
                        />
                    </div>
                    <Button size="sm" variant="outline" className="h-8 gap-1.5 text-xs"
                        onClick={() => router.reload({ only: ['orders', 'counts'] })}>
                        <RefreshCw className="h-3 w-3" /> Refresh
                    </Button>
                </div>
            </div>

            {/* Table */}
            <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />

                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/80">
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Order</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Customer</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Details</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Rider</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
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
                                        <p className="text-sm font-medium text-slate-400">No orders found</p>
                                        <p className="text-xs text-slate-300">Try a different filter or search term.</p>
                                    </div>
                                </td>
                            </tr>
                        )}

                        {orders.data.map(o => (
                            <tr key={o.id} className={cn(
                                'group transition-colors',
                                o.status === 'pending' ? 'bg-amber-50/30 hover:bg-amber-50/50' : 'hover:bg-slate-50/50',
                            )}>
                                {/* Order */}
                                <td className="px-5 py-4">
                                    <div className="flex items-center gap-2">
                                        <div className={cn(
                                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                                            o.order_type === 'swap' ? 'bg-orange-50' : 'bg-blue-50',
                                        )}>
                                            {o.order_type === 'swap'
                                                ? <RefreshCw className="h-3.5 w-3.5 text-orange-500" />
                                                : <Package   className="h-3.5 w-3.5 text-blue-500"   />}
                                        </div>
                                        <div>
                                            <p className="font-semibold text-slate-900 text-xs">{o.order_number}</p>
                                            <p className="text-[10px] text-slate-400 flex items-center gap-0.5 mt-0.5">
                                                <Clock className="h-2.5 w-2.5 shrink-0" />
                                                {o.created_ago}
                                            </p>
                                        </div>
                                        {o.has_issue && (
                                            <span className="flex items-center gap-0.5 text-red-500 shrink-0" title={o.issue_type?.replace(/_/g, ' ')}>
                                                <AlertCircle className="h-3.5 w-3.5" />
                                                {o.issue_type === 'damaged_cylinder' && (
                                                    <span className="text-[9px] font-bold uppercase leading-none">P0</span>
                                                )}
                                            </span>
                                        )}
                                    </div>
                                </td>

                                {/* Customer */}
                                <td className="px-5 py-4">
                                    <p className="text-sm font-medium text-slate-800">{o.customer_name ?? '—'}</p>
                                    <p className="text-[10px] text-slate-400">{o.customer_phone}</p>
                                </td>

                                {/* Details */}
                                <td className="px-5 py-4">
                                    <p className="text-xs font-semibold text-slate-700">{o.size_name}</p>
                                    <div className="mt-0.5 flex items-center gap-1.5">
                                        <span className={cn(
                                            'rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase',
                                            o.order_type === 'swap' ? 'bg-orange-50 text-orange-600' : 'bg-blue-50 text-blue-600',
                                        )}>
                                            {o.order_type === 'swap' ? 'Swap' : 'New'}
                                        </span>
                                        <span className={cn(
                                            'text-[10px] font-bold uppercase',
                                            o.payment_method === 'mpesa' ? 'text-emerald-600' : 'text-slate-400',
                                        )}>
                                            {o.payment_method}
                                        </span>
                                    </div>
                                </td>

                                {/* Status */}
                                <td className="px-5 py-4">
                                    <StatusBadge status={o.status} />
                                    {o.payment_status !== 'pending' && (
                                        <span className={cn(
                                            'mt-1 block w-fit rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize',
                                            PAYMENT_CHIP[o.payment_status] ?? PAYMENT_CHIP.pending,
                                        )}>
                                            {o.payment_status}
                                        </span>
                                    )}
                                </td>

                                {/* Rider */}
                                <td className="px-5 py-4">
                                    {o.rider_name ? (
                                        <p className="text-xs font-medium text-slate-700">{o.rider_name}</p>
                                    ) : (
                                        o.status === 'pending' ? (
                                            <Link
                                                href={`/admin/orders/${o.id}`}
                                                className="inline-flex items-center gap-1 rounded-lg border border-orange-200 bg-orange-50 px-2.5 py-1 text-[10px] font-semibold text-orange-600 hover:bg-orange-100 transition-colors"
                                            >
                                                Assign Rider →
                                            </Link>
                                        ) : (
                                            <span className="text-xs text-slate-300 italic">None</span>
                                        )
                                    )}
                                </td>

                                {/* Amount */}
                                <td className="px-5 py-4 text-right">
                                    <p className="text-sm font-bold text-slate-900 tabular-nums">
                                        KES {o.total_amount.toLocaleString()}
                                    </p>
                                </td>

                                {/* Action */}
                                <td className="px-5 py-4 text-right">
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
                                    <Link href={orders.prev_page_url}>
                                        <ChevronLeft className="h-3 w-3" /> Prev
                                    </Link>
                                </Button>
                            )}
                            {orders.next_page_url && (
                                <Button asChild variant="outline" size="sm" className="h-8 gap-1 text-xs">
                                    <Link href={orders.next_page_url}>
                                        Next <ChevronRight className="h-3 w-3" />
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
