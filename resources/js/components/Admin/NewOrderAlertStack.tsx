import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Package, RefreshCw, MapPin, Phone, X, BellRing } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { NewOrderPayload } from './AdminRealtime';

export interface NewOrderAlert {
    order:      NewOrderPayload;
    receivedAt: number;
}

/** Cash orders need someone to plan for collection, so they linger longer. */
function dismissAfter(order: NewOrderPayload): number {
    return order.payment_method === 'cash' ? 30_000 : 20_000;
}

interface Props {
    alerts:    NewOrderAlert[];
    onDismiss: (orderId: number) => void;
}

/**
 * New-order banners, rendered above every admin page.
 *
 * Lives in AdminRealtimeProvider rather than on the Orders page so an admin
 * working in Stock, Riders or Reports still finds out an order came in.
 */
export default function NewOrderAlertStack({ alerts, onDismiss }: Props) {
    if (alerts.length === 0) return null;

    return (
        <div
            // `pointer-events-none` on the column, re-enabled per card, so the
            // gaps between cards do not block the page underneath.
            className="pointer-events-none fixed inset-x-0 top-0 z-[100] flex flex-col items-center gap-2 px-3 pt-3 sm:px-4 sm:pt-4"
            role="region"
            aria-label="New order alerts"
        >
            {alerts.map(alert => (
                <AlertCard
                    key={alert.order.id}
                    alert={alert}
                    onDismiss={() => onDismiss(alert.order.id)}
                />
            ))}
        </div>
    );
}

function AlertCard({ alert, onDismiss }: { alert: NewOrderAlert; onDismiss: () => void }) {
    const { order } = alert;
    const [entered, setEntered] = useState(false);
    const [paused, setPaused]   = useState(false);

    // Drive the slide-in on the frame after mount so the transition actually runs.
    useEffect(() => {
        const raf = requestAnimationFrame(() => setEntered(true));
        return () => cancelAnimationFrame(raf);
    }, []);

    useEffect(() => {
        if (paused) return;
        const timer = window.setTimeout(onDismiss, dismissAfter(order));
        return () => window.clearTimeout(timer);
    }, [paused, order, onDismiss]);

    const isSwap = order.order_type === 'swap';

    return (
        <div
            // assertive: a new order is the one thing in this panel that needs
            // to interrupt whatever the admin is reading.
            role="alert"
            aria-live="assertive"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
            className={cn(
                'pointer-events-auto w-full max-w-lg overflow-hidden rounded-2xl border border-orange-300',
                'bg-white shadow-xl shadow-orange-900/10 ring-1 ring-black/5',
                'transition-all duration-300 ease-out',
                entered ? 'translate-y-0 opacity-100' : '-translate-y-3 opacity-0',
            )}
        >
            <div className="h-1 w-full bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />

            <div className="flex items-start gap-3 p-3.5 sm:p-4">
                <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-50">
                    <BellRing className="h-5 w-5 text-orange-500" />
                    <span className="absolute -right-0.5 -top-0.5 flex h-2.5 w-2.5">
                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-orange-400 opacity-75" />
                        <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-orange-500" />
                    </span>
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <p className="text-sm font-bold text-slate-900">New order</p>
                        <p className="font-mono text-sm font-semibold text-orange-600">{order.order_number}</p>
                        <span className="ml-auto text-sm font-bold tabular-nums text-slate-900">
                            KES {order.total_amount.toLocaleString()}
                        </span>
                    </div>

                    <p className="mt-1 truncate text-sm font-medium text-slate-800">
                        {order.customer_name ?? 'Customer'}
                        {order.customer_phone && (
                            <span className="ml-1.5 inline-flex items-center gap-1 text-xs font-normal text-slate-500">
                                <Phone className="h-3 w-3 shrink-0" />
                                {order.customer_phone}
                            </span>
                        )}
                    </p>

                    <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <span className={cn(
                            'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-2xs font-semibold uppercase',
                            isSwap ? 'bg-orange-50 text-orange-600' : 'bg-blue-50 text-blue-600',
                        )}>
                            {isSwap
                                ? <RefreshCw className="h-2.5 w-2.5" />
                                : <Package className="h-2.5 w-2.5" />}
                            {isSwap ? 'Swap' : 'New'}
                        </span>

                        {order.size_name && (
                            <span className="rounded bg-slate-100 px-1.5 py-0.5 text-2xs font-semibold text-slate-700">
                                {order.size_name}
                            </span>
                        )}

                        {order.brand_name && (
                            <span className="rounded bg-slate-100 px-1.5 py-0.5 text-2xs font-medium text-slate-600">
                                {order.brand_name}
                            </span>
                        )}

                        <span className={cn(
                            'rounded px-1.5 py-0.5 text-2xs font-bold uppercase',
                            order.payment_method === 'mpesa'
                                ? 'bg-emerald-50 text-emerald-600'
                                : 'bg-slate-100 text-slate-600',
                        )}>
                            {order.payment_method}
                        </span>
                    </div>

                    {order.address && (
                        <p className="mt-1.5 flex items-start gap-1 text-xs text-slate-500">
                            <MapPin className="mt-0.5 h-3 w-3 shrink-0" />
                            <span className="line-clamp-2">{order.address}</span>
                        </p>
                    )}

                    <div className="mt-2.5 flex items-center gap-2">
                        <Link
                            href={`/admin/orders/${order.id}`}
                            onClick={onDismiss}
                            className="inline-flex items-center gap-1 rounded-lg bg-orange-500 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-orange-600"
                        >
                            Assign rider →
                        </Link>
                        <Link
                            href="/admin/orders?status=pending"
                            onClick={onDismiss}
                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50"
                        >
                            View queue
                        </Link>
                    </div>
                </div>

                <button
                    onClick={onDismiss}
                    aria-label={`Dismiss alert for order ${order.order_number}`}
                    className="-m-1.5 shrink-0 p-1.5 text-slate-400 transition-colors hover:text-slate-700"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}
