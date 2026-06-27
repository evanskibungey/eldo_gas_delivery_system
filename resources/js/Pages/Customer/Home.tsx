import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Flame,
    ShoppingBag,
    Clock,
    ChevronRight,
    AlertTriangle,
    Lightbulb,
    Zap,
    ShieldCheck,
    CreditCard,
    RefreshCcw,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface LastOrder {
    id: number;
    order_type: string;
    size_id: number | null;
    brand_id: number | null;
    status: string;
    payment_status: string;
    size_label: string | null;
    brand_name: string | null;
    total_amount: number;
    created_at: string;
    can_track: boolean;
    can_reorder: boolean;
}

interface Props {
    shopOpen: boolean;
    shopOpensAt: string;
    shopClosesAt: string;
    lastOrder: LastOrder | null;
}

const SAFETY_TIPS = [
    'Never store cylinders indoors or in enclosed spaces.',
    'Check hose connections for leaks using soapy water.',
    'Keep cylinders away from heat sources and direct sunlight.',
    'Always turn off your gas at the cylinder when not in use.',
    'Ensure good ventilation when cooking with LPG.',
];

const ORDER_STATUS_CFG: Record<string, { label: string; color: string }> = {
    pending: { label: 'Pending', color: 'text-slate-500' },
    rider_assigned: { label: 'Rider Assigned', color: 'text-blue-600' },
    picked_up: { label: 'Picked Up', color: 'text-indigo-600' },
    on_the_way: { label: 'On the Way', color: 'text-amber-600' },
    correction_in_progress: { label: 'Issue Being Corrected', color: 'text-rose-600' },
    delivered: { label: 'Delivered', color: 'text-emerald-600' },
    cancelled: { label: 'Cancelled', color: 'text-red-500' },
};

