import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Search, Eye, Users, Star, ShoppingBag, ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useState } from 'react';
import { cn } from '@/lib/utils';

// â”€â”€ Types â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

interface CustomerRow {
    id:                number;
    name:              string;
    phone:             string;
    gaspoints_balance: number;
    orders_count:      number;
    is_active:         boolean;
    joined_at:         string;
    last_order_at:     string | null;
}

interface Paginated {
    data:          CustomerRow[];
    current_page:  number;
    last_page:     number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total:         number;
}

interface Props {
    customers: Paginated;
    filters:   { search?: string };
}

// â”€â”€ Sub-components â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function CustomerAvatar({ name }: { name: string }) {
    return (
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-orange-400 to-amber-500 text-white text-xs font-bold shadow-sm shadow-orange-500/20">
            {name.slice(0, 2).toUpperCase()}
        </div>
    );
}

// â”€â”€ Page â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

export default function CustomersIndex({ customers, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applySearch() {
        router.get('/admin/customers', { search: search || undefined }, { preserveState: true });
    }

    return (
        <AdminLayout title="Customers" subtitle="Browse and manage registered customers">

            {/* Header */}
            <div className="mb-5 flex items-center justify-between">
                <p className="text-sm text-slate-500">{customers.total.toLocaleString()} customer{customers.total !== 1 ? 's' : ''} registered</p>
                <div className="relative">
                    <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400 pointer-events-none" />
                    <Input
                        placeholder="Name or phone…"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        onKeyDown={e => e.key === 'Enter' && applySearch()}
                        className="h-9 w-60 pl-8 border-slate-200 bg-white text-sm focus:border-orange-400 focus:ring-orange-400/20"
                    />
                </div>
            </div>

            {/* Table */}
            <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />

                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/80">
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Customer</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</th>
                            <th className="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Orders</th>
                            <th className="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">GasPoints</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Joined</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Last Order</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">

                        {customers.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="py-16 text-center">
                                    <div className="flex flex-col items-center gap-2">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                            <Users className="h-6 w-6 text-slate-300" />
                                        </div>
                                        <p className="text-sm font-medium text-slate-400">No customers found</p>
                                        <p className="text-xs text-slate-300">Try a different search term.</p>
                                    </div>
                                </td>
                            </tr>
                        )}

                        {customers.data.map(c => (
                            <tr key={c.id} className={cn('transition-colors group', !c.is_active ? 'opacity-60' : 'hover:bg-slate-50/50')}>
                                {/* Customer */}
                                <td className="px-5 py-4">
                                    <div className="flex items-center gap-3">
                                        <CustomerAvatar name={c.name} />
                                        <div>
                                            <p className="font-semibold text-slate-900">{c.name}</p>
                                            {!c.is_active && (
                                                <span className="text-[10px] font-semibold text-red-400">Inactive</span>
                                            )}
                                        </div>
                                    </div>
                                </td>

                                {/* Phone */}
                                <td className="px-5 py-4">
                                    <p className="font-mono text-xs text-slate-600">{c.phone}</p>
                                </td>

                                {/* Orders */}
                                <td className="px-5 py-4 text-center">
                                    <div className="flex items-center justify-center gap-1">
                                        <ShoppingBag className="h-3.5 w-3.5 text-slate-400" />
                                        <span className="text-sm font-semibold tabular-nums text-slate-700">{c.orders_count}</span>
                                    </div>
                                </td>

                                {/* GasPoints */}
                                <td className="px-5 py-4 text-center">
                                    <div className="flex items-center justify-center gap-1">
                                        <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                        <span className="text-sm font-semibold tabular-nums text-slate-700">
                                            {c.gaspoints_balance.toLocaleString()}
                                        </span>
                                    </div>
                                </td>

                                {/* Joined */}
                                <td className="px-5 py-4">
                                    <p className="text-xs text-slate-500">{c.joined_at}</p>
                                </td>

                                {/* Last order */}
                                <td className="px-5 py-4">
                                    <p className="text-xs text-slate-500">{c.last_order_at ?? '—'}</p>
                                </td>

                                {/* Action */}
                                <td className="px-5 py-4 text-right">
                                    <Button asChild variant="ghost" size="sm"
                                        className="h-8 w-8 p-0 opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-slate-700">
                                        <Link href={`/admin/customers/${c.id}`}>
                                            <Eye className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {/* Pagination */}
                {customers.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <p className="text-xs text-slate-400">
                            Page {customers.current_page} of {customers.last_page} · {customers.total} customers
                        </p>
                        <div className="flex gap-2">
                            {customers.prev_page_url && (
                                <Button asChild variant="outline" size="sm" className="h-8 gap-1 text-xs">
                                    <Link href={customers.prev_page_url}>
                                        <ChevronLeft className="h-3 w-3" /> Prev
                                    </Link>
                                </Button>
                            )}
                            {customers.next_page_url && (
                                <Button asChild variant="outline" size="sm" className="h-8 gap-1 text-xs">
                                    <Link href={customers.next_page_url}>
                                        Next <ChevronRight className="h-3 w-3" />
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

        </AdminLayout>
    );
}
