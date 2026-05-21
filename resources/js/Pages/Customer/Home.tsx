import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Flame, ShoppingBag, Clock, ChevronRight, AlertTriangle, Lightbulb,
    Zap, ShieldCheck, CreditCard, RefreshCcw,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface LastOrder {
    id:           number;
    order_type:   string;
    size_id:      number | null;
    brand_id:     number | null;
    status:       string;
    size_label:   string | null;
    brand_name:   string | null;
    total_amount: number;
    created_at:   string;
}

interface Props {
    shopOpen:     boolean;
    shopOpensAt:  string;
    shopClosesAt: string;
    lastOrder:    LastOrder | null;
}

const SAFETY_TIPS = [
    'Never store cylinders indoors or in enclosed spaces.',
    'Check hose connections for leaks using soapy water.',
    'Keep cylinders away from heat sources and direct sunlight.',
    'Always turn off your gas at the cylinder when not in use.',
    'Ensure good ventilation when cooking with LPG.',
];

const ORDER_STATUS_CFG: Record<string, { label: string; color: string }> = {
    pending:        { label: 'Pending',        color: 'text-slate-500'   },
    rider_assigned: { label: 'Rider Assigned', color: 'text-blue-600'   },
    picked_up:      { label: 'On the Way',     color: 'text-amber-600'  },
    delivered:      { label: 'Delivered',      color: 'text-emerald-600' },
    cancelled:      { label: 'Cancelled',      color: 'text-red-500'    },
};

const VALUE_PROPS = [
    { icon: Zap,         title: 'Fast Delivery', desc: 'Gas at your door in ~25 minutes', color: 'text-orange-500', bg: 'bg-orange-50' },
    { icon: ShieldCheck, title: 'Safety First',  desc: 'Certified cylinders, trained riders', color: 'text-emerald-500', bg: 'bg-emerald-50' },
    { icon: CreditCard,  title: 'Easy Payment',  desc: 'M-Pesa or cash on delivery', color: 'text-blue-500', bg: 'bg-blue-50' },
];

// ── Countdown hook: parses "7:00 AM" / "9:30 PM" → seconds until that time ──
function useCountdown(timeStr: string): string {
    const [display, setDisplay] = useState('');

    useEffect(() => {
        function secondsUntil(): number {
            const match = timeStr.match(/(\d+):(\d+)\s*(AM|PM)/i);
            if (!match) return 0;
            let h = parseInt(match[1]);
            const m = parseInt(match[2]);
            const ampm = match[3].toUpperCase();
            if (ampm === 'PM' && h !== 12) h += 12;
            if (ampm === 'AM' && h === 12) h = 0;
            const now  = new Date();
            const open = new Date(now);
            open.setHours(h, m, 0, 0);
            if (open <= now) open.setDate(open.getDate() + 1);
            return Math.max(0, Math.floor((open.getTime() - now.getTime()) / 1000));
        }

        function fmt(secs: number): string {
            const hh = Math.floor(secs / 3600);
            const mm = Math.floor((secs % 3600) / 60);
            const ss = secs % 60;
            if (hh > 0) return `${hh}h ${mm}m`;
            return `${mm}m ${String(ss).padStart(2, '0')}s`;
        }

        setDisplay(fmt(secondsUntil()));
        const t = setInterval(() => {
            const s = secondsUntil();
            setDisplay(fmt(s));
            if (s <= 0) clearInterval(t);
        }, 1000);
        return () => clearInterval(t);
    }, [timeStr]);

    return display;
}

