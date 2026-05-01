import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Plus, Pencil, Eye, UserX, MoreHorizontal, ShieldCheck, ShieldAlert, Star, Users } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';
import { useState } from 'react';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem,
    DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

interface Rider {
    id:                  number;
    name:                string;
    phone:               string;
    photo_url:           string | null;
    is_active:           boolean;
    is_available:        boolean;
    is_safety_certified: boolean;
    certification_valid: boolean;
    avg_rating:          number;
    total_deliveries:    number;
    status:              'available' | 'on_delivery' | 'offline';
}

interface PaginatedRiders {
    data:          Rider[];
    current_page:  number;
    last_page:     number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total:         number;
}

interface Props {
    riders:  PaginatedRiders;
    filters: { search?: string; is_active?: string; is_available?: string };
}

const STATUS_CFG = {
    available:   { label: 'Available',    dot: 'bg-emerald-500', chip: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    on_delivery: { label: 'On Delivery',  dot: 'bg-amber-500',   chip: 'border-amber-200 bg-amber-50 text-amber-700' },
    offline:     { label: 'Offline',      dot: 'bg-slate-400',   chip: 'border-slate-200 bg-slate-100 text-slate-500' },
};

function StatusBadge({ status }: { status: Rider['status'] }) {
    const cfg = STATUS_CFG[status];
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold', cfg.chip)}>
            <span className={cn('h-1.5 w-1.5 rounded-full', cfg.dot)} />
            {cfg.label}
        </span>
    );
}

function SafetyBadge({ certified, valid }: { certified: boolean; valid: boolean }) {
    if (!certified) return null;
    return valid ? (
        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
            <ShieldCheck className="h-3 w-3" /> Certified
        </span>
    ) : (
        <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
            <ShieldAlert className="h-3 w-3" /> Expired
        </span>
    );
}

function RiderAvatar({ name, photoUrl, size = 9 }: { name: string; photoUrl: string | null; size?: number }) {
    const cls = `h-${size} w-${size}`;
    return photoUrl ? (
        <img src={photoUrl} alt={name} className={cn(cls, 'rounded-full object-cover border border-slate-100 shadow-sm')} />
    ) : (
        <div className={cn(cls, 'flex items-center justify-center rounded-full bg-gradient-to-br from-orange-400 to-orange-600 text-white font-semibold shadow-sm shadow-orange-500/20 text-xs')}>
            {name.slice(0, 2).toUpperCase()}
        </div>
    );
}

function DeactivateDialog({ name, onCancel, onConfirm }: { name: string; onCancel: () => void; onConfirm: () => void }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-50">
                    <UserX className="h-5 w-5 text-red-500" />
                </div>
                <h3 className="mt-3 text-base font-semibold text-slate-900">Deactivate {name}?</h3>
                <p className="mt-1.5 text-sm text-slate-500">
                    This rider will no longer appear as available and cannot log in to the rider app.
                </p>
                <div className="mt-5 flex gap-3">
                    <Button variant="outline" className="flex-1" onClick={onCancel}>Cancel</Button>
                    <Button className="flex-1 bg-red-500 hover:bg-red-600 text-white" onClick={onConfirm}>Deactivate</Button>
                </div>
            </div>
        </div>
    );
}

