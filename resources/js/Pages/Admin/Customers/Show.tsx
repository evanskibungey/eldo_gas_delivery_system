import AdminLayout from '@/Layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import {
    Phone, Star, MapPin, Package, ArrowLeft,
    TrendingUp, TrendingDown, Gift, Users, ShoppingBag,
    RefreshCw, CreditCard, Hash,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

// â”€â”€ Types â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

interface CustomerData {
    id:               number;
    name:             string;
    phone:            string;
    is_active:        boolean;
    gaspoints_balance: number;
    referral_code:    string;
    referred_by_name: string | null;
    referral_count:   number;
    total_spend:      number;
    total_orders:     number;
    member_since:     string;
}

interface Order {
    id:             number;
    order_number:   string;
    status:         string;
    order_type:     string;
    size_name:      string | null;
    brand_name:     string | null;
    rider_name:     string | null;
    total_amount:   number;
    payment_method: string;
    created_at:     string;
}

interface Transaction {
    id:            number;
    type:          string;
    points:        number;
    balance_after: number;
    description:   string;
    created_at:    string;
}

interface Address {
    id:          number;
    label:       string;
    description: string | null;
    is_default:  boolean;
}

interface Props {
    customer:     CustomerData;
    orders:       Order[];
    transactions: Transaction[];
    addresses:    Address[];
}

// â”€â”€ Config â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const STATUS_CHIP: Record<string, string> = {
    pending:        'border-amber-200   bg-amber-50   text-amber-700',
    rider_assigned: 'border-blue-200    bg-blue-50    text-blue-700',
    picked_up:      'border-indigo-200  bg-indigo-50  text-indigo-700',
    delivered:      'border-emerald-200 bg-emerald-50 text-emerald-700',
    cancelled:      'border-red-200     bg-red-50     text-red-600',
};

const STATUS_LABEL: Record<string, string> = {
    pending: 'Pending', rider_assigned: 'Assigned', picked_up: 'On the Way',
    delivered: 'Delivered', cancelled: 'Cancelled',
};

const TX_ICON: Record<string, { icon: typeof TrendingUp; color: string }> = {
    earned:         { icon: TrendingUp,   color: 'text-emerald-600' },
    redeemed:       { icon: TrendingDown, color: 'text-red-500' },
    bonus:          { icon: Gift,         color: 'text-violet-600' },
    referral:       { icon: Users,        color: 'text-blue-600' },
    referral_bonus: { icon: Gift,         color: 'text-violet-600' },
};

// â”€â”€ Sub-components â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function StatCard({ icon: Icon, label, value, sub }: { icon: typeof Package; label: string; value: string; sub?: string }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-center gap-2 mb-1">
                <Icon className="h-4 w-4 text-orange-500" />
                <p className="text-xs font-medium text-slate-500">{label}</p>
            </div>
            <p className="text-xl font-bold text-slate-900 tabular-nums">{value}</p>
            {sub && <p className="mt-0.5 text-xs text-slate-400">{sub}</p>}
        </div>
    );
}

function SectionHeader({ children }: { children: React.ReactNode }) {
    return (
        <h2 className="text-sm font-bold text-slate-900 mb-3">{children}</h2>
    );
}

// â”€â”€ Page â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