const PAYMENT_STATUS_CFG: Record<string, { label: string; chip: string }> = {
    pending: { label: 'Payment pending', chip: 'border-amber-200 bg-amber-50 text-amber-700' },
    collected: { label: 'Payment received', chip: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    disputed: { label: 'Payment disputed', chip: 'border-red-200 bg-red-50 text-red-600' },
    refunded: { label: 'Refund in progress', chip: 'border-slate-200 bg-slate-50 text-slate-600' },
};

const ACTIVE_ORDER_STATUSES = ['pending', 'rider_assigned', 'picked_up', 'on_the_way', 'correction_in_progress'];

const VALUE_PROPS = [
    { icon: Zap, title: 'Fast Delivery', desc: 'Gas at your door in about 25 minutes', color: 'text-orange-500', bg: 'bg-orange-50' },
    { icon: ShieldCheck, title: 'Safety First', desc: 'Certified cylinders and trained riders', color: 'text-emerald-500', bg: 'bg-emerald-50' },
    { icon: CreditCard, title: 'Easy Payment', desc: 'M-Pesa or cash on delivery', color: 'text-blue-500', bg: 'bg-blue-50' },
];

function useCountdown(timeStr: string): string {
    const [display, setDisplay] = useState('');

    useEffect(() => {
        function secondsUntil(): number {
            const match = timeStr.match(/(\d+):(\d+)\s*(AM|PM)/i);
            if (!match) return 0;

            let hours = parseInt(match[1], 10);
            const minutes = parseInt(match[2], 10);
            const ampm = match[3].toUpperCase();

            if (ampm === 'PM' && hours !== 12) hours += 12;
            if (ampm === 'AM' && hours === 12) hours = 0;

            const now = new Date();
            const opening = new Date(now);
            opening.setHours(hours, minutes, 0, 0);
            if (opening <= now) opening.setDate(opening.getDate() + 1);

            return Math.max(0, Math.floor((opening.getTime() - now.getTime()) / 1000));
        }

        function formatCountdown(seconds: number): string {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const remainingSeconds = seconds % 60;

            if (hours > 0) return `${hours}h ${minutes}m`;
            return `${minutes}m ${String(remainingSeconds).padStart(2, '0')}s`;
        }

        setDisplay(formatCountdown(secondsUntil()));
        const timer = setInterval(() => {
            const seconds = secondsUntil();
            setDisplay(formatCountdown(seconds));
            if (seconds <= 0) clearInterval(timer);
        }, 1000);

        return () => clearInterval(timer);
    }, [timeStr]);

    return display;
}

export default function CustomerHome({ shopOpen, shopOpensAt, shopClosesAt, lastOrder }: Props) {
    const { auth } = usePage().props as { auth?: { customer?: { name?: string } } };
    const customer = auth?.customer;
    const tipIndex = new Date().getDate() % SAFETY_TIPS.length;
    const firstName = customer?.name?.split(' ')[0] ?? 'there';
    const countdown = useCountdown(shopOpensAt);

    const activeOrder = lastOrder ? ACTIVE_ORDER_STATUSES.includes(lastOrder.status) : false;
    const paymentCfg = lastOrder ? PAYMENT_STATUS_CFG[lastOrder.payment_status] ?? PAYMENT_STATUS_CFG.pending : null;

    function reorder(): void {
        if (!lastOrder?.size_id) return;

        router.visit('/order/new', {
            data: {
                prefill_order_type: lastOrder.order_type,
                prefill_size_id: lastOrder.size_id,
                prefill_brand_id: lastOrder.brand_id ?? undefined,
            },
        });
    }

    return (
        <CustomerLayout>
            <div className="mx-auto max-w-lg space-y-5 px-4 py-5 md:max-w-4xl">
                <div className="space-y-4 md:grid md:grid-cols-2 md:items-start md:gap-8 md:space-y-0">
                    <div className="space-y-4">
                        <div>
                            <h1 className="text-xl font-bold text-slate-900 md:text-2xl">Hello, {firstName}</h1>
                            <p className="text-sm text-slate-500">Gas delivered to your door.</p>
                        </div>

                        <div className={cn('flex items-center gap-3 rounded-xl border px-4 py-3', shopOpen ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50')}>
                            <span className={cn('h-3 w-3 shrink-0 rounded-full', shopOpen ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400')} />
                            <div className="min-w-0 flex-1">
                                <p className={cn('text-sm font-semibold', shopOpen ? 'text-emerald-700' : 'text-slate-600')}>
                                    {shopOpen ? 'We are open' : 'Shop is closed'}
                                </p>
                                <p className="text-xs text-slate-500">
                                    {shopOpen
                                        ? `Closes at ${shopClosesAt} · Delivery in about 25 mins`
                                        : countdown
                                            ? `Opens at ${shopOpensAt} · in ${countdown}`
                                            : `Opens at ${shopOpensAt}`}
                                </p>
                            </div>
                            {shopOpen && <Clock className="h-4 w-4 shrink-0 text-emerald-400" />}
                        </div>

                        <Button
                            asChild={shopOpen}
                            disabled={!shopOpen}
                            className={cn(
                                'h-14 w-full gap-2 text-base font-semibold shadow-md',
                                shopOpen
                                    ? 'bg-orange-500 shadow-orange-500/30 hover:bg-orange-600'
                                    : 'cursor-not-allowed bg-slate-200 text-slate-400 shadow-none',
                            )}
                        >
                            {shopOpen ? (
                                <Link href="/order/new" className="flex items-center justify-center gap-2">
                                    <Flame className="h-5 w-5" />
                                    Order Gas Now
                                </Link>
                            ) : (
                                <span className="flex items-center justify-center gap-2">
                                    <AlertTriangle className="h-5 w-5" />
                                    Unavailable - Opens in {countdown || shopOpensAt}
                                </span>
                            )}
                        </Button>
                    </div>

                    <div>
                        {lastOrder ? (
                            <div className="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 to-amber-500" />
                                <div className="flex items-center justify-between border-b border-slate-50 px-4 py-3">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">Last Order</p>
                                    <Link href={`/orders/${lastOrder.id}`} className="flex items-center gap-0.5 text-xs font-medium text-orange-500 hover:text-orange-600">
                                        View <ChevronRight className="h-3.5 w-3.5" />
                                    </Link>
                                </div>
                                <div className="flex items-center gap-3 px-4 py-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orange-50">
                                        <ShoppingBag className="h-5 w-5 text-orange-400" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-slate-800">
                                            {lastOrder.brand_name ?? 'Gas'} · {lastOrder.size_label ?? '-'}
                                        </p>
                                        <div className="mt-0.5 flex flex-wrap items-center gap-2">
                                            <span className={cn('text-xs font-semibold', ORDER_STATUS_CFG[lastOrder.status]?.color ?? 'text-slate-500')}>
                                                {ORDER_STATUS_CFG[lastOrder.status]?.label ?? lastOrder.status}
                                            </span>
                                            {paymentCfg && (
                                                <span className={cn('rounded-full border px-2 py-0.5 text-[10px] font-semibold', paymentCfg.chip)}>
                                                    {paymentCfg.label}
                                                </span>
                                            )}
                                            <span className="text-[10px] text-slate-400">· {lastOrder.created_at}</span>
                                        </div>
                                    </div>
                                    <p className="shrink-0 text-sm font-bold tabular-nums text-slate-800">KES {lastOrder.total_amount.toLocaleString()}</p>
                                </div>
                                <div className="flex items-center justify-between gap-2 border-t border-slate-50 px-4 py-2">
                                    {lastOrder.can_track ? (
                                        <Link href={`/orders/${lastOrder.id}/tracking`} className="flex items-center gap-1 text-xs font-medium text-orange-500 hover:text-orange-600">
                                            Track your delivery <ChevronRight className="h-3.5 w-3.5" />
                                        </Link>
                                    ) : activeOrder ? (
                                        <Link href={`/orders/${lastOrder.id}`} className="flex items-center gap-1 text-xs font-medium text-orange-500 hover:text-orange-600">
                                            View order status <ChevronRight className="h-3.5 w-3.5" />
                                        </Link>
                                    ) : (
                                        <span />
                                    )}

                                    {lastOrder.size_id && lastOrder.can_reorder && shopOpen && (
                                        <button
                                            type="button"
                                            onClick={reorder}
                                            className="flex items-center gap-1 rounded-lg border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600 transition-colors hover:bg-orange-100"
                                        >
                                            <RefreshCcw className="h-3 w-3" /> Reorder
                                        </button>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div className="hidden h-full min-h-[160px] flex-col items-center justify-center rounded-2xl border border-dashed border-orange-200 bg-orange-50/60 p-10 text-center md:flex">
                                <Flame className="mb-2 h-8 w-8 text-orange-300" />
                                <p className="text-sm font-medium text-orange-400">No orders yet</p>
                                <p className="mt-1 text-xs text-orange-300">Place your first order above</p>
                            </div>
                        )}
                    </div>
                </div>

                <div>
                    <div className="scrollbar-none flex snap-x gap-3 overflow-x-auto pb-1 md:hidden">
                        {VALUE_PROPS.map((card) => (
                            <div key={card.title} className="w-44 shrink-0 snap-start rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div className={cn('mb-2.5 flex h-9 w-9 items-center justify-center rounded-full', card.bg)}>
                                    <card.icon className={cn('h-4 w-4', card.color)} />
                                </div>
                                <p className="text-sm font-semibold text-slate-800">{card.title}</p>
                                <p className="mt-0.5 text-xs text-slate-500">{card.desc}</p>
                            </div>
                        ))}
                    </div>

                    <div className="hidden gap-4 md:grid md:grid-cols-3">
                        {VALUE_PROPS.map((card) => (
                            <div key={card.title} className="rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
                                <div className={cn('mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full', card.bg)}>
                                    <card.icon className={cn('h-5 w-5', card.color)} />
                                </div>
                                <p className="text-sm font-semibold text-slate-800">{card.title}</p>
                                <p className="mt-1 text-xs text-slate-500">{card.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex items-start gap-3 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3">
                    <Lightbulb className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                    <div>
                        <p className="text-xs font-semibold text-amber-700">Safety Tip</p>
                        <p className="mt-0.5 text-xs text-amber-600">{SAFETY_TIPS[tipIndex]}</p>
                    </div>
                </div>
            </div>
        </CustomerLayout>
    );
}