export default function RidersIndex({ riders, filters }: Props) {
    const [search, setSearch]             = useState(filters.search ?? '');
    const [activeOnly, setActiveOnly]     = useState(filters.is_active === '1');
    const [availableOnly, setAvailableOnly] = useState(filters.is_available === '1');
    const [deactivateTarget, setDeactivateTarget] = useState<Rider | null>(null);

    function applyFilters() {
        router.get('/admin/riders', {
            search:       search || undefined,
            is_active:    activeOnly    ? '1' : undefined,
            is_available: availableOnly ? '1' : undefined,
        }, { preserveState: true });
    }

    function confirmDeactivate() {
        if (!deactivateTarget) return;
        router.delete(`/admin/riders/${deactivateTarget.id}`);
        setDeactivateTarget(null);
    }

    return (
        <AdminLayout title="Riders" subtitle="Manage delivery riders and track availability">

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <span className="text-sm text-slate-500">{riders.total} rider{riders.total !== 1 ? 's' : ''} total</span>
                <Button asChild className="bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20 h-9 gap-2">
                    <Link href="/admin/riders/create">
                        <Plus className="h-4 w-4" /> Add Rider
                    </Link>
                </Button>
            </div>

            {/* Filters */}
            <div className="mb-5 flex flex-wrap items-center gap-3">
                <Input
                    placeholder="Search name or phoneâ€¦"
                    value={search}
                    onChange={e => setSearch(e.target.value)}
                    onKeyDown={e => e.key === 'Enter' && applyFilters()}
                    className="h-9 w-60 border-slate-200 bg-white text-sm focus:border-orange-400 focus:ring-orange-400/20"
                />
                <label className={cn('flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors', activeOnly ? 'border-orange-300 bg-orange-50 text-orange-700' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300')}>
                    <input type="checkbox" checked={activeOnly} onChange={e => setActiveOnly(e.target.checked)} className="accent-orange-500" />
                    Active only
                </label>
                <label className={cn('flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors', availableOnly ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300')}>
                    <input type="checkbox" checked={availableOnly} onChange={e => setAvailableOnly(e.target.checked)} className="accent-emerald-500" />
                    Available only
                </label>
                <Button size="sm" onClick={applyFilters} className="h-9 bg-slate-800 hover:bg-slate-700 text-xs">Apply</Button>
            </div>

            {/* Table */}
            <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/80">
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Rider</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Certification</th>
                            <th className="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Rating</th>
                            <th className="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Deliveries</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {riders.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="py-16 text-center">
                                    <div className="flex flex-col items-center gap-2">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                            <Users className="h-6 w-6 text-slate-300" />
                                        </div>
                                        <p className="text-sm font-medium text-slate-400">No riders found</p>
                                        <p className="text-xs text-slate-300">Add your first rider or adjust the filters.</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                        {riders.data.map(r => (
                            <tr key={r.id} className={cn('transition-colors group', !r.is_active ? 'opacity-60' : 'hover:bg-slate-50/50')}>
                                <td className="px-5 py-4">
                                    <div className="flex items-center gap-3">
                                        <RiderAvatar name={r.name} photoUrl={r.photo_url} />
                                        <div>
                                            <p className="font-semibold text-slate-900">{r.name}</p>
                                            <p className="text-xs text-slate-400">{r.phone}</p>
                                        </div>
                                        {!r.is_active && (
                                            <span className="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-400">Inactive</span>
                                        )}
                                    </div>
                                </td>
                                <td className="px-5 py-4">
                                    <StatusBadge status={r.status} />
                                </td>
                                <td className="px-5 py-4">
                                    <SafetyBadge certified={r.is_safety_certified} valid={r.certification_valid} />
                                    {!r.is_safety_certified && <span className="text-xs text-slate-300 italic">None</span>}
                                </td>
                                <td className="px-5 py-4 text-center">
                                    <div className="flex items-center justify-center gap-1">
                                        <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                        <span className="text-sm font-semibold text-slate-700 tabular-nums">
                                            {r.avg_rating > 0 ? r.avg_rating.toFixed(1) : 'â€”'}
                                        </span>
                                    </div>
                                </td>
                                <td className="px-5 py-4 text-center">
                                    <span className="text-sm font-semibold tabular-nums text-slate-700">{r.total_deliveries}</span>
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
                                                <Link href={`/admin/riders/${r.id}`} className="flex items-center gap-2 cursor-pointer">
                                                    <Eye className="h-3.5 w-3.5 text-slate-400" /> View Profile
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href={`/admin/riders/${r.id}/edit`} className="flex items-center gap-2 cursor-pointer">
                                                    <Pencil className="h-3.5 w-3.5 text-slate-400" /> Edit
                                                </Link>
                                            </DropdownMenuItem>
                                            {r.is_active && (
                                                <>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        onClick={() => setDeactivateTarget(r)}
                                                        className="flex items-center gap-2 text-red-500 focus:text-red-600 focus:bg-red-50 cursor-pointer"
                                                    >
                                                        <UserX className="h-3.5 w-3.5" /> Deactivate
                                                    </DropdownMenuItem>
                                                </>
                                            )}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {riders.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <p className="text-xs text-slate-400">Page {riders.current_page} of {riders.last_page}</p>
                        <div className="flex gap-2">
                            {riders.prev_page_url && (
                                <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                                    <Link href={riders.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {riders.next_page_url && (
                                <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                                    <Link href={riders.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {deactivateTarget && (
                <DeactivateDialog
                    name={deactivateTarget.name}
                    onCancel={() => setDeactivateTarget(null)}
                    onConfirm={confirmDeactivate}
                />
            )}
        </AdminLayout>
    );
}