export default function CustomerShow({ customer, orders, transactions, addresses }: Props) {
    return (
        <AdminLayout
            title={customer.name}
            subtitle={`Customer profile · ${customer.phone}`}
        >
            {/* Back */}
            <div className="mb-5">
                <Button asChild variant="ghost" size="sm" className="gap-1.5 text-slate-500 hover:text-slate-800 -ml-2 h-8">
                    <Link href="/admin/customers">
                        <ArrowLeft className="h-3.5 w-3.5" /> Back to Customers
                    </Link>
                </Button>
            </div>

            {/* Profile header */}
            <div className="mb-6 flex items-start gap-4">
                <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-400 to-amber-500 text-white text-xl font-black shadow-lg shadow-orange-500/20">
                    {customer.name.slice(0, 2).toUpperCase()}
                </div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                        <h1 className="text-xl font-bold text-slate-900">{customer.name}</h1>
                        {!customer.is_active && (
                            <span className="rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-600">
                                Inactive
                            </span>
                        )}
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
                        <span className="flex items-center gap-1.5"><Phone className="h-3.5 w-3.5" />{customer.phone}</span>
                        <span className="flex items-center gap-1.5"><Hash className="h-3.5 w-3.5" />
                            <span className="font-mono font-semibold text-orange-600">{customer.referral_code}</span>
                        </span>
                        <span>Member since {customer.member_since}</span>
                    </div>
                    {customer.referred_by_name && (
                        <p className="mt-1 text-xs text-slate-400">Referred by <span className="font-medium text-slate-600">{customer.referred_by_name}</span></p>
                    )}
                </div>
            </div>

            {/* Stats grid */}
            <div className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard icon={ShoppingBag} label="Total Orders"     value={customer.total_orders.toString()} />
                <StatCard icon={CreditCard}  label="Total Spend"      value={`KES ${customer.total_spend.toLocaleString()}`} />
                <StatCard icon={Star}        label="GasPoints Balance" value={customer.gaspoints_balance.toLocaleString() + ' pts'} />
                <StatCard icon={Users}       label="Referrals"         value={customer.referral_count.toString()} sub="friends referred" />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">

                {/* Left column — orders + transactions */}
                <div className="lg:col-span-2 space-y-6">

                    {/* Order history */}
                    <div>
                        <SectionHeader>Order History</SectionHeader>
                        <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 to-amber-500" />
                            {orders.length === 0 ? (
                                <div className="flex flex-col items-center py-12 text-center">
                                    <ShoppingBag className="h-8 w-8 text-slate-200" />
                                    <p className="mt-2 text-sm text-slate-400">No orders yet</p>
                                </div>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-100 bg-slate-50/80">
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Order</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Details</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {orders.map(o => (
                                            <tr key={o.id} className="hover:bg-slate-50/50 transition-colors group">
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <div className={cn('flex h-7 w-7 items-center justify-center rounded-lg', o.order_type === 'swap' ? 'bg-orange-50' : 'bg-blue-50')}>
                                                            {o.order_type === 'swap'
                                                                ? <RefreshCw className="h-3 w-3 text-orange-500" />
                                                                : <Package   className="h-3 w-3 text-blue-500" />
                                                            }
                                                        </div>
                                                        <div>
                                                            <Link href={`/admin/orders/${o.id}`} className="font-mono text-xs font-semibold text-slate-700 hover:text-orange-600">
                                                                {o.order_number}
                                                            </Link>
                                                            <p className="text-[10px] text-slate-400">{o.created_at}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="text-xs font-medium text-slate-700">{o.size_name}</p>
                                                    <p className="text-[10px] text-slate-400">{o.rider_name ?? 'No rider'}</p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className={cn('inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold', STATUS_CHIP[o.status] ?? STATUS_CHIP.cancelled)}>
                                                        {STATUS_LABEL[o.status] ?? o.status}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <p className="text-sm font-bold text-slate-900 tabular-nums">KES {o.total_amount.toLocaleString()}</p>
                                                    <p className={cn('text-[10px] font-semibold uppercase', o.payment_method === 'mpesa' ? 'text-emerald-600' : 'text-slate-400')}>
                                                        {o.payment_method}
                                                    </p>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                        {orders.length > 0 && (
                            <div className="mt-2 text-right">
                                <Link href={`/admin/orders?search=${customer.phone}`} className="text-xs text-orange-500 hover:text-orange-600 font-medium">
                                    View all orders →
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* GasPoints transactions */}
                    <div>
                        <SectionHeader>GasPoints History</SectionHeader>
                        <div className="rounded-xl border border-slate-200 bg-white shadow-sm px-4 divide-y divide-slate-100">
                            {transactions.length === 0 ? (
                                <div className="flex flex-col items-center py-10 text-center">
                                    <Star className="h-8 w-8 text-slate-200" />
                                    <p className="mt-2 text-sm text-slate-400">No GasPoints activity</p>
                                </div>
                            ) : (
                                transactions.map(tx => {
                                    const cfg = TX_ICON[tx.type] ?? TX_ICON.earned;
                                    const Icon = cfg.icon;
                                    const positive = tx.points > 0;
                                    return (
                                        <div key={tx.id} className="flex items-center gap-3 py-3">
                                            <div className={cn('flex h-8 w-8 shrink-0 items-center justify-center rounded-full', positive ? 'bg-emerald-50' : 'bg-red-50')}>
                                                <Icon className={cn('h-3.5 w-3.5', cfg.color)} />
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-xs font-medium text-slate-800 truncate">{tx.description}</p>
                                                <p className="text-[10px] text-slate-400">{tx.created_at}</p>
                                            </div>
                                            <div className="text-right shrink-0">
                                                <p className={cn('text-sm font-bold tabular-nums', positive ? 'text-emerald-600' : 'text-red-500')}>
                                                    {positive ? '+' : ''}{tx.points.toLocaleString()} pts
                                                </p>
                                                <p className="text-[10px] text-slate-400">{tx.balance_after.toLocaleString()} bal</p>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>
                </div>

                {/* Right column — addresses */}
                <div className="space-y-6">
                    <div>
                        <SectionHeader>Saved Addresses</SectionHeader>
                        {addresses.length === 0 ? (
                            <div className="flex flex-col items-center rounded-xl border border-dashed border-slate-200 py-10 text-center">
                                <MapPin className="h-8 w-8 text-slate-200" />
                                <p className="mt-2 text-sm text-slate-400">No saved addresses</p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {addresses.map(addr => (
                                    <div
                                        key={addr.id}
                                        className={cn(
                                            'rounded-xl border p-3',
                                            addr.is_default
                                                ? 'border-orange-200 bg-orange-50'
                                                : 'border-slate-200 bg-white',
                                        )}
                                    >
                                        <div className="flex items-center gap-2 mb-0.5">
                                            <MapPin className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                                            <p className="text-xs font-semibold text-slate-800">{addr.label}</p>
                                            {addr.is_default && (
                                                <span className="rounded-full border border-orange-200 bg-orange-100 px-1.5 py-0.5 text-[9px] font-bold text-orange-700">
                                                    DEFAULT
                                                </span>
                                            )}
                                        </div>
                                        {addr.description && (
                                            <p className="ml-5 text-xs text-slate-500">{addr.description}</p>
                                        )}
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
