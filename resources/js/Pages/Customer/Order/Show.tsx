import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { MapPin, Phone, Star, AlertCircle, Package } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Addon  { name: string | null; price: number }
interface History { status: string; at: string }
interface Rider  { name: string; phone: string; avg_rating: number | null; avatar_url: string | null }

interface Order {
    id:             number;
    order_number:   string;
    order_type:     string;
    status:         string;
    size_name:      string | null;
    brand_name:     string | null;
    gas_price:      number;
    cylinder_price: number;
    delivery_fee:   number;
    addons_total:   number;
    total_amount:   number;
    payment_method: string;
    delivery_notes: string | null;
    created_at:     string;
    can_cancel:     boolean;
    can_rate:       boolean;
    can_track:      boolean;
    addons:         Addon[];
    history:        History[];
    rider:          Rider | null;
}

interface Props {
    order:      Order;
    mpesa_till: string;
}

const STATUS_COLORS: Record<string, string> = {
    pending:        'bg-amber-100 text-amber-700',
    rider_assigned: 'bg-blue-100 text-blue-700',
    picked_up:      'bg-indigo-100 text-indigo-700',
    delivered:      'bg-green-100 text-green-700',
    cancelled:      'bg-red-100 text-red-600',
};

const STATUS_LABELS: Record<string, string> = {
    pending:        'Pending',
    rider_assigned: 'Rider Assigned',
    picked_up:      'On the Way',
    delivered:      'Delivered',
    cancelled:      'Cancelled',
};

const fmt = (n: number) => `KES ${n.toLocaleString()}`;

