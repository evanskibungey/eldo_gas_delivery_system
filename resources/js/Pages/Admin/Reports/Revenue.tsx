import AdminLayout from '@/Layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    TrendingUp, ShoppingBag, CreditCard, BarChart3,
    Download, RefreshCw,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

// â”€â”€ Types â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

interface Summary {
    total_revenue:   number;
    total_orders:    number;
    cash_revenue:    number;
    mpesa_revenue:   number;
    avg_order_value: number;
}

interface DayRow {
    date:    string;
    revenue: number;
    orders:  number;
}

interface SizeRow {
    size_name: string;
    revenue:   number;
    orders:    number;
}

interface TypeRow {
    type:    string;
    revenue: number;
    orders:  number;
}

interface Props {
    filters:      { from: string; to: string };
    summary:      Summary;
    dailyRevenue: DayRow[];
    bySize:       SizeRow[];
    byType:       TypeRow[];
}

// â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const fmt = (n: number) => `KES ${n.toLocaleString()}`;

// â”€â”€ Sub-components â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function MetricCard({
    icon: Icon, label, value, sub, color = 'orange',
}: {
    icon: typeof TrendingUp;
    label: string;
    value: string;
    sub?: string;
    color?: 'orange' | 'emerald' | 'blue' | 'violet';
}) {
    const colors = {
        orange:  'bg-orange-50 text-orange-500',
        emerald: 'bg-emerald-50 text-emerald-600',
        blue:    'bg-blue-50 text-blue-600',
        violet:  'bg-violet-50 text-violet-600',
    };
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className={cn('mb-3 flex h-9 w-9 items-center justify-center rounded-lg', colors[color])}>
                <Icon className="h-4.5 w-4.5" />
            </div>
            <p className="text-2xl font-black text-slate-900 tabular-nums">{value}</p>
            <p className="mt-0.5 text-xs font-medium text-slate-500">{label}</p>
            {sub && <p className="mt-1 text-[10px] text-slate-400">{sub}</p>}
        </div>
    );
}

function MiniBar({
    value, max, label, amount,
}: { value: number; max: number; label: string; amount: number }) {
    const pct = max > 0 ? (value / max) * 100 : 0;
    return (
        <div className="flex items-center gap-3">
            <p className="w-20 shrink-0 text-xs text-slate-600 truncate">{label}</p>
            <div className="flex-1 h-2 rounded-full bg-slate-100">
                <div
                    className="h-2 rounded-full bg-gradient-to-r from-orange-400 to-amber-500 transition-all"
                    style={{ width: `${pct}%` }}
                />
            </div>
            <p className="w-28 shrink-0 text-right text-xs font-semibold text-slate-800 tabular-nums">
                {fmt(amount)}
            </p>
        </div>
    );
}

// â”€â”€ Page â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

