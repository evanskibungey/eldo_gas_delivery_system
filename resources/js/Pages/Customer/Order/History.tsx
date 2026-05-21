import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link, router } from '@inertiajs/react';
import { Package, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Order {
    id:           number;
    order_number: string;
    status:       string;
    order_type:   string;
    size_name:    string | null;
    brand_name:   string | null;
    total_amount: number;
    created_at:   string;
    can_cancel:   boolean;
    can_rate:     boolean;
    can_track:    boolean;
}

interface PaginatedOrders {
    data:          Order[];
    current_page:  number;
    last_page:     number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    orders: PaginatedOrders;
}

const STATUS_COLORS: Record<string, string> = {
    pending:         'bg-amber-100 text-amber-700',
    rider_assigned:  'bg-blue-100 text-blue-700',
    picked_up:       'bg-indigo-100 text-indigo-700',
    delivered:       'bg-green-100 text-green-700',
    cancelled:       'bg-slate-100 text-slate-500',
};

const STATUS_LABELS: Record<string, string> = {
    pending:         'Pending',
    rider_assigned:  'Rider Assigned',
    picked_up:       'On the Way',
    delivered:       'Delivered',
    cancelled:       'Cancelled',
};

const fmt = (n: number) => `KES ${n.toLocaleString()}`;

// ── Pagination controls (shared between mobile and desktop) ─────────────────
function Pagination({ orders }: { orders: PaginatedOrders }) {
    if (orders.last_page <= 1) return null;
    return (
        <div className="flex justify-center gap-2 pt-2">
            {orders.prev_page_url && (
                <Link
                    href={orders.prev_page_url}
                    className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    ← Prev
                </Link>
            )}
            <span className="flex items-center px-3 text-sm text-slate-500">
                {orders.current_page} / {orders.last_page}
            </span>
            {orders.next_page_url && (
                <Link
                    href={orders.next_page_url}
                    className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Next →
                </Link>
            )}
        </div>
    );
}

export default function History({ orders }: Props) {
    return (
        <CustomerLayout title="My Orders" showBack backHref="/home">
            <div className="mx-auto max-w-sm md:max-w-4xl px-4 py-4">
                {orders.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-20 text-center">
                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-orange-50 mb-4">
                            <Package className="h-8 w-8 text-orange-300" />
                        </div>
                        <p className="text-base font-semibold text-slate-700">No orders yet</p>
                        <p className="mt-1 text-sm text-slate-400 max-w-xs">
                            Your order history will appear here once you place your first order.
                        </p>
                        <Link
                            href="/order/new"
                            className="mt-6 inline-flex items-center gap-2 rounded-xl bg-orange-500 px-6 py-3 text-sm font-semibold text-white shadow-md shadow-orange-500/20 hover:bg-orange-600 transition-colors"
                        >
                            Place your first order
                        </Link>
                    </div>
                ) : (
                    <>
                        {/* Mobile: card list */}
                        <div className="md:hidden space-y-3">
                            {orders.data.map(o => (
                                <Link
                                    key={o.id}
                                    href={`/orders/${o.id}`}
                                    className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 hover:bg-slate-50 transition-colors"
                                >
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orange-100">
                                        <Package className="h-5 w-5 text-orange-500" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="font-mono text-xs font-semibold text-slate-700 truncate">{o.order_number}</p>
                                            <span className={cn(
                                                'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                STATUS_COLORS[o.status] ?? 'bg-slate-100 text-slate-500',
                                            )}>
                                                {STATUS_LABELS[o.status] ?? o.status}
                                            </span>
                                        </div>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {o.size_name}{o.brand_name ? ` · ${o.brand_name}` : ''} · {o.created_at}
                                        </p>
                                        <p className="mt-0.5 text-sm font-bold text-slate-800">{fmt(o.total_amount)}</p>
                                    </div>
                                    <ChevronRight className="h-4 w-4 shrink-0 text-slate-400" />
                                </Link>
                            ))}
                            <Pagination orders={orders} />
                        </div>

                        {/* Desktop: table */}
                        <div className="hidden md:block space-y-3">
                            <div className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-100 bg-slate-50">
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Order</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Item</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Date</th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Total</th>
                                            <th className="px-4 py-3 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {orders.data.map(o => (
                                            <tr
                                                key={o.id}
                                                onClick={() => router.visit(`/orders/${o.id}`)}
                                                className="hover:bg-slate-50 cursor-pointer transition-colors"
                                            >
                                                <td className="px-4 py-3 font-mono text-xs font-semibold text-slate-700">{o.order_number}</td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    {o.size_name}{o.brand_name ? ` · ${o.brand_name}` : ''}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className={cn(
                                                        'rounded-full px-2.5 py-1 text-[11px] font-medium',
                                                        STATUS_COLORS[o.status] ?? 'bg-slate-100 text-slate-500',
                                                    )}>
                                                        {STATUS_LABELS[o.status] ?? o.status}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-slate-500">{o.created_at}</td>
                                                <td className="px-4 py-3 text-right font-bold text-slate-800 tabular-nums">{fmt(o.total_amount)}</td>
                                                <td className="px-4 py-3">
                                                    <ChevronRight className="h-4 w-4 text-slate-300" />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <Pagination orders={orders} />
                        </div>
                    </>
                )}
            </div>
        </CustomerLayout>
    );
}
