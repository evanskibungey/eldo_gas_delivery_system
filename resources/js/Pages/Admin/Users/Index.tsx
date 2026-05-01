import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import {
    Plus,
    MoreHorizontal,
    ShieldCheck,
    ShieldOff,
    Pencil,
    Trash2,
    UserCircle2,
    CheckCircle2,
    XCircle,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface AdminUser {
    id:            number;
    name:          string;
    email:         string;
    is_active:     boolean;
    last_login_at: string | null;
    roles:         string[];
    is_self:       boolean;
}

interface Props {
    admins: AdminUser[];
    roles:  string[];
}

const roleColors: Record<string, string> = {
    super_admin:    'bg-purple-500/10 text-purple-400 border-purple-500/20',
    shop_manager:   'bg-blue-500/10 text-blue-400 border-blue-500/20',
    dispatcher:     'bg-teal-500/10 text-teal-400 border-teal-500/20',
};

function RoleBadge({ role }: { role: string }) {
    const color = roleColors[role] ?? 'bg-slate-500/10 text-slate-400 border-slate-500/20';
    const label = role.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    return (
        <span className={cn('inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold', color)}>
            {label}
        </span>
    );
}

function DeleteDialog({ admin, onCancel, onConfirm }: {
    admin:     AdminUser;
    onCancel:  () => void;
    onConfirm: () => void;
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div className="mb-1 flex h-10 w-10 items-center justify-center rounded-full bg-red-50">
                    <Trash2 className="h-5 w-5 text-red-500" />
                </div>
                <h3 className="mt-3 text-base font-semibold text-slate-900">Delete admin user?</h3>
                <p className="mt-1.5 text-sm text-slate-500">
                    <strong>{admin.name}</strong> will be permanently removed. This action cannot be undone.
                </p>
                <div className="mt-5 flex gap-3">
                    <Button variant="outline" className="flex-1" onClick={onCancel}>
                        Cancel
                    </Button>
                    <Button
                        className="flex-1 bg-red-500 hover:bg-red-600 text-white"
                        onClick={onConfirm}
                    >
                        Delete
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default function AdminUsersIndex({ admins, roles }: Props) {
    const [deleteTarget, setDeleteTarget] = useState<AdminUser | null>(null);

    function confirmDelete() {
        if (!deleteTarget) return;
        router.delete(`/admin/users/${deleteTarget.id}`);
        setDeleteTarget(null);
    }

    return (
        <AdminLayout title="Admin Users" subtitle="Manage portal access and roles">

            {/* Header row */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <p className="text-sm text-slate-500">
                        {admins.length} admin{admins.length !== 1 ? 's' : ''} total
                    </p>
                </div>
                <Button asChild className="bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20 h-9 gap-2">
                    <Link href="/admin/users/create">
                        <Plus className="h-4 w-4" />
                        Add Admin
                    </Link>
                </Button>
            </div>

            {/* Table card */}
            <div className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50">
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">User</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Role</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Last Login</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {admins.length === 0 && (
                            <tr>
                                <td colSpan={5} className="py-16 text-center text-slate-400">
                                    No admin users found.
                                </td>
                            </tr>
                        )}
                        {admins.map(admin => (
                            <tr key={admin.id} className="hover:bg-slate-50/50 transition-colors">
                                {/* User */}
                                <td className="px-5 py-4">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 text-white text-xs font-bold shadow-sm">
                                            {admin.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)}
                                        </div>
                                        <div>
                                            <p className="font-medium text-slate-900 leading-none">
                                                {admin.name}
                                                {admin.is_self && (
                                                    <span className="ml-2 text-[10px] text-orange-500 font-semibold bg-orange-50 border border-orange-100 rounded-full px-1.5 py-0.5">You</span>
                                                )}
                                            </p>
                                            <p className="text-xs text-slate-400 mt-0.5">{admin.email}</p>
                                        </div>
                                    </div>
                                </td>

                                {/* Role */}
                                <td className="px-5 py-4">
                                    {admin.roles.length > 0
                                        ? admin.roles.map(r => <RoleBadge key={r} role={r} />)
                                        : <span className="text-slate-400 text-xs">No role</span>
                                    }
                                </td>

                                {/* Status */}
                                <td className="px-5 py-4">
                                    {admin.is_active ? (
                                        <span className="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600">
                                            <CheckCircle2 className="h-3.5 w-3.5" /> Active
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400">
                                            <XCircle className="h-3.5 w-3.5" /> Suspended
                                        </span>
                                    )}
                                </td>

                                {/* Last login */}
                                <td className="px-5 py-4 text-xs text-slate-400">
                                    {admin.last_login_at ?? 'Never'}
                                </td>

                                {/* Actions */}
                                <td className="px-5 py-4 text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-8 w-8 text-slate-400 hover:text-slate-700 hover:bg-slate-100"
                                            >
                                                <MoreHorizontal className="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" className="w-44">
                                            <DropdownMenuItem asChild>
                                                <Link href={`/admin/users/${admin.id}/edit`} className="flex items-center gap-2 cursor-pointer">
                                                    <Pencil className="h-3.5 w-3.5 text-slate-400" />
                                                    Edit
                                                </Link>
                                            </DropdownMenuItem>
                                            {!admin.is_self && (
                                                <>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        onClick={() => setDeleteTarget(admin)}
                                                        className="flex items-center gap-2 text-red-500 focus:text-red-600 focus:bg-red-50 cursor-pointer"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                        Delete
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
            </div>

            {/* Delete confirmation dialog */}
            {deleteTarget && (
                <DeleteDialog
                    admin={deleteTarget}
                    onCancel={() => setDeleteTarget(null)}
                    onConfirm={confirmDelete}
                />
            )}
        </AdminLayout>
    );
}
