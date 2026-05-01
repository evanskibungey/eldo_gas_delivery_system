import CustomerLayout from '@/Layouts/CustomerLayout';
import { router, useForm, Link } from '@inertiajs/react';
import {
    User, Phone, Star, MapPin, Package, ChevronRight,
    Edit2, Check, X, Calendar, Hash,
} from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

// â”€â”€ Types â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

interface CustomerData {
    id:               number;
    name:             string;
    phone:            string;
    gaspoints_balance: number;
    referral_code:    string;
    member_since:     string;
    total_orders:     number;
}

interface Address {
    id:          number;
    label:       string;
    description: string | null;
    is_default:  boolean;
    latitude:    number;
    longitude:   number;
}

interface RecentOrder {
    id:           number;
    order_number: string;
    status:       string;
    size_name:    string | null;
    brand_name:   string | null;
    total_amount: number;
    created_at:   string;
}

interface Props {
    customer:     CustomerData;
    addresses:    Address[];
    recentOrders: RecentOrder[];
}

// â”€â”€ Config â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const STATUS_COLORS: Record<string, string> = {
    pending:        'bg-amber-100 text-amber-700',
    rider_assigned: 'bg-blue-100 text-blue-700',
    picked_up:      'bg-indigo-100 text-indigo-700',
    delivered:      'bg-emerald-100 text-emerald-700',
    cancelled:      'bg-slate-100 text-slate-500',
};

const STATUS_LABELS: Record<string, string> = {
    pending:        'Pending',
    rider_assigned: 'Assigned',
    picked_up:      'On the Way',
    delivered:      'Delivered',
    cancelled:      'Cancelled',
};

const LABEL_ICONS: Record<string, string> = {
    Home:       'ðŸ ',
    Office:     'ðŸ¢',
    Restaurant: 'ðŸ½ï¸',
    Other:      '📍',
};

// â”€â”€ Sub-components â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function StatChip({ icon: Icon, label, value }: { icon: typeof User; label: string; value: string | number }) {
    return (
        <div className="flex flex-col items-center rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <Icon className="h-4 w-4 text-orange-500 mb-1" />
            <p className="text-base font-bold text-slate-900 tabular-nums">{value}</p>
            <p className="text-[10px] text-slate-400 font-medium uppercase tracking-wide">{label}</p>
        </div>
    );
}

