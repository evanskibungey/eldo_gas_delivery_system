import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link } from '@inertiajs/react';
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

export default function History({ orders }: Props) {
    return (
        <CustomerLayout title="My Orders" showBack backHref="/home">
            <div className="mx-auto max-w-sm px-4 py-4">
                {orders.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-20 text-center">
                        <Package className="h-12 w-12 text-slate-300" />
                        <p className="mt-3 text-sm font-medium text-slate-500">No orders yet</p>
                        <Link
                            href="/order/new"
                            className="mt-4 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-600"
                        >
                            Place your first order
                        </Link>
                    </div>
                ) : (
                    <div className="space-y-3">
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

                        {/* Pagination */}
                        {orders.last_page > 1 && (
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
                        )}
                    </div>
                )}
            </div>
        </CustomerLayout>
    );
}
