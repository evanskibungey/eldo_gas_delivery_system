import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, MoreHorizontal, ImageOff, Tag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useState } from 'react';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem,
    DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Brand {
    id: number; name: string; logo_url: string | null;
    is_active: boolean; sizes: { id: number; name: string }[];
}

function DeleteDialog({ brand, onCancel, onConfirm }: { brand: Brand; onCancel: () => void; onConfirm: () => void }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-50">
                    <Trash2 className="h-5 w-5 text-red-500" />
                </div>
                <h3 className="mt-3 text-base font-semibold text-slate-900">Delete {brand.name}?</h3>
                <p className="mt-1.5 text-sm text-slate-500">This brand will be permanently removed. This cannot be undone.</p>
                <div className="mt-5 flex gap-3">
                    <Button variant="outline" className="flex-1" onClick={onCancel}>Cancel</Button>
                    <Button className="flex-1 bg-red-500 hover:bg-red-600 text-white" onClick={onConfirm}>Delete</Button>
                </div>
            </div>
        </div>
    );
}

export default function BrandsIndex({ brands }: { brands: Brand[] }) {
    const [deleteTarget, setDeleteTarget] = useState<Brand | null>(null);

    function confirmDelete() {
        if (!deleteTarget) return;
        router.delete(`/admin/catalogue/brands/${deleteTarget.id}`);
        setDeleteTarget(null);
    }

    return (
        <AdminLayout title="Gas Brands" subtitle="Manage LPG brands available for delivery">

            <div className="mb-6 flex items-center justify-between">
                <span className="text-sm text-slate-500">{brands.length} brand{brands.length !== 1 ? 's' : ''} configured</span>
                <Button asChild className="bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20 h-9 gap-2">
                    <Link href="/admin/catalogue/brands/create">
                        <Plus className="h-4 w-4" /> Add Brand
                    </Link>
                </Button>
            </div>

            <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/80">
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Brand</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Available In</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {brands.length === 0 && (
                            <tr>
                                <td colSpan={4} className="py-16 text-center">
                                    <div className="flex flex-col items-center gap-2">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                            <Tag className="h-6 w-6 text-slate-300" />
                                        </div>
                                        <p className="text-sm font-medium text-slate-400">No brands yet</p>
                                        <p className="text-xs text-slate-300">Add your first LPG brand to get started.</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {brands.map(b => (
                            <tr key={b.id} className="hover:bg-slate-50/50 transition-colors group">
                                <td className="px-5 py-4">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-100 bg-white overflow-hidden shadow-sm">
                                            {b.logo_url
                                                ? <img src={b.logo_url} alt={b.name} className="h-full w-full object-contain p-1" />
                                                : <ImageOff className="h-4 w-4 text-slate-300" />
                                            }
                                        </div>
                                        <span className="font-semibold text-slate-900">{b.name}</span>
                                    </div>
                                </td>
                                <td className="px-5 py-4">
                                    <div className="flex flex-wrap gap-1">
                                        {b.sizes.length === 0
                                            ? <span className="text-xs text-slate-300 italic">None</span>
                                            : b.sizes.map(s => (
                                                <span key={s.id} className="inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-2 py-0.5 text-[10px] font-semibold text-orange-700">
                                                    {s.name}
                                                </span>
                                            ))
                                        }
                                    </div>
                                </td>
                                <td className="px-5 py-4">
                                    {b.is_active ? (
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
                                                <Link href={`/admin/catalogue/brands/${b.id}/edit`} className="flex items-center gap-2 cursor-pointer">
                                                    <Pencil className="h-3.5 w-3.5 text-slate-400" /> Edit
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                onClick={() => setDeleteTarget(b)}
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
                <DeleteDialog brand={deleteTarget} onCancel={() => setDeleteTarget(null)} onConfirm={confirmDelete} />
            )}
        </AdminLayout>
    );
}
