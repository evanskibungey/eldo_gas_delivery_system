import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, ChevronDown, ChevronRight, Layers } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface Item {
    id: number; name: string; description: string | null; price: number;
    photo_url: string | null; sort_order: number; is_active: boolean;
}

interface Group {
    id: number; name: string; selection_type: 'multi' | 'single';
    sort_order: number; is_active: boolean; items: Item[];
}

interface Size { id: number; name: string; groups: Group[] }

function DeleteDialog({ label, onCancel, onConfirm }: { label: string; onCancel: () => void; onConfirm: () => void }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-50">
                    <Trash2 className="h-5 w-5 text-red-500" />
                </div>
                <h3 className="mt-3 text-base font-semibold text-slate-900">Delete "{label}"?</h3>
                <p className="mt-1.5 text-sm text-slate-500">This cannot be undone.</p>
                <div className="mt-5 flex gap-3">
                    <Button variant="outline" className="flex-1" onClick={onCancel}>Cancel</Button>
                    <Button className="flex-1 bg-red-500 hover:bg-red-600 text-white" onClick={onConfirm}>Delete</Button>
                </div>
            </div>
        </div>
    );
}

export default function AddonsIndex({ sizes }: { sizes: Size[] }) {
    const [expanded, setExpanded]         = useState<Set<number>>(new Set(sizes.map(s => s.id)));
    const [deleteTarget, setDeleteTarget] = useState<{ type: 'group' | 'item'; id: number; label: string } | null>(null);

    function toggleExpand(id: number) {
        setExpanded(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    }

    function confirmDelete() {
        if (!deleteTarget) return;
        if (deleteTarget.type === 'group') router.delete(`/admin/catalogue/addon-groups/${deleteTarget.id}`);
        else router.delete(`/admin/catalogue/addon-items/${deleteTarget.id}`);
        setDeleteTarget(null);
    }

    const fmt = (n: number) => n === 0 ? 'Free' : `KES ${n.toLocaleString()}`;

    return (
        <AdminLayout title="Add-ons & Options" subtitle="Manage add-on groups and items per cylinder size">

            <div className="mb-6 flex items-center justify-between">
                <p className="text-sm text-slate-500">Organised by cylinder size. Each group belongs to one size.</p>
                <Button asChild className="bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20 h-9 gap-2">
                    <Link href="/admin/catalogue/addon-groups/create">
                        <Plus className="h-4 w-4" /> Add Group
                    </Link>
                </Button>
            </div>

            <div className="space-y-4">
                {sizes.map(size => (
                    <div key={size.id} className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />

                        {/* Size accordion header */}
                        <button
                            onClick={() => toggleExpand(size.id)}
                            className="flex w-full items-center justify-between px-5 py-4 hover:bg-slate-50/60 transition-colors"
                        >
                            <div className="flex items-center gap-3">
                                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 text-white font-bold text-xs shadow-sm shadow-orange-500/20">
                                    {size.name}
                                </div>
                                <div className="text-left">
                                    <span className="font-semibold text-slate-800">{size.name} Cylinder</span>
                                    <span className="ml-2.5 rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                                        {size.groups.length} group{size.groups.length !== 1 ? 's' : ''}
                                    </span>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="h-7 gap-1 text-xs"
                                    onClick={e => e.stopPropagation()}
                                >
                                    <Link href={`/admin/catalogue/addon-groups/create?size_id=${size.id}`}>
                                        <Plus className="h-3 w-3" /> Group
                                    </Link>
                                </Button>
                                {expanded.has(size.id)
                                    ? <ChevronDown className="h-4 w-4 text-slate-400" />
                                    : <ChevronRight className="h-4 w-4 text-slate-400" />
                                }
                            </div>
                        </button>

                        {/* Groups */}
                        {expanded.has(size.id) && (
                            <div className="border-t border-slate-100">
                                {size.groups.length === 0 ? (
                                    <div className="flex flex-col items-center gap-2 px-5 py-10">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100">
                                            <Layers className="h-5 w-5 text-slate-300" />
                                        </div>
                                        <p className="text-sm text-slate-400">No add-on groups for this size yet.</p>
                                        <Button asChild variant="outline" size="sm" className="mt-1 h-7 gap-1 text-xs">
                                            <Link href={`/admin/catalogue/addon-groups/create?size_id=${size.id}`}>
                                                <Plus className="h-3 w-3" /> Add group
                                            </Link>
                                        </Button>
                                    </div>
                                ) : (
                                    size.groups.map(group => (
                                        <div key={group.id} className="border-b border-slate-100 last:border-0">
                                            {/* Group header */}
                                            <div className="flex items-center justify-between bg-slate-50/60 px-5 py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-semibold text-slate-700 text-sm">{group.name}</span>
                                                    <span className={cn(
                                                        'rounded-full border px-2 py-0.5 text-[10px] font-semibold',
                                                        group.selection_type === 'single'
                                                            ? 'border-blue-200 bg-blue-50 text-blue-700'
                                                            : 'border-purple-200 bg-purple-50 text-purple-700',
                                                    )}>
                                                        {group.selection_type === 'single' ? 'Single-select' : 'Multi-select'}
                                                    </span>
                                                    {group.is_active ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" /> Active
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                                                            <span className="h-1.5 w-1.5 rounded-full bg-slate-400" /> Inactive
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <Button asChild variant="ghost" size="sm" className="h-7 gap-1 text-xs text-slate-500 hover:text-slate-700">
                                                        <Link href={`/admin/catalogue/addon-items/create?group_id=${group.id}`}>
                                                            <Plus className="h-3 w-3" /> Item
                                                        </Link>
                                                    </Button>
                                                    <Button asChild variant="ghost" size="sm" className="h-7 text-xs text-slate-500 hover:text-slate-700">
                                                        <Link href={`/admin/catalogue/addon-groups/${group.id}/edit`}>
                                                            <Pencil className="h-3 w-3" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 text-xs text-red-400 hover:text-red-600 hover:bg-red-50"
                                                        onClick={() => setDeleteTarget({ type: 'group', id: group.id, label: group.name })}
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            </div>

                                            {/* Items */}
                                            {group.items.length === 0 ? (
                                                <p className="px-8 py-3 text-xs text-slate-400 italic">No items in this group.</p>
                                            ) : (
                                                <div className="divide-y divide-slate-50">
                                                    {group.items.map(item => (
                                                        <div key={item.id} className="flex items-center gap-3 px-8 py-3 hover:bg-slate-50/40 transition-colors group">
                                                            {item.photo_url ? (
                                                                <img src={item.photo_url} alt={item.name} className="h-9 w-9 rounded-lg object-cover border border-slate-100 shadow-sm" />
                                                            ) : (
                                                                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 text-[10px] font-bold text-slate-500">
                                                                    {item.name.slice(0, 2).toUpperCase()}
                                                                </div>
                                                            )}
                                                            <div className="flex-1 min-w-0">
                                                                <p className="text-sm font-medium text-slate-800 flex items-center gap-2">
                                                                    {item.name}
                                                                    {!item.is_active && (
                                                                        <span className="rounded-full border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-400">Inactive</span>
                                                                    )}
                                                                </p>
                                                                {item.description && <p className="text-xs text-slate-400 truncate">{item.description}</p>}
                                                            </div>
                                                            <span className={cn(
                                                                'text-sm font-semibold shrink-0',
                                                                item.price === 0 ? 'text-emerald-600' : 'text-slate-700',
                                                            )}>
                                                                {fmt(item.price)}
                                                            </span>
                                                            <div className="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                                                <Button asChild variant="ghost" size="sm" className="h-7 text-xs text-slate-400 hover:text-slate-700">
                                                                    <Link href={`/admin/catalogue/addon-items/${item.id}/edit`}>
                                                                        <Pencil className="h-3 w-3" />
                                                                    </Link>
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="h-7 text-xs text-red-400 hover:text-red-600 hover:bg-red-50"
                                                                    onClick={() => setDeleteTarget({ type: 'item', id: item.id, label: item.name })}
                                                                >
                                                                    <Trash2 className="h-3 w-3" />
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    ))
                                )}
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {deleteTarget && (
                <DeleteDialog label={deleteTarget.label} onCancel={() => setDeleteTarget(null)} onConfirm={confirmDelete} />
            )}
        </AdminLayout>
    );
}