export default function Show({ order, mpesa_till }: Props) {
    const [cancelling, setCancelling] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    function cancel() {
        setCancelling(true);
        router.post(`/orders/${order.id}/cancel`, {}, {
            onFinish: () => { setCancelling(false); setShowConfirm(false); },
        });
    }

    return (
        <CustomerLayout title={`Order ${order.order_number}`} showBack backHref="/orders">
            <div className="mx-auto max-w-sm px-4 py-4 space-y-4">

                {/* Header status */}
                <div className="flex items-center justify-between">
                    <p className="font-mono text-xs text-slate-500">{order.order_number}</p>
                    <span className={cn(
                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                        STATUS_COLORS[order.status] ?? 'bg-slate-100 text-slate-500',
                    )}>
                        {STATUS_LABELS[order.status] ?? order.status}
                    </span>
                </div>

                {/* Order details */}
                <section className="rounded-xl bg-white border border-slate-200 p-4">
                    <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Order Details</h2>
                    <div className="space-y-1.5 text-sm">
                        <div className="flex justify-between text-slate-600">
                            <span>Type</span>
                            <span className="font-medium capitalize">{order.order_type.replace('_', ' ')}</span>
                        </div>
                        {order.size_name && (
                            <div className="flex justify-between text-slate-600">
                                <span>Size</span><span className="font-medium">{order.size_name}</span>
                            </div>
                        )}
                        {order.brand_name && (
                            <div className="flex justify-between text-slate-600">
                                <span>Brand</span><span className="font-medium">{order.brand_name}</span>
                            </div>
                        )}
                        <div className="flex justify-between text-slate-600">
                            <span>Placed</span><span>{order.created_at}</span>
                        </div>
                    </div>
                </section>

                {/* Price */}
                <section className="rounded-xl bg-white border border-slate-200 p-4">
                    <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Price Breakdown</h2>
                    <div className="space-y-1.5 text-sm">
                        {order.cylinder_price > 0 && (
                            <div className="flex justify-between text-slate-600"><span>Cylinder</span><span>{fmt(order.cylinder_price)}</span></div>
                        )}
                        <div className="flex justify-between text-slate-600">
                            <span>Gas</span><span>{fmt(order.gas_price)}</span>
                        </div>
                        <div className="flex justify-between text-slate-600">
                            <span>Delivery</span><span>{fmt(order.delivery_fee)}</span>
                        </div>
                        {order.addons.map((a, i) => (
                            <div key={i} className="flex justify-between text-slate-600">
                                <span className="truncate mr-2">{a.name}</span><span>{fmt(a.price)}</span>
                            </div>
                        ))}
                        <div className="flex justify-between border-t border-slate-100 pt-2 font-bold text-slate-800">
                            <span>Total</span><span>{fmt(order.total_amount)}</span>
                        </div>
                    </div>
                    <p className="mt-2 text-xs text-slate-500 capitalize">
                        Payment: {order.payment_method.replace('_', ' ')}
                    </p>
                    {order.payment_method === 'mpesa' && mpesa_till && order.status === 'pending' && (
                        <div className="mt-3 flex items-start gap-2 rounded-lg bg-green-50 border border-green-200 p-3 text-xs text-green-800">
                            <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <span>Pay <strong>{fmt(order.total_amount)}</strong> to M-Pesa Till <strong>{mpesa_till}</strong> · Ref: <strong>{order.order_number}</strong></span>
                        </div>
                    )}
                </section>

                {/* Rider */}
                {order.rider && (
                    <section className="rounded-xl bg-white border border-slate-200 p-4 flex items-center gap-3">
                        {order.rider.avatar_url ? (
                            <img src={order.rider.avatar_url} alt={order.rider.name} className="h-12 w-12 rounded-full object-cover shrink-0" />
                        ) : (
                            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 text-lg font-bold">
                                {order.rider.name.charAt(0)}
                            </div>
                        )}
                        <div className="flex-1 min-w-0">
                            <p className="font-semibold text-slate-800">{order.rider.name}</p>
                            {order.rider.avg_rating != null && (
                                <span className="flex items-center gap-0.5 text-xs text-amber-500">
                                    <Star className="h-3 w-3 fill-amber-400 stroke-amber-400" />{order.rider.avg_rating.toFixed(1)}
                                </span>
                            )}
                        </div>
                        <a
                            href={`tel:${order.rider.phone}`}
                            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600 hover:bg-orange-200"
                        >
                            <Phone className="h-5 w-5" />
                        </a>
                    </section>
                )}

                {/* Status history */}
                {order.history.length > 0 && (
                    <section className="rounded-xl bg-white border border-slate-200 p-4">
                        <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">History</h2>
                        <div className="space-y-2">
                            {order.history.map((h, i) => (
                                <div key={i} className="flex items-center gap-3 text-sm">
                                    <div className="h-2 w-2 rounded-full bg-orange-400 shrink-0" />
                                    <span className="flex-1 capitalize text-slate-700">{h.status.replace('_', ' ')}</span>
                                    <span className="text-xs text-slate-400">{h.at}</span>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* Delivery notes */}
                {order.delivery_notes && (
                    <div className="flex items-start gap-2 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                        <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-orange-400" />
                        {order.delivery_notes}
                    </div>
                )}

                {/* Actions */}
                <div className="space-y-3">
                    {order.can_track && (
                        <Link
                            href={`/orders/${order.id}/tracking`}
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-orange-500 py-3.5 text-sm font-semibold text-white shadow-md shadow-orange-500/25 hover:bg-orange-600"
                        >
                            <MapPin className="h-4 w-4" /> Live Track
                        </Link>
                    )}

                    {order.can_rate && (
                        <Link
                            href={`/orders/${order.id}/rate`}
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 py-3.5 text-sm font-semibold text-white hover:bg-amber-600"
                        >
                            <Star className="h-4 w-4" /> Rate Delivery
                        </Link>
                    )}

                    {order.can_cancel && (
                        <>
                            {showConfirm ? (
                                <div className="rounded-xl border border-red-200 bg-red-50 p-4 space-y-3">
                                    <p className="text-sm font-medium text-red-700">Cancel this order?</p>
                                    <div className="flex gap-2">
                                        <button
                                            onClick={() => setShowConfirm(false)}
                                            className="flex-1 rounded-lg border border-slate-200 bg-white py-2 text-sm font-medium text-slate-700"
                                        >
                                            Keep
                                        </button>
                                        <button
                                            onClick={cancel}
                                            disabled={cancelling}
                                            className="flex-1 rounded-lg bg-red-500 py-2 text-sm font-medium text-white hover:bg-red-600 disabled:opacity-50"
                                        >
                                            {cancelling ? 'Cancelling…' : 'Yes, Cancel'}
                                        </button>
                                    </div>
                                </div>
                            ) : (
                                <button
                                    onClick={() => setShowConfirm(true)}
                                    className="w-full rounded-xl border border-red-200 bg-white py-3.5 text-sm font-semibold text-red-600 hover:bg-red-50"
                                >
                                    Cancel Order
                                </button>
                            )}
                        </>
                    )}
                </div>
            </div>
        </CustomerLayout>
    );
}