export default function RevenueReport({ filters, summary, dailyRevenue, bySize, byType }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo]     = useState(filters.to);

    function applyFilters() {
        router.get('/admin/reports/revenue', { from, to }, { preserveState: true });
    }

    const maxDailyRevenue = Math.max(...dailyRevenue.map(d => d.revenue), 1);
    const maxSizeRevenue  = Math.max(...bySize.map(s => s.revenue), 1);

    return (
        <AdminLayout title="Revenue Report" subtitle="Financial summary for delivered orders">

            {/* Date filter */}
            <div className="mb-6 flex flex-wrap items-end gap-3">
                <div>
                    <label className="block text-xs font-medium text-slate-500 mb-1">From</label>
                    <input
                        type="date"
                        value={from}
                        max={to}
                        onChange={e => setFrom(e.target.value)}
                        className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-slate-500 mb-1">To</label>
                    <input
                        type="date"
                        value={to}
                        min={from}
                        onChange={e => setTo(e.target.value)}
                        className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20"
                    />
                </div>
                <Button onClick={applyFilters} className="h-9 bg-orange-500 hover:bg-orange-600 text-xs gap-1.5 shadow-sm shadow-orange-500/20">
                    <RefreshCw className="h-3.5 w-3.5" /> Apply
                </Button>
                <div className="ml-auto">
                    <a
                        href={`/admin/reports/revenue/export?from=${from}&to=${to}`}
                        className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                    >
                        <Download className="h-3.5 w-3.5" /> Export CSV
                    </a>
                </div>
            </div>

            {/* Summary metrics */}
            <div className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
                <MetricCard icon={TrendingUp}  label="Total Revenue"    value={fmt(summary.total_revenue)}    color="orange" />
                <MetricCard icon={ShoppingBag} label="Delivered Orders" value={summary.total_orders.toString()} color="blue" />
                <MetricCard icon={CreditCard}  label="Cash Revenue"     value={fmt(summary.cash_revenue)}     color="emerald" />
                <MetricCard icon={CreditCard}  label="M-Pesa Revenue"   value={fmt(summary.mpesa_revenue)}    color="violet" />
                <MetricCard icon={BarChart3}   label="Avg Order Value"  value={fmt(summary.avg_order_value)}  color="orange" />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">

                {/* Daily revenue chart (horizontal bars) */}
                <div className="lg:col-span-2">
                    <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 to-amber-500" />
                        <div className="px-5 py-4 border-b border-slate-100">
                            <h2 className="text-sm font-bold text-slate-900">Daily Revenue</h2>
                            <p className="text-xs text-slate-400">{filters.from} → {filters.to}</p>
                        </div>
                        <div className="px-5 py-4 space-y-3 max-h-80 overflow-y-auto">
                            {dailyRevenue.length === 0 ? (
                                <p className="py-10 text-center text-sm text-slate-400">No revenue data for this period.</p>
                            ) : (
                                dailyRevenue.map(d => (
                                    <div key={d.date} className="flex items-center gap-3">
                                        <p className="w-14 shrink-0 text-xs font-medium text-slate-500">{d.date}</p>
                                        <div className="flex-1 h-5 rounded bg-slate-100 overflow-hidden">
                                            <div
                                                className="h-5 rounded bg-gradient-to-r from-orange-400 to-amber-500 flex items-center justify-end pr-2 transition-all"
                                                style={{ width: `${Math.max(2, (d.revenue / maxDailyRevenue) * 100)}%` }}
                                            >
                                                {d.revenue / maxDailyRevenue > 0.3 && (
                                                    <span className="text-[10px] font-bold text-white">{fmt(d.revenue)}</span>
                                                )}
                                            </div>
                                        </div>
                                        <p className="w-8 shrink-0 text-right text-[10px] font-medium text-slate-400 tabular-nums">{d.orders}</p>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                {/* Right column */}
                <div className="space-y-4">

                    {/* By cylinder size */}
                    <div className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 to-amber-500" />
                        <div className="px-4 py-3 border-b border-slate-100">
                            <h2 className="text-sm font-bold text-slate-900">By Cylinder Size</h2>
                        </div>
                        <div className="px-4 py-3 space-y-3">
                            {bySize.length === 0 ? (
                                <p className="text-xs text-slate-400 py-4 text-center">No data</p>
                            ) : (
                                bySize.map(s => (
                                    <MiniBar key={s.size_name} label={s.size_name} value={s.revenue} max={maxSizeRevenue} amount={s.revenue} />
                                ))
                            )}
                        </div>
                    </div>

                    {/* Payment method split */}
                    <div className="rounded-xl border border-slate-200 bg-white shadow-sm p-4">
                        <h2 className="text-sm font-bold text-slate-900 mb-3">Payment Split</h2>
                        <div className="space-y-2">
                            {summary.total_revenue > 0 && (
                                <>
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="font-medium text-slate-600">Cash</span>
                                        <span className="font-semibold text-slate-900 tabular-nums">
                                            {Math.round((summary.cash_revenue / summary.total_revenue) * 100)}%
                                        </span>
                                    </div>
                                    <div className="h-2 w-full rounded-full bg-slate-100">
                                        <div
                                            className="h-2 rounded-full bg-slate-400"
                                            style={{ width: `${(summary.cash_revenue / summary.total_revenue) * 100}%` }}
                                        />
                                    </div>
                                    <div className="flex items-center justify-between text-xs pt-1">
                                        <span className="font-medium text-slate-600">M-Pesa</span>
                                        <span className="font-semibold text-slate-900 tabular-nums">
                                            {Math.round((summary.mpesa_revenue / summary.total_revenue) * 100)}%
                                        </span>
                                    </div>
                                    <div className="h-2 w-full rounded-full bg-slate-100">
                                        <div
                                            className="h-2 rounded-full bg-emerald-500"
                                            style={{ width: `${(summary.mpesa_revenue / summary.total_revenue) * 100}%` }}
                                        />
                                    </div>
                                </>
                            )}
                        </div>
                    </div>

                    {/* By order type */}
                    <div className="rounded-xl border border-slate-200 bg-white shadow-sm p-4">
                        <h2 className="text-sm font-bold text-slate-900 mb-3">By Order Type</h2>
                        <div className="space-y-2">
                            {byType.map(t => (
                                <div key={t.type} className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        {t.type === 'swap'
                                            ? <RefreshCw className="h-3.5 w-3.5 text-orange-500" />
                                            : <ShoppingBag className="h-3.5 w-3.5 text-blue-500" />}
                                        <span className="text-xs font-medium text-slate-600 capitalize">
                                            {t.type === 'swap' ? 'Swap (Refill)' : 'New Cylinder'}
                                        </span>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xs font-bold text-slate-900 tabular-nums">{fmt(t.revenue)}</p>
                                        <p className="text-[10px] text-slate-400">{t.orders} orders</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

        </AdminLayout>
    );
}
