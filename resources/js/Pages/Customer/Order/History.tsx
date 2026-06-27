import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link, router } from '@inertiajs/react';
import { Package, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Order {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    order_type: string;
    size_name: string | null;
    brand_name: string | null;
    total_amount: number;
    created_at: string;
    can_cancel: boolean;
    can_rate: boolean;
    can_track: boolean;
}

interface PaginatedOrders {
    data: Order[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    orders: PaginatedOrders;
}

const STATUS_COLORS: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    rider_assigned: 'bg-blue-100 text-blue-700',
    picked_up: 'bg-indigo-100 text-indigo-700',
    on_the_way: 'bg-violet-100 text-violet-700',
    correction_in_progress: 'bg-rose-100 text-rose-700',
    delivered: 'bg-green-100 text-green-700',
    cancelled: 'bg-slate-100 text-slate-500',
};

const STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    rider_assigned: 'Rider Assigned',
    picked_up: 'Picked Up',
    on_the_way: 'On the Way',
    correction_in_progress: 'Correction In Progress',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
};

const PAYMENT_CHIPS: Record<string, string> = {
    pending: 'border-amber-200 bg-amber-50 text-amber-700',
    collected: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    disputed: 'border-red-200 bg-red-50 text-red-600',
    refunded: 'border-slate-200 bg-slate-50 text-slate-600',
};

const PAYMENT_LABELS: Record<string, string> = {
    pending: 'Payment pending',
    collected: 'Paid',
    disputed: 'Disputed',
    refunded: 'Refunded',
};

const fmt = (amount: number) => `KES ${amount.toLocaleString()}`;

function Pagination({ orders }: { orders: PaginatedOrders }) {
    if (orders.last_page <= 1) return null;

    return (
        <div className="flex justify-center gap-2 pt-2">
            {orders.prev_page_url && (
                <Link href={orders.prev_page_url} className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Previous
                </Link>
            )}
            <span className="flex items-center px-3 text-sm text-slate-500">
                {orders.current_page} / {orders.last_page}
            </span>
            {orders.next_page_url && (
                <Link href={orders.next_page_url} className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Next
                </Link>
            )}
        </div>
    );
}

export default function History({ orders }: Props) {
    return (
        <CustomerLayout title="My Orders" showBack backHref="/home">
            <div className="mx-auto max-w-sm px-4 py-4 md:max-w-4xl">
                {orders.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-20 text-center">
                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-orange-50">
                            <Package className="h-8 w-8 text-orange-300" />
                        </div>
                        <p className="text-base font-semibold text-slate-700">No orders yet</p>
                        <p className="mt-1 max-w-xs text-sm text-slate-400">Your order history will appear here once you place your first order.</p>
                        <Link href="/order/new" className="mt-6 inline-flex items-center gap-2 rounded-xl bg-orange-500 px-6 py-3 text-sm font-semibold text-white shadow-md shadow-orange-500/20 transition-colors hover:bg-orange-600">
                            Place your first order
                        </Link>
                    </div>
                ) : (
                    <>
                        <div className="space-y-3 md:hidden">
                            {orders.data.map((order) => (
                                <Link key={order.id} href={`/orders/${order.id}`} className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 transition-colors hover:bg-slate-50">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orange-100">
                                        <Package className="h-5 w-5 text-orange-500" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="truncate font-mono text-xs font-semibold text-slate-700">{order.order_number}</p>
                                            <span className={cn('shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium', STATUS_COLORS[order.status] ?? 'bg-slate-100 text-slate-500')}>
                                                {STATUS_LABELS[order.status] ?? order.status}
                                            </span>
                                            <span className={cn('shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-medium', PAYMENT_CHIPS[order.payment_status] ?? PAYMENT_CHIPS.pending)}>
                                                {PAYMENT_LABELS[order.payment_status] ?? order.payment_status}
                                            </span>
                                        </div>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {order.size_name}
                                            {order.brand_name ? ` · ${order.brand_name}` : ''}
                                            {' · '}
                                            {order.created_at}
                                        </p>
                                        <p className="mt-0.5 text-sm font-bold text-slate-800">{fmt(order.total_amount)}</p>
                                    </div>
                                    <ChevronRight className="h-4 w-4 shrink-0 text-slate-400" />
                                </Link>
                            ))}
                            <Pagination orders={orders} />
                        </div>

                        <div className="hidden space-y-3 md:block">
                            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-100 bg-slate-50">
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Order</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Item</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Payment</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Date</th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Total</th>
                                            <th className="w-8 px-4 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {orders.data.map((order) => (
                                            <tr key={order.id} onClick={() => router.visit(`/orders/${order.id}`)} className="cursor-pointer transition-colors hover:bg-slate-50">
                                                <td className="px-4 py-3 font-mono text-xs font-semibold text-slate-700">{order.order_number}</td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    {order.size_name}
                                                    {order.brand_name ? ` · ${order.brand_name}` : ''}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className={cn('rounded-full px-2.5 py-1 text-[11px] font-medium', STATUS_COLORS[order.status] ?? 'bg-slate-100 text-slate-500')}>
                                                        {STATUS_LABELS[order.status] ?? order.status}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className={cn('rounded-full border px-2.5 py-1 text-[11px] font-medium', PAYMENT_CHIPS[order.payment_status] ?? PAYMENT_CHIPS.pending)}>
                                                        {PAYMENT_LABELS[order.payment_status] ?? order.payment_status}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-slate-500">{order.created_at}</td>
                                                <td className="px-4 py-3 text-right font-bold tabular-nums text-slate-800">{fmt(order.total_amount)}</td>
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