export default function CustomerHome({ shopOpen, shopOpensAt, shopClosesAt, lastOrder }: Props) {
    const { auth }  = usePage().props as any;
    const customer  = auth?.customer;
    const tipIndex  = new Date().getDate() % SAFETY_TIPS.length;
    const firstName = customer?.name?.split(' ')[0] ?? 'there';
    const countdown = useCountdown(shopOpensAt);

    const activeOrder = lastOrder && ['pending', 'rider_assigned', 'picked_up'].includes(lastOrder.status);

    function reorder() {
        if (!lastOrder?.size_id) return;
        router.visit('/order/new', {
            data: {
                prefill_order_type: lastOrder.order_type,
                prefill_size_id:    lastOrder.size_id,
                prefill_brand_id:   lastOrder.brand_id ?? undefined,
            },
        });
    }

    return (
        <CustomerLayout>
            <div className="mx-auto max-w-lg md:max-w-4xl px-4 py-5 space-y-5">

                {/* ── Hero grid ───────────────────────────────────────────── */}
                <div className="md:grid md:grid-cols-2 md:gap-8 md:items-start space-y-4 md:space-y-0">

                    {/* Left: greeting + shop status + CTA */}
                    <div className="space-y-4">
                        <div>
                            <h1 className="text-xl md:text-2xl font-bold text-slate-900">
                                Hello, {firstName} 👋
                            </h1>
                            <p className="text-sm text-slate-500">Gas delivered to your door.</p>
                        </div>

                        {/* Shop status banner */}
                        <div className={cn(
                            'flex items-center gap-3 rounded-xl border px-4 py-3',
                            shopOpen ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50',
                        )}>
                            <span className={cn(
                                'h-3 w-3 rounded-full shrink-0',
                                shopOpen ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400',
                            )} />
                            <div className="flex-1 min-w-0">
                                <p className={cn('text-sm font-semibold', shopOpen ? 'text-emerald-700' : 'text-slate-600')}>
                                    {shopOpen ? 'We are Open' : 'Shop is Closed'}
                                </p>
                                <p className="text-xs text-slate-500">
                                    {shopOpen
                                        ? `Closes at ${shopClosesAt} · Delivery in ~25 mins`
                                        : countdown
                                            ? `Opens at ${shopOpensAt} · in ${countdown}`
                                            : `Opens at ${shopOpensAt}`}
                                </p>
                            </div>
                            {shopOpen && <Clock className="h-4 w-4 text-emerald-400 shrink-0" />}
                        </div>

                        {/* Order CTA */}
                        <Button
                            asChild={shopOpen}
                            disabled={!shopOpen}
                            className={cn(
                                'w-full h-14 text-base font-semibold gap-2 shadow-md',
                                shopOpen
                                    ? 'bg-orange-500 hover:bg-orange-600 shadow-orange-500/30'
                                    : 'bg-slate-200 text-slate-400 cursor-not-allowed shadow-none',
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
                                    Unavailable — Opens in {countdown || shopOpensAt}
                                </span>
                            )}
                        </Button>
                    </div>

                    {/* Right: last order card */}
                    <div>
                        {lastOrder ? (
                            <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 to-amber-500" />

                                {/* Header */}
                                <div className="flex items-center justify-between px-4 py-3 border-b border-slate-50">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">Last Order</p>
                                    <Link
                                        href={`/orders/${lastOrder.id}`}
                                        className="flex items-center gap-0.5 text-xs font-medium text-orange-500 hover:text-orange-600"
                                    >
                                        View <ChevronRight className="h-3.5 w-3.5" />
                                    </Link>
                                </div>

                                {/* Body */}
                                <div className="flex items-center gap-3 px-4 py-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-orange-50 shrink-0">
                                        <ShoppingBag className="h-5 w-5 text-orange-400" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-slate-800">
                                            {lastOrder.brand_name ?? 'Gas'} · {lastOrder.size_label ?? '—'}
                                        </p>
                                        <div className="flex items-center gap-2 mt-0.5">
                                            <span className={cn('text-xs font-semibold', ORDER_STATUS_CFG[lastOrder.status]?.color ?? 'text-slate-500')}>
                                                {ORDER_STATUS_CFG[lastOrder.status]?.label ?? lastOrder.status}
                                            </span>
                                            <span className="text-[10px] text-slate-400">· {lastOrder.created_at}</span>
                                        </div>
                                    </div>
                                    <p className="text-sm font-bold text-slate-800 shrink-0 tabular-nums">
                                        KES {lastOrder.total_amount.toLocaleString()}
                                    </p>
                                </div>

                                {/* Footer actions */}
                                <div className="border-t border-slate-50 px-4 py-2 flex items-center justify-between gap-2">
                                    {activeOrder ? (
                                        <Link
                                            href={`/orders/${lastOrder.id}/tracking`}
                                            className="flex items-center gap-1 text-xs font-medium text-orange-500 hover:text-orange-600"
                                        >
                                            Track your delivery <ChevronRight className="h-3.5 w-3.5" />
                                        </Link>
                                    ) : (
                                        <span />
                                    )}

                                    {/* Reorder button */}
                                    {lastOrder.size_id && shopOpen && !activeOrder && (
                                        <button
                                            type="button"
                                            onClick={reorder}
                                            className="flex items-center gap-1 rounded-lg bg-orange-50 border border-orange-200 px-3 py-1 text-xs font-semibold text-orange-600 hover:bg-orange-100 transition-colors"
                                        >
                                            <RefreshCcw className="h-3 w-3" /> Reorder
                                        </button>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div className="hidden md:flex flex-col items-center justify-center rounded-2xl border border-dashed border-orange-200 bg-orange-50/60 p-10 text-center h-full min-h-[160px]">
                                <Flame className="h-8 w-8 text-orange-300 mb-2" />
                                <p className="text-sm font-medium text-orange-400">No orders yet</p>
                                <p className="text-xs text-orange-300 mt-1">Place your first order above</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* ── Value props — mobile: horizontal scroll, desktop: grid ─ */}
                <div>
                    {/* Mobile scroll */}
                    <div className="flex gap-3 overflow-x-auto pb-1 md:hidden scrollbar-none snap-x">
                        {VALUE_PROPS.map(card => (
                            <div key={card.title} className="snap-start shrink-0 w-44 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div className={cn('flex h-9 w-9 items-center justify-center rounded-full mb-2.5', card.bg)}>
                                    <card.icon className={cn('h-4 w-4', card.color)} />
                                </div>
                                <p className="font-semibold text-slate-800 text-sm">{card.title}</p>
                                <p className="text-xs text-slate-500 mt-0.5">{card.desc}</p>
                            </div>
                        ))}
                    </div>

                    {/* Desktop grid */}
                    <div className="hidden md:grid md:grid-cols-3 gap-4">
                        {VALUE_PROPS.map(card => (
                            <div key={card.title} className="rounded-xl border border-slate-200 bg-white p-5 text-center shadow-sm">
                                <div className={cn('flex h-10 w-10 items-center justify-center rounded-full mx-auto mb-3', card.bg)}>
                                    <card.icon className={cn('h-5 w-5', card.color)} />
                                </div>
                                <p className="font-semibold text-slate-800 text-sm">{card.title}</p>
                                <p className="text-xs text-slate-500 mt-1">{card.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>

                {/* ── Safety tip ──────────────────────────────────────────── */}
                <div className="flex items-start gap-3 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3">
                    <Lightbulb className="h-4 w-4 text-amber-500 shrink-0 mt-0.5" />
                    <div>
                        <p className="text-xs font-semibold text-amber-700">Safety Tip</p>
                        <p className="text-xs text-amber-600 mt-0.5">{SAFETY_TIPS[tipIndex]}</p>
                    </div>
                </div>

            </div>
        </CustomerLayout>
    );
}
