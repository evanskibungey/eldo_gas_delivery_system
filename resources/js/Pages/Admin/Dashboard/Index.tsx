import AdminLayout from '@/Layouts/AdminLayout';
import { Link, usePage } from '@inertiajs/react';
import {
    ShoppingBag, TrendingUp, Truck, Clock, AlertTriangle,
    CheckCircle2, ArrowUpRight, Flame, Package, Zap,
    ChevronRight, RefreshCw, Star, MapPin,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { OrderStatus } from '@/types/models';

// ── Types ─────────────────────────────────────────────────────────────────────

interface Metrics {
    today_orders:   number;
    today_revenue:  number;
    pending_orders: number;
    active_riders:  number;
    low_stock:      number;
}

interface DailyRevenue { date: string; cash: number; mpesa: number; orders: number; }
interface BySizeItem   { name: string; value: number; }

interface RecentOrder {
    id: number; order_number: string; status: OrderStatus;
    total_amount: number; payment_method: string;
    customer_name: string | null; rider_name: string | null;
    size_name: string | null; created_ago: string;
}

interface TopRider {
    id: number; name: string; avg_rating: number | null;
    avatar_url: string | null; deliveries: number; revenue: number;
}

interface StockItem {
    size_name: string; quantity: number; reorder_point: number;
    status: 'ok' | 'low' | 'depleted';
}

interface Props {
    metrics:      Metrics;
    dailyRevenue: DailyRevenue[];
    bySize:       BySizeItem[];
    recentOrders: RecentOrder[];
    topRiders:    TopRider[];
    stock:        StockItem[];
}

// ── Config ────────────────────────────────────────────────────────────────────

const STATUS_CFG: Partial<Record<OrderStatus, { label: string; dot: string; chip: string }>> = {
    pending:        { label: 'Pending',    dot: 'bg-amber-400',   chip: 'bg-amber-50   text-amber-700   border-amber-200'   },
    rider_assigned: { label: 'Assigned',   dot: 'bg-blue-400',    chip: 'bg-blue-50    text-blue-700    border-blue-200'    },
    picked_up:      { label: 'Picked Up',  dot: 'bg-indigo-400',  chip: 'bg-indigo-50  text-indigo-700  border-indigo-200'  },
    on_the_way:     { label: 'On the Way', dot: 'bg-violet-400',  chip: 'bg-violet-50  text-violet-700  border-violet-200'  },
    delivered:      { label: 'Delivered',  dot: 'bg-emerald-400', chip: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    cancelled:      { label: 'Cancelled',  dot: 'bg-red-400',     chip: 'bg-red-50     text-red-700     border-red-200'     },
};

const STOCK_CFG = {
    ok:       { bar: 'bg-emerald-400', chip: 'bg-emerald-50 text-emerald-700 border-emerald-200', label: 'OK'       },
    low:      { bar: 'bg-amber-400',   chip: 'bg-amber-50   text-amber-700   border-amber-200',   label: 'Low'      },
    depleted: { bar: 'bg-red-400',     chip: 'bg-red-50     text-red-700     border-red-200',     label: 'Depleted' },
};

const fmt = (n: number) => `KES ${n.toLocaleString()}`;

// ── Sparkline ─────────────────────────────────────────────────────────────────

function Sparkline({ data, color = '#f97316' }: { data: number[]; color?: string }) {
    if (data.length < 2) return null;
    const min = Math.min(...data), max = Math.max(...data), range = max - min || 1;
    const W = 72, H = 28, p = 2;
    const pts = data.map((v, i) => {
        const x = p + (i / (data.length - 1)) * (W - p * 2);
        const y = H - p - ((v - min) / range) * (H - p * 2);
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    });
    const fill = [...pts, `${(W - p).toFixed(1)},${H}`, `${p},${H}`].join(' ');
    const [lx, ly] = pts[pts.length - 1].split(',').map(Number);
    return (
        <svg width={W} height={H} viewBox={`0 0 ${W} ${H}`} fill="none">
            <polygon points={fill} fill={color} opacity="0.12" />
            <polyline points={pts.join(' ')} stroke={color} strokeWidth="1.5" fill="none" strokeLinecap="round" strokeLinejoin="round" />
            <circle cx={lx} cy={ly} r="2.5" fill={color} />
        </svg>
    );
}

// ── Sub-components ────────────────────────────────────────────────────────────

function SectionHeader({ title, sub, href, linkLabel = 'View all' }: {
    title: string; sub?: string; href?: string; linkLabel?: string;
}) {
    return (
        <div className="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <div>
                <h2 className="text-sm font-semibold text-slate-900 leading-none">{title}</h2>
                {sub && <p className="text-xs text-slate-400 mt-1">{sub}</p>}
            </div>
            {href && (
                <Link href={href} className="inline-flex items-center gap-1 text-xs font-medium text-orange-500 hover:text-orange-600">
                    {linkLabel}<ChevronRight className="h-3 w-3" />
                </Link>
            )}
        </div>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function Dashboard({ metrics, dailyRevenue, bySize, recentOrders, topRiders, stock }: Props) {
    const { auth } = usePage().props as any;
    const name  = auth?.admin?.name?.split(' ')[0] ?? 'Admin';
    const hour  = new Date().getHours();
    const greet = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';

    // Build sparkline from daily revenue totals
    const revSpark = dailyRevenue.map(d => d.cash + d.mpesa);
    const maxDaily  = Math.max(...revSpark, 1);

    // Stock alerts
    const badStock = stock.filter(s => s.status !== 'ok');

    // Order status summary counts from recentOrders (top 10 only, for display)
    const statusCounts = recentOrders.reduce<Record<string, number>>((acc, o) => {
        acc[o.status] = (acc[o.status] || 0) + 1;
        return acc;
    }, {});

    return (
        <AdminLayout title="Dashboard" subtitle={`${greet}, ${name} — operations snapshot`}>
            <div className="space-y-6">

                {/* ── KPI Cards ────────────────────────────────────────── */}
                <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">

                    {/* Today's Orders */}
                    <div className="relative overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm p-5">
                        <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-blue-500 to-blue-600" />
                        <div className="flex items-start justify-between">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600">
                                <ShoppingBag className="h-5 w-5 text-white" />
                            </div>
                            <Sparkline data={dailyRevenue.map(d => d.orders)} color="#3b82f6" />
                        </div>
                        <p className="mt-4 text-2xl font-bold text-slate-900">{metrics.today_orders}</p>
                        <p className="mt-0.5 text-xs font-medium text-slate-500">Today's Orders</p>
                        {metrics.pending_orders > 0 && (
                            <p className="mt-2 flex items-center gap-1 text-[11px] text-amber-600">
                                <Clock className="h-3 w-3" />{metrics.pending_orders} pending assignment
                            </p>
                        )}
                    </div>

                    {/* Today's Revenue */}
                    <div className="relative overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm p-5">
                        <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-emerald-500 to-teal-600" />
                        <div className="flex items-start justify-between">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600">
                                <TrendingUp className="h-5 w-5 text-white" />
                            </div>
                            <Sparkline data={revSpark.length ? revSpark : [0]} color="#10b981" />
                        </div>
                        <p className="mt-4 text-2xl font-bold text-slate-900">{fmt(metrics.today_revenue)}</p>
                        <p className="mt-0.5 text-xs font-medium text-slate-500">Today's Revenue</p>
                        <p className="mt-2 flex items-center gap-1 text-[11px] text-emerald-600">
                            <ArrowUpRight className="h-3.5 w-3.5" />Delivered orders only
                        </p>
                    </div>

                    {/* Active Riders */}
                    <div className="relative overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm p-5">
                        <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-500 to-orange-600" />
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500 to-orange-600">
                            <Truck className="h-5 w-5 text-white" />
                        </div>
                        <p className="mt-4 text-2xl font-bold text-slate-900">{metrics.active_riders}</p>
                        <p className="mt-0.5 text-xs font-medium text-slate-500">Active & Available Riders</p>
                        <Link href="/admin/riders" className="mt-2 flex items-center gap-1 text-[11px] text-orange-500">
                            <MapPin className="h-3 w-3" />Manage riders
                        </Link>
                    </div>

                    {/* Low Stock Alerts */}
                    <div className="relative overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm p-5">
                        <div className={cn(
                            'absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r',
                            metrics.low_stock > 0 ? 'from-red-500 to-red-600' : 'from-slate-300 to-slate-400',
                        )} />
                        <div className={cn(
                            'flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br',
                            metrics.low_stock > 0 ? 'from-red-500 to-red-600' : 'from-slate-400 to-slate-500',
                        )}>
                            <AlertTriangle className="h-5 w-5 text-white" />
                        </div>
                        <p className="mt-4 text-2xl font-bold text-slate-900">{metrics.low_stock}</p>
                        <p className="mt-0.5 text-xs font-medium text-slate-500">Low / Depleted Stock</p>
                        <Link href="/admin/stock" className="mt-2 flex items-center gap-1 text-[11px] text-red-500">
                            <ChevronRight className="h-3 w-3" />View stock levels
                        </Link>
                    </div>
                </div>

                {/* ── Revenue Chart + By Size ───────────────────────────── */}
                <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">

                    {/* 7-day Revenue Bar Chart */}
                    <div className="lg:col-span-2 rounded-xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                        <SectionHeader title="Revenue — Last 7 Days" sub="Cash vs M-Pesa split" href="/admin/reports/revenue" linkLabel="Full report" />
                        <div className="px-5 pb-5 pt-4">
                            {dailyRevenue.length === 0 ? (
                                <p className="py-8 text-center text-sm text-slate-400">No revenue data yet</p>
                            ) : (
                                <div className="space-y-2.5">
                                    {dailyRevenue.map(d => {
                                        const total  = d.cash + d.mpesa;
                                        const cashPct = total > 0 ? (d.cash  / maxDaily) * 100 : 0;
                                        const mpesaPct= total > 0 ? (d.mpesa / maxDaily) * 100 : 0;
                                        return (
                                            <div key={d.date} className="flex items-center gap-3">
                                                <span className="w-10 shrink-0 text-right text-[10px] font-medium text-slate-400">{d.date}</span>
                                                <div className="flex flex-1 h-5 rounded-md overflow-hidden bg-slate-100 gap-px">
                                                    {cashPct > 0 && (
                                                        <div
                                                            className="h-full bg-slate-500 flex items-center justify-end pr-1 transition-all"
                                                            style={{ width: `${cashPct}%` }}
                                                            title={`Cash: ${fmt(d.cash)}`}
                                                        />
                                                    )}
                                                    {mpesaPct > 0 && (
                                                        <div
                                                            className="h-full bg-emerald-500 flex items-center justify-end pr-1 transition-all"
                                                            style={{ width: `${mpesaPct}%` }}
                                                            title={`M-Pesa: ${fmt(d.mpesa)}`}
                                                        />
                                                    )}
                                                </div>
                                                <span className="w-24 shrink-0 text-right text-xs font-semibold text-slate-700 tabular-nums">
                                                    {fmt(total)}
                                                </span>
                                            </div>
                                        );
                                    })}
                                    <div className="flex items-center gap-4 pt-1">
                                        <span className="flex items-center gap-1.5 text-[10px] text-slate-500">
                                            <span className="h-2.5 w-2.5 rounded-sm bg-slate-500" />Cash
                                        </span>
                                        <span className="flex items-center gap-1.5 text-[10px] text-slate-500">
                                            <span className="h-2.5 w-2.5 rounded-sm bg-emerald-500" />M-Pesa
                                        </span>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Orders by Size */}
                    <div className="rounded-xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                        <SectionHeader title="Orders by Size" sub="Last 30 days" />
                        <div className="px-5 pb-5 pt-4 space-y-3">
                            {bySize.length === 0 ? (
                                <p className="py-4 text-center text-sm text-slate-400">No data yet</p>
                            ) : (() => {
                                const total = bySize.reduce((s, b) => s + b.value, 0);
                                const colors = ['bg-orange-500','bg-orange-400','bg-orange-300','bg-amber-400','bg-amber-300'];
                                return bySize.map((b, i) => {
                                    const pct = total > 0 ? Math.round((b.value / total) * 100) : 0;
                                    return (
                                        <div key={b.name}>
                                            <div className="flex items-center justify-between mb-1">
                                                <div className="flex items-center gap-2">
                                                    <Flame className="h-3 w-3 text-orange-400" />
                                                    <span className="text-xs font-semibold text-slate-700">{b.name}</span>
                                                </div>
                                                <span className="text-[10px] text-slate-400">{b.value} orders · {pct}%</span>
                                            </div>
                                            <div className="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                                <div className={cn('h-1.5 rounded-full transition-all', colors[i % colors.length])} style={{ width: `${pct}%` }} />
                                            </div>
                                        </div>
                                    );
                                });
                            })()}
                        </div>
                    </div>
                </div>

                {/* ── Recent Orders + Stock + Top Riders ────────────────── */}
                <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">

                    {/* Recent 10 Orders */}
                    <div className="lg:col-span-2 rounded-xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                        <SectionHeader title="Recent Orders" sub="Last 10 orders" href="/admin/orders" />
                        <div className="divide-y divide-slate-50">
                            {recentOrders.length === 0 ? (
                                <p className="py-10 text-center text-sm text-slate-400">No orders yet today</p>
                            ) : recentOrders.map(o => {
                                const sc    = STATUS_CFG[o.status];
                                const isSwap = o.order_number ? true : false;
                                return (
                                    <Link key={o.id} href={`/admin/orders/${o.id}`}
                                        className="flex items-center gap-3 px-5 py-3 hover:bg-slate-50/70 transition-colors">
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-orange-50">
                                            <Package className="h-3.5 w-3.5 text-orange-500" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-1.5">
                                                <span className="text-xs font-semibold text-slate-900">{o.order_number}</span>
                                                <span className="text-slate-300 text-xs">·</span>
                                                <span className="text-xs text-slate-500 truncate">{o.customer_name ?? '—'}</span>
                                            </div>
                                            <div className="mt-0.5 flex items-center gap-2 text-[10px] text-slate-400">
                                                <span>{o.size_name}</span>
                                                {o.rider_name && <><span className="text-slate-200">·</span><span>Rider: {o.rider_name}</span></>}
                                            </div>
                                        </div>
                                        <div className="flex flex-col items-end gap-1 shrink-0">
                                            {sc && (
                                                <span className={cn('inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold', sc.chip)}>
                                                    <span className={cn('h-1.5 w-1.5 rounded-full', sc.dot)} />{sc.label}
                                                </span>
                                            )}
                                            <span className="text-[10px] text-slate-400">{o.created_ago}</span>
                                        </div>
                                        <span className="hidden sm:block w-20 text-right text-sm font-bold text-slate-900 tabular-nums shrink-0">
                                            {fmt(o.total_amount)}
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>

                    {/* Stock + Top Riders stacked */}
                    <div className="space-y-5">

                        {/* Stock Levels */}
                        <div className="rounded-xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                            <SectionHeader title="Stock Levels" href="/admin/stock" linkLabel="Manage" />
                            <div className="px-5 py-4 space-y-3.5">
                                {stock.map(s => {
                                    const cfg = STOCK_CFG[s.status];
                                    const pct = s.reorder_point > 0
                                        ? Math.min(100, Math.round((s.quantity / (s.reorder_point * 3)) * 100))
                                        : s.quantity > 0 ? 100 : 0;
                                    return (
                                        <div key={s.size_name}>
                                            <div className="flex items-center justify-between mb-1.5">
                                                <div className="flex items-center gap-1.5">
                                                    <Flame className="h-3 w-3 text-orange-400" />
                                                    <span className="text-xs font-semibold text-slate-700">{s.size_name}</span>
                                                </div>
                                                <div className="flex items-center gap-1.5">
                                                    <span className="text-xs font-bold text-slate-900 tabular-nums">{s.quantity}</span>
                                                    <span className={cn('rounded-full border px-1.5 py-0.5 text-[9px] font-semibold uppercase', cfg.chip)}>{cfg.label}</span>
                                                </div>
                                            </div>
                                            <div className="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                                <div className={cn('h-1.5 rounded-full transition-all', cfg.bar)} style={{ width: `${pct}%` }} />
                                            </div>
                                        </div>
                                    );
                                })}
                                {badStock.length > 0 && (
                                    <div className="mt-1 flex items-start gap-2 rounded-lg border border-red-100 bg-red-50 px-3 py-2">
                                        <AlertTriangle className="h-3.5 w-3.5 shrink-0 text-red-500 mt-0.5" />
                                        <p className="text-[11px] text-red-700 font-medium">
                                            {badStock.length} size{badStock.length > 1 ? 's' : ''} need restocking
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Top Riders This Week */}
                        <div className="rounded-xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                            <SectionHeader title="Top Riders" sub="This week" href="/admin/riders" />
                            <div className="px-5 py-4 space-y-3">
                                {topRiders.length === 0 ? (
                                    <p className="py-4 text-center text-xs text-slate-400">No deliveries this week yet</p>
                                ) : topRiders.map((r, i) => (
                                    <div key={r.id} className="flex items-center gap-3">
                                        <span className="text-[10px] w-4 font-bold text-slate-400">#{i + 1}</span>
                                        {r.avatar_url ? (
                                            <img src={r.avatar_url} className="h-8 w-8 rounded-full object-cover shrink-0" alt={r.name} />
                                        ) : (
                                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600 font-bold text-sm">
                                                {r.name.charAt(0)}
                                            </div>
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-xs font-semibold text-slate-800 truncate">{r.name}</p>
                                            <div className="flex items-center gap-1.5 mt-0.5">
                                                <Truck className="h-2.5 w-2.5 text-slate-400" />
                                                <span className="text-[10px] text-slate-400">{r.deliveries} deliveries</span>
                                                {r.avg_rating != null && (
                                                    <>
                                                        <span className="text-slate-200">·</span>
                                                        <Star className="h-2.5 w-2.5 fill-amber-400 text-amber-400" />
                                                        <span className="text-[10px] text-amber-600">{r.avg_rating.toFixed(1)}</span>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                        <span className="text-xs font-bold text-slate-700 tabular-nums shrink-0">{fmt(r.revenue)}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                {/* ── Quick Links ───────────────────────────────────────── */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        { label: 'Revenue Report', href: '/admin/reports/revenue', icon: TrendingUp, color: 'text-emerald-600 bg-emerald-50 border-emerald-200' },
                        { label: 'Orders Report',  href: '/admin/reports/orders',  icon: ShoppingBag, color: 'text-blue-600 bg-blue-50 border-blue-200'    },
                        { label: 'Customers',      href: '/admin/customers',        icon: CheckCircle2, color: 'text-violet-600 bg-violet-50 border-violet-200' },
                        { label: 'Stock & Audit',  href: '/admin/stock',            icon: Zap,         color: 'text-orange-600 bg-orange-50 border-orange-200' },
                    ].map(l => (
                        <Link key={l.href} href={l.href}
                            className={cn('flex items-center gap-2.5 rounded-xl border px-4 py-3 text-sm font-semibold transition-all hover:shadow-sm', l.color)}
                        >
                            <l.icon className="h-4 w-4 shrink-0" />
                            {l.label}
                        </Link>
                    ))}
                </div>

            </div>
        </AdminLayout>
    );
}
