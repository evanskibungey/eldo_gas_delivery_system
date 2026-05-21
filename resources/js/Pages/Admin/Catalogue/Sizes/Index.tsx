import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, MoreHorizontal, Building2, Home, Package } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useState } from 'react';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem,
    DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Size {
    id:            number;
    name:          string;
    weight_kg:     number;
    sort_order:    number;
    is_commercial: boolean;
    is_active:     boolean;
    swap_price:    number | null;
    stock:         number | null;
    image_url:     string | null;
}

function DeleteDialog({ size, onCancel, onConfirm }: { size: Size; onCancel: () => void; onConfirm: () => void }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-50">
                    <Trash2 className="h-5 w-5 text-red-500" />
                </div>
                <h3 className="mt-3 text-base font-semibold text-slate-900">Delete {size.name} size?</h3>
                <p className="mt-1.5 text-sm text-slate-500">
                    This will remove the cylinder size and all associated pricing and add-ons. This cannot be undone.
                </p>
                <div className="mt-5 flex gap-3">
                    <Button variant="outline" className="flex-1" onClick={onCancel}>Cancel</Button>
                    <Button className="flex-1 bg-red-500 hover:bg-red-600 text-white" onClick={onConfirm}>Delete</Button>
                </div>
            </div>
        </div>
    );
}

function StockBadge({ stock }: { stock: number | null }) {
    if (stock == null) return <span className="text-sm text-slate-300">—</span>;
    const cls = stock === 0
        ? 'border-red-200 bg-red-50 text-red-600'
        : stock <= 5
            ? 'border-amber-200 bg-amber-50 text-amber-600'
            : 'border-emerald-200 bg-emerald-50 text-emerald-600';
    return (
        <span className={cn('inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold', cls)}>
            {stock}
        </span>
    );
}

export default function SizesIndex({ sizes }: { sizes: Size[] }) {
    const [deleteTarget, setDeleteTarget] = useState<Size | null>(null);

    function confirmDelete() {
        if (!deleteTarget) return;
        router.delete(`/admin/catalogue/sizes/${deleteTarget.id}`);
        setDeleteTarget(null);
    }

    const fmt = (n: number | null) => n != null ? `KES ${n.toLocaleString()}` : '—';

    return (
        <AdminLayout title="Cylinder Sizes" subtitle="Manage available cylinder sizes">

            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <span className="text-sm text-slate-500">{sizes.length} size{sizes.length !== 1 ? 's' : ''} configured</span>
                </div>
                <Button asChild className="bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20 h-9 gap-2">
                    <Link href="/admin/catalogue/sizes/create">
                        <Plus className="h-4 w-4" /> Add Size
                    </Link>
                </Button>
            </div>

            <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/80">
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Size</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Swap Price</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Filled Stock</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {sizes.length === 0 && (
                            <tr>
                                <td colSpan={6} className="py-16 text-center">
                                    <div className="flex flex-col items-center gap-2">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                            <Package className="h-6 w-6 text-slate-300" />
                                        </div>
                                        <p className="text-sm font-medium text-slate-400">No cylinder sizes yet</p>
                                        <p className="text-xs text-slate-300">Add your first size to get started.</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {sizes.map(s => (
                            <tr key={s.id} className="hover:bg-slate-50/50 transition-colors group">
                                <td className="px-5 py-4">
                                    <div className="flex items-center gap-3">
                                        {s.image_url ? (
                                            <img
                                                src={s.image_url}
                                                alt={s.name}
                                                className="h-10 w-10 rounded-lg object-cover border border-slate-100 shadow-sm"
                                            />
                                        ) : (
                                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 text-white font-bold text-xs shadow-sm shadow-orange-500/20 shrink-0">
                                                {s.name}
                                            </div>
                                        )}
                                        <div>
                                            <p className="font-semibold text-slate-900">{s.name}</p>
                                            <p className="text-xs text-slate-400">{s.weight_kg} kg</p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-5 py-4">
                                    {s.is_commercial ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[10px] font-semibold text-blue-700">
                                            <Building2 className="h-3 w-3" /> Commercial
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-semibold text-slate-600">
                                            <Home className="h-3 w-3" /> Household
                                        </span>
                                    )}
                                </td>
                                <td className="px-5 py-4 font-medium text-slate-700">{fmt(s.swap_price)}</td>
                                <td className="px-5 py-4">
                                    <StockBadge stock={s.stock} />
                                </td>
                                <td className="px-5 py-4">
                                    {s.is_active ? (
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700">
                                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Active
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-semibold text-slate-500">
                                            <span className="h-1.5 w-1.5 rounded-full bg-slate-400" /> Inactive
                                        </span>
                                    )}
                                </td>
                                <td className="px-5 py-4 text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="icon" className="h-8 w-8 text-slate-400 hover:text-slate-700 hover:bg-slate-100 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <MoreHorizontal className="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" className="w-40">
                                            <DropdownMenuItem asChild>
                                                <Link href={`/admin/catalogue/sizes/${s.id}/edit`} className="flex items-center gap-2 cursor-pointer">
                                                    <Pencil className="h-3.5 w-3.5 text-slate-400" /> Edit
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                onClick={() => setDeleteTarget(s)}
                                                className="flex items-center gap-2 text-red-500 focus:text-red-600 focus:bg-red-50 cursor-pointer"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" /> Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {deleteTarget && (
                <DeleteDialog size={deleteTarget} onCancel={() => setDeleteTarget(null)} onConfirm={confirmDelete} />
            )}
        </AdminLayout>
    );
}
