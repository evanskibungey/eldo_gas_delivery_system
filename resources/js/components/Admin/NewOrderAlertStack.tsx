import { Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { Package, RefreshCw, MapPin, Phone, X, BellRing } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { NewOrderPayload } from './AdminRealtime';

export interface NewOrderAlert {
    order:      NewOrderPayload;
    receivedAt: number;
}

/** Cards rendered in full; the rest are summarised as a count. */
const VISIBLE = 3;

interface Props {
    alerts:       NewOrderAlert[];
    onDismiss:    (orderId: number) => void;
    onDismissAll: () => void;
}

/**
 * New-order alarm, centred over every admin page.
 *
 * This is deliberately a modal and not a toast. The alarm rings until it is
 * acknowledged, so it must be impossible to leave ringing without noticing why:
 * nothing auto-dismisses, and the only ways out are acting on the order or
 * explicitly dismissing it.
 */
export default function NewOrderAlertStack({ alerts, onDismiss, onDismissAll }: Props) {
    const primaryAction = useRef<HTMLAnchorElement>(null);
    const open = alerts.length > 0;

    // Escape acknowledges everything — the fastest way to silence the alarm for
    // someone already looking at the screen.
    useEffect(() => {
        if (!open) return;

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') onDismissAll();
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [open, onDismissAll]);

    // Focus the primary action so the alarm is keyboard-actionable, and so the
    // browser treats the next keypress as an interaction with this dialog.
    useEffect(() => {
        if (open) primaryAction.current?.focus();
    }, [open, alerts[0]?.order.id]);

    // The page behind must not scroll under the modal.
    useEffect(() => {
        if (!open) return;

        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, [open]);

    if (!open) return null;

    const visible = alerts.slice(0, VISIBLE);
    const hidden = alerts.length - visible.length;

    return (
        <div
            role="alertdialog"
            aria-modal="true"
            aria-label={`${alerts.length} new ${alerts.length === 1 ? 'order' : 'orders'}`}
            className="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto p-4"
        >
            {/* Backdrop. Deliberately not click-to-dismiss: an accidental click
                must not silence an order nobody has actually looked at. */}
            <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-[2px]" aria-hidden="true" />

            <div className="relative flex w-full max-w-lg flex-col gap-3">
                {visible.map((alert, index) => (
                    <AlertCard
                        key={alert.order.id}
                        alert={alert}
                        primaryRef={index === 0 ? primaryAction : undefined}
                        onDismiss={() => onDismiss(alert.order.id)}
                    />
                ))}

                {hidden > 0 && (
                    <div className="flex items-center justify-between gap-3 rounded-xl border border-white/20 bg-slate-900/70 px-4 py-2.5 text-sm text-white">
                        <span className="font-medium">
                            and {hidden} more new {hidden === 1 ? 'order' : 'orders'}
                        </span>
                        <Link
                            href="/admin/orders?status=pending"
                            onClick={onDismissAll}
                            className="shrink-0 rounded-lg bg-white/15 px-3 py-1 text-xs font-semibold transition-colors hover:bg-white/25"
                        >
                            View queue
                        </Link>
                    </div>
                )}

                {alerts.length > 1 && (
                    <button
                        onClick={onDismissAll}
                        className="self-center rounded-lg px-3 py-1.5 text-xs font-semibold text-white/80 transition-colors hover:bg-white/10 hover:text-white"
                    >
                        Dismiss all and silence
                    </button>
                )}
            </div>
        </div>
    );
}

function AlertCard({ alert, onDismiss, primaryRef }: {
    alert: NewOrderAlert;
    onDismiss: () => void;
    primaryRef?: React.Ref<HTMLAnchorElement>;
}) {
    const { order } = alert;
    const isSwap = order.order_type === 'swap';

    return (
        <div className="overflow-hidden rounded-2xl border border-orange-300 bg-white shadow-2xl shadow-black/30 ring-1 ring-black/5">
            <div className="h-1 w-full bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />

            <div className="flex items-start gap-3 p-4 sm:p-5">
                <div className="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-50">
                    <BellRing className="h-5 w-5 text-orange-500" />
                    <span className="absolute -right-0.5 -top-0.5 flex h-2.5 w-2.5">
                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-orange-400 opacity-75" />
                        <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-orange-500" />
                    </span>
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <p className="text-base font-bold text-slate-900">New order</p>
                        <p className="font-mono text-sm font-semibold text-orange-600">{order.order_number}</p>
                        <span className="ml-auto text-base font-bold tabular-nums text-slate-900">
                            KES {order.total_amount.toLocaleString()}
                        </span>
                    </div>

                    <p className="mt-1.5 truncate text-sm font-medium text-slate-800">
                        {order.customer_name ?? 'Customer'}
                        {order.customer_phone && (
                            <span className="ml-1.5 inline-flex items-center gap-1 text-xs font-normal text-slate-500">
                                <Phone className="h-3 w-3 shrink-0" />
                                {order.customer_phone}
                            </span>
                        )}
                    </p>

                    <div className="mt-2 flex flex-wrap items-center gap-1.5">
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
                        <p className="mt-2 flex items-start gap-1 text-xs text-slate-500">
                            <MapPin className="mt-0.5 h-3 w-3 shrink-0" />
                            <span className="line-clamp-2">{order.address}</span>
                        </p>
                    )}

                    <div className="mt-3.5 flex items-center gap-2">
                        <Link
                            ref={primaryRef}
                            href={`/admin/orders/${order.id}`}
                            onClick={onDismiss}
                            className="inline-flex items-center gap-1 rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2"
                        >
                            Assign rider →
                        </Link>
                        <button
                            onClick={onDismiss}
                            className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50"
                        >
                            Dismiss
                        </button>
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
