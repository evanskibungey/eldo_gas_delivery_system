import AdminLayout from '@/Layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Pencil, Star, Truck, ShieldCheck, ShieldAlert, ShieldOff, Phone } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

interface Order {
    id:           number;
    status:       string;
    total_amount: number;
    created_at:   string;
}

interface Rating {
    id:            number;
    order_id:      number;
    customer_name: string;
    stars:         number;
    review:        string | null;
    created_at:    string | null;
}

interface Rider {
    id:                  number;
    name:                string;
    phone:               string;
    national_id:         string | null;
    photo_url:           string | null;
    is_active:           boolean;
    is_available:        boolean;
    is_safety_certified: boolean;
    certification_date:  string | null;
    certification_valid: boolean;
    avg_rating:          number;
    total_deliveries:    number;
    status:              'available' | 'on_delivery' | 'offline';
    created_at:          string;
}

interface Props {
    rider: Rider;
    stats: {
        totalEarnings: number;
        recentOrders:  Order[];
        recentRatings: Rating[];
    };
}

const STATUS_CFG = {
    available:   { label: 'Available',   dot: 'bg-emerald-500', chip: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    on_delivery: { label: 'On Delivery', dot: 'bg-amber-500',   chip: 'border-amber-200 bg-amber-50 text-amber-700' },
    offline:     { label: 'Offline',     dot: 'bg-slate-400',   chip: 'border-slate-200 bg-slate-100 text-slate-500' },
};

const ORDER_STATUS_CFG: Record<string, string> = {
    pending:         'border-slate-200 bg-slate-100 text-slate-600',
    rider_assigned:  'border-blue-200 bg-blue-50 text-blue-700',
    picked_up:       'border-amber-200 bg-amber-50 text-amber-700',
    delivered:       'border-emerald-200 bg-emerald-50 text-emerald-700',
    cancelled:       'border-red-200 bg-red-50 text-red-600',
};

function StarRow({ count }: { count: number }) {
    return (
        <div className="flex items-center gap-0.5">
            {[1, 2, 3, 4, 5].map(i => (
                <Star key={i} className={cn('h-3.5 w-3.5', i <= count ? 'fill-amber-400 text-amber-400' : 'fill-slate-200 text-slate-200')} />
            ))}
        </div>
    );
}

export default function RidersShow({ rider, stats }: Props) {
    const statusCfg = STATUS_CFG[rider.status];
    const fmt = (n: number) => `KES ${n.toLocaleString()}`;

    return (
        <AdminLayout title={rider.name} subtitle="Rider profile and delivery history">

            <div className="mb-6 flex items-center justify-between">
                <Link href="/admin/riders" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <ArrowLeft className="h-4 w-4" /> Back to Riders
                </Link>
                <Button asChild variant="outline" size="sm" className="h-8 gap-1.5 text-xs">
                    <Link href={`/admin/riders/${rider.id}/edit`}>
                        <Pencil className="h-3 w-3" /> Edit
                    </Link>
                </Button>
            </div>

            <div className="grid grid-cols-3 gap-5">

                {/* â”€â”€ Profile card â”€â”€ */}
                <div className="col-span-1">
                    <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                        <div className="flex flex-col items-center px-6 pt-8 pb-6 text-center">
                            {rider.photo_url ? (
                                <img src={rider.photo_url} alt={rider.name} className="h-24 w-24 rounded-full object-cover border-4 border-white shadow-md" />
                            ) : (
                                <div className="flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-orange-400 to-orange-600 text-white text-2xl font-bold shadow-md shadow-orange-500/20">
                                    {rider.name.slice(0, 2).toUpperCase()}
                                </div>
                            )}

                            <h2 className="mt-4 text-lg font-bold text-slate-900">{rider.name}</h2>

                            <span className={cn('mt-2 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold', statusCfg.chip)}>
                                <span className={cn('h-1.5 w-1.5 rounded-full', statusCfg.dot)} />
                                {statusCfg.label}
                            </span>

                            {!rider.is_active && (
                                <span className="mt-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-[10px] font-semibold text-red-600">Inactive</span>
                            )}

                            <div className="mt-5 w-full space-y-2 text-left">
                                <div className="flex items-center gap-2 text-sm text-slate-600">
                                    <Phone className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                                    {rider.phone}
                                </div>
                                {rider.national_id && (
                                    <div className="flex items-center gap-2 text-sm text-slate-600">
                                        <span className="text-xs font-semibold text-slate-400 w-3.5 text-center">ID</span>
                                        {rider.national_id}
                                    </div>
                                )}
                                <div className="flex items-center gap-2 text-xs text-slate-400">
                                    <Truck className="h-3.5 w-3.5 shrink-0" />
                                    Joined {rider.created_at}
                                </div>
                            </div>

                            {/* Safety cert */}
                            <div className="mt-4 w-full rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                                {rider.is_safety_certified && rider.certification_valid ? (
                                    <div className="flex items-center gap-2">
                                        <ShieldCheck className="h-4 w-4 text-emerald-500 shrink-0" />
                                        <div className="text-left">
                                            <p className="text-xs font-semibold text-emerald-700">Safety Certified</p>
                                            <p className="text-[10px] text-slate-400">Expires {rider.certification_date}</p>
                                        </div>
                                    </div>
                                ) : rider.is_safety_certified ? (
                                    <div className="flex items-center gap-2">
                                        <ShieldAlert className="h-4 w-4 text-amber-500 shrink-0" />
                                        <div className="text-left">
                                            <p className="text-xs font-semibold text-amber-700">Certification Expired</p>
                                            <p className="text-[10px] text-slate-400">Was {rider.certification_date}</p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-2">
                                        <ShieldOff className="h-4 w-4 text-slate-300 shrink-0" />
                                        <p className="text-xs text-slate-400">Not certified</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* â”€â”€ Right column â”€â”€ */}
                <div className="col-span-2 space-y-5">

                    {/* Stats */}
                    <div className="grid grid-cols-3 gap-4">
                        {[
                            { label: 'Deliveries',     value: rider.total_deliveries.toString(), icon: Truck,  color: 'text-slate-800' },
                            { label: 'Avg Rating',     value: rider.avg_rating > 0 ? rider.avg_rating.toFixed(1) : '—', icon: Star, color: 'text-amber-600' },
                            { label: 'Total Earnings', value: fmt(stats.totalEarnings), icon: null, color: 'text-emerald-700' },
                        ].map(({ label, value, color }) => (
                            <div key={label} className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden p-4">
                                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 to-amber-500" />
                                <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
                                <p className={cn('mt-1.5 text-xl font-bold tabular-nums', color)}>{value}</p>
                            </div>
                        ))}
                    </div>

                    {/* Recent Orders */}
                    <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 to-amber-500" />
                        <div className="px-5 py-3.5 border-b border-slate-100">
                            <p className="text-sm font-semibold text-slate-800">Recent Orders</p>
                        </div>
                        {stats.recentOrders.length === 0 ? (
                            <p className="px-5 py-8 text-center text-sm text-slate-400 italic">No orders yet.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-50 bg-slate-50/60">
                                        <th className="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Order</th>
                                        <th className="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                                        <th className="px-5 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Amount</th>
                                        <th className="px-5 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-400">Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {stats.recentOrders.map(o => (
                                        <tr key={o.id} className="hover:bg-slate-50/40 transition-colors">
                                            <td className="px-5 py-2.5">
                                                <Link href={`/admin/orders/${o.id}`} className="text-xs font-semibold text-orange-600 hover:text-orange-700">
                                                    #{o.id}
                                                </Link>
                                            </td>
                                            <td className="px-5 py-2.5">
                                                <span className={cn('rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize', ORDER_STATUS_CFG[o.status] ?? 'border-slate-200 bg-slate-100 text-slate-600')}>
                                                    {o.status.replace('_', ' ')}
                                                </span>
                                            </td>
                                            <td className="px-5 py-2.5 text-right text-xs font-medium text-slate-700">{fmt(o.total_amount)}</td>
                                            <td className="px-5 py-2.5 text-right text-xs text-slate-400">{o.created_at.slice(0, 10)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    {/* Recent Ratings */}
                    <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 to-amber-500" />
                        <div className="px-5 py-3.5 border-b border-slate-100">
                            <p className="text-sm font-semibold text-slate-800">Recent Ratings</p>
                        </div>
                        {stats.recentRatings.length === 0 ? (
                            <p className="px-5 py-8 text-center text-sm text-slate-400 italic">No ratings yet.</p>
                        ) : (
                            <div className="divide-y divide-slate-50">
                                {stats.recentRatings.map(r => (
                                    <div key={r.id} className="px-5 py-3.5 hover:bg-slate-50/40 transition-colors">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <p className="text-sm font-medium text-slate-800">{r.customer_name}</p>
                                                    <Link href={`/admin/orders/${r.order_id}`} className="text-[10px] text-orange-500 hover:text-orange-600">
                                                        Order #{r.order_id}
                                                    </Link>
                                                </div>
                                                {r.review && <p className="mt-1 text-xs text-slate-500 truncate">{r.review}</p>}
                                            </div>
                                            <div className="flex flex-col items-end gap-1 shrink-0">
                                                <StarRow count={r.stars} />
                                                <span className="text-[10px] text-slate-400">{r.created_at?.slice(0, 10)}</span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