function NameEditForm({ currentName, onDone }: { currentName: string; onDone: () => void }) {
    const { data, setData, put, processing, errors } = useForm({ name: currentName });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put('/profile', { onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="flex items-center gap-2 mt-2">
            <Input
                value={data.name}
                onChange={e => setData('name', e.target.value)}
                className="h-9 flex-1 border-orange-300 focus:border-orange-400 focus:ring-orange-400/20"
                autoFocus
            />
            <Button type="submit" size="icon" className="h-9 w-9 shrink-0 bg-orange-500 hover:bg-orange-600" disabled={processing}>
                <Check className="h-4 w-4" />
            </Button>
            <Button type="button" size="icon" variant="ghost" className="h-9 w-9 shrink-0" onClick={onDone}>
                <X className="h-4 w-4" />
            </Button>
            {errors.name && <p className="text-xs text-red-500 mt-1">{errors.name}</p>}
        </form>
    );
}

// â”€â”€ Page â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

export default function ProfileShow({ customer, addresses, recentOrders }: Props) {
    const [editingName, setEditingName] = useState(false);

    function deleteAddress(id: number) {
        if (!confirm('Remove this address?')) return;
        router.delete(`/addresses/${id}`, { preserveScroll: true });
    }

    function setDefaultAddress(id: number) {
        router.post(`/addresses/${id}/set-default`, {}, { preserveScroll: true });
    }

    return (
        <CustomerLayout title="My Profile" showBack backHref="/home">
            <div className="mx-auto max-w-sm px-4 py-5 space-y-5">

                {/* Avatar + name */}
                <div className="flex flex-col items-center text-center">
                    <div className="flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-orange-400 to-amber-500 text-white text-2xl font-black shadow-lg shadow-orange-500/20">
                        {customer.name.slice(0, 2).toUpperCase()}
                    </div>

                    {editingName ? (
                        <div className="w-full mt-2">
                            <NameEditForm currentName={customer.name} onDone={() => setEditingName(false)} />
                        </div>
                    ) : (
                        <div className="mt-3 flex items-center gap-2">
                            <h1 className="text-lg font-bold text-slate-900">{customer.name}</h1>
                            <button
                                onClick={() => setEditingName(true)}
                                className="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors"
                            >
                                <Edit2 className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    )}
                    <p className="mt-0.5 text-sm text-slate-500 font-mono">{customer.phone}</p>
                </div>

                {/* Stats row */}
                <div className="grid grid-cols-3 gap-2">
                    <StatChip icon={Package}  label="Orders"  value={customer.total_orders} />
                    <StatChip icon={Star}      label="Points"  value={customer.gaspoints_balance.toLocaleString()} />
                    <StatChip icon={Calendar}  label="Since"   value={customer.member_since} />
                </div>

                {/* Account info card */}
                <div className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 to-amber-500" />
                    <div className="px-4 py-3 border-b border-slate-100">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">Account Details</p>
                    </div>
                    <div className="divide-y divide-slate-100">
                        <div className="flex items-center gap-3 px-4 py-3">
                            <Phone className="h-4 w-4 text-slate-400 shrink-0" />
                            <div className="flex-1 min-w-0">
                                <p className="text-[10px] text-slate-400 font-medium uppercase">Phone</p>
                                <p className="text-sm font-semibold text-slate-800 font-mono">{customer.phone}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3 px-4 py-3">
                            <Hash className="h-4 w-4 text-slate-400 shrink-0" />
                            <div className="flex-1 min-w-0">
                                <p className="text-[10px] text-slate-400 font-medium uppercase">Referral Code</p>
                                <p className="text-sm font-mono font-bold text-orange-600 tracking-widest">{customer.referral_code}</p>
                            </div>
                            <Link href="/gaspoints" className="text-xs text-orange-500 hover:text-orange-600 font-medium">
                                Share →
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Saved addresses */}
                <div>
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-sm font-bold text-slate-900">Saved Addresses</h2>
                        <Link
                            href="/addresses/create"
                            className="text-xs font-semibold text-orange-500 hover:text-orange-600"
                        >
                            + Add new
                        </Link>
                    </div>

                    {addresses.length === 0 ? (
                        <div className="flex flex-col items-center rounded-xl border border-dashed border-slate-300 py-8 text-center">
                            <MapPin className="h-8 w-8 text-slate-200" />
                            <p className="mt-2 text-sm text-slate-400">No saved addresses</p>
                            <Link
                                href="/addresses/create"
                                className="mt-3 rounded-xl bg-orange-500 px-4 py-2 text-xs font-semibold text-white hover:bg-orange-600"
                            >
                                Add your first address
                            </Link>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {addresses.map(addr => (
                                <div
                                    key={addr.id}
                                    className={cn(
                                        'flex items-start gap-3 rounded-xl border p-3 transition-colors',
                                        addr.is_default
                                            ? 'border-orange-200 bg-orange-50'
                                            : 'border-slate-200 bg-white',
                                    )}
                                >
                                    <span className="text-lg shrink-0 mt-0.5">{LABEL_ICONS[addr.label] ?? '📍'}</span>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="text-sm font-semibold text-slate-800">{addr.label}</p>
                                            {addr.is_default && (
                                                <span className="rounded-full border border-orange-200 bg-orange-100 px-2 py-0.5 text-[10px] font-semibold text-orange-700">
                                                    Default
                                                </span>
                                            )}
                                        </div>
                                        {addr.description && (
                                            <p className="mt-0.5 text-xs text-slate-500 truncate">{addr.description}</p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-1 shrink-0">
                                        {!addr.is_default && (
                                            <button
                                                onClick={() => setDefaultAddress(addr.id)}
                                                className="rounded-lg border border-slate-200 px-2 py-1 text-[10px] font-medium text-slate-600 hover:bg-slate-100 transition-colors"
                                            >
                                                Set default
                                            </button>
                                        )}
                                        <Link
                                            href={`/addresses/${addr.id}/edit`}
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors"
                                        >
                                            <Edit2 className="h-3.5 w-3.5" />
                                        </Link>
                                        <button
                                            onClick={() => deleteAddress(addr.id)}
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500 transition-colors"
                                        >
                                            <X className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Recent orders */}
                {recentOrders.length > 0 && (
                    <div>
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-sm font-bold text-slate-900">Recent Orders</h2>
                            <Link href="/orders" className="text-xs font-semibold text-orange-500 hover:text-orange-600">
                                View all →
                            </Link>
                        </div>
                        <div className="space-y-2">
                            {recentOrders.map(o => (
                                <Link
                                    key={o.id}
                                    href={`/orders/${o.id}`}
                                    className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 hover:bg-slate-50 transition-colors"
                                >
                                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-orange-50">
                                        <Package className="h-4 w-4 text-orange-500" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="font-mono text-xs font-semibold text-slate-700">{o.order_number}</p>
                                        <p className="text-xs text-slate-500 truncate">
                                            {o.size_name}{o.brand_name ? ` · ${o.brand_name}` : ''} · {o.created_at}
                                        </p>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <p className="text-sm font-bold text-slate-800 tabular-nums">
                                            KES {o.total_amount.toLocaleString()}
                                        </p>
                                        <span className={cn('rounded-full px-2 py-0.5 text-[10px] font-medium', STATUS_COLORS[o.status] ?? 'bg-slate-100 text-slate-500')}>
                                            {STATUS_LABELS[o.status] ?? o.status}
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

            </div>
        </CustomerLayout>
    );
}
