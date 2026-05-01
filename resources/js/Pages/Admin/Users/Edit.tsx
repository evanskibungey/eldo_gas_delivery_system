import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ArrowLeft, Loader2, Eye, EyeOff, Clock } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface AdminUser {
    id:            number;
    name:          string;
    email:         string;
    is_active:     boolean;
    last_login_at: string | null;
    roles:         string[];
}

interface Props {
    admin:   AdminUser;
    roles:   string[];
    is_self: boolean;
}

const schema = z.object({
    name:                  z.string().min(2, 'Name must be at least 2 characters'),
    email:                 z.string().email('Enter a valid email address'),
    password:              z.string().optional().or(z.literal('')),
    password_confirmation: z.string().optional().or(z.literal('')),
    role:                  z.string().min(1, 'Select a role'),
    is_active:             z.boolean(),
}).refine(d => !d.password || d.password === d.password_confirmation, {
    message: 'Passwords do not match',
    path:    ['password_confirmation'],
});

type FormData = z.infer<typeof schema>;

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

const roleLabels: Record<string, string> = {
    super_admin:  'Super Admin â€” Full access to all features',
    shop_manager: 'Shop Manager â€” Manages orders and stock',
    dispatcher:   'Dispatcher â€” Assigns and tracks riders',
};

export default function AdminUsersEdit({ admin, roles, is_self }: Props) {
    const [loading, setLoading]           = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm]   = useState(false);

    const {
        register,
        handleSubmit,
        setError,
        formState: { errors },
    } = useForm<FormData>({
        resolver: zodResolver(schema),
        defaultValues: {
            name:                  admin.name,
            email:                 admin.email,
            password:              '',
            password_confirmation: '',
            role:                  admin.roles[0] ?? '',
            is_active:             admin.is_active,
        },
    });

    function onSubmit(data: FormData) {
        setLoading(true);
        router.put(`/admin/users/${admin.id}`, data, {
            onError: (errs) => {
                setLoading(false);
                Object.entries(errs).forEach(([field, message]) =>
                    setError(field as keyof FormData, { message: message as string }),
                );
            },
            onFinish: () => setLoading(false),
        });
    }

    return (
        <AdminLayout
            title="Edit Admin User"
            subtitle={`Editing account for ${admin.name}`}
        >
            {/* Back link */}
            <div className="mb-6">
                <Link
                    href="/admin/users"
                    className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" /> Back to Admin Users
                </Link>
            </div>

            <div className="max-w-xl space-y-5">

                {/* Last login info strip */}
                {admin.last_login_at && (
                    <div className="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                        <Clock className="h-3.5 w-3.5 shrink-0 text-slate-400" />
                        Last login: {admin.last_login_at}
                    </div>
                )}

                <div className="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                    <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

                        {/* Name */}
                        <div>
                            <Label htmlFor="name" className="text-sm font-medium text-slate-700">Full Name</Label>
                            <Input
                                id="name"
                                placeholder="Jane Doe"
                                {...register('name')}
                                className={cn(
                                    'mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm',
                                    'focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all',
                                    errors.name && 'border-red-400 bg-red-50',
                                )}
                            />
                            <FieldError message={errors.name?.message} />
                        </div>

                        {/* Email */}
                        <div>
                            <Label htmlFor="email" className="text-sm font-medium text-slate-700">Email Address</Label>
                            <Input
                                id="email"
                                type="email"
                                placeholder="jane@eldogas.co.ke"
                                {...register('email')}
                                className={cn(
                                    'mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm',
                                    'focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all',
                                    errors.email && 'border-red-400 bg-red-50',
                                )}
                            />
                            <FieldError message={errors.email?.message} />
                        </div>

                        {/* Role */}
                        <div>
                            <Label htmlFor="role" className="text-sm font-medium text-slate-700">Role</Label>
                            <select
                                id="role"
                                {...register('role')}
                                className={cn(
                                    'mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800',
                                    'focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20 focus:bg-white transition-all',
                                    errors.role && 'border-red-400 bg-red-50',
                                )}
                            >
                                <option value="">Select a roleâ€¦</option>
                                {roles.map(r => (
                                    <option key={r} value={r}>{roleLabels[r] ?? r}</option>
                                ))}
                            </select>
                            <FieldError message={errors.role?.message} />
                        </div>

                        {/* New Password (optional) */}
                        <div>
                            <Label htmlFor="password" className="text-sm font-medium text-slate-700">
                                New Password
                                <span className="ml-1.5 text-xs font-normal text-slate-400">(leave blank to keep current)</span>
                            </Label>
                            <div className="relative mt-1.5">
                                <Input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    placeholder="Min. 8 characters"
                                    {...register('password')}
                                    className={cn(
                                        'h-10 border-slate-200 bg-slate-50 pr-10 text-sm',
                                        'focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all',
                                        errors.password && 'border-red-400 bg-red-50',
                                    )}
                                />
                                <button
                                    type="button"
                                    tabIndex={-1}
                                    onClick={() => setShowPassword(p => !p)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                            <FieldError message={errors.password?.message} />
                        </div>

                        {/* Password confirmation */}
                        <div>
                            <Label htmlFor="password_confirmation" className="text-sm font-medium text-slate-700">Confirm New Password</Label>
                            <div className="relative mt-1.5">
                                <Input
                                    id="password_confirmation"
                                    type={showConfirm ? 'text' : 'password'}
                                    placeholder="Re-enter new password"
                                    {...register('password_confirmation')}
                                    className={cn(
                                        'h-10 border-slate-200 bg-slate-50 pr-10 text-sm',
                                        'focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all',
                                        errors.password_confirmation && 'border-red-400 bg-red-50',
                                    )}
                                />
                                <button
                                    type="button"
                                    tabIndex={-1}
                                    onClick={() => setShowConfirm(p => !p)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                                >
                                    {showConfirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                            <FieldError message={errors.password_confirmation?.message} />
                        </div>

                        {/* Active toggle (disabled for self) */}
                        <div className={cn(
                            'flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3',
                            is_self && 'opacity-50',
                        )}>
                            <input
                                id="is_active"
                                type="checkbox"
                                disabled={is_self}
                                {...register('is_active')}
                                className="h-4 w-4 rounded border-slate-300 accent-orange-500"
                            />
                            <Label htmlFor="is_active" className={cn(
                                'text-sm font-normal text-slate-600',
                                !is_self && 'cursor-pointer',
                            )}>
                                {is_self
                                    ? 'You cannot deactivate your own account'
                                    : 'Account is active (can log in)'}
                            </Label>
                        </div>

                        {/* Actions */}
                        <div className="flex gap-3 pt-1">
                            <Button
                                type="button"
                                variant="outline"
                                className="flex-1"
                                onClick={() => router.visit('/admin/users')}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={loading}
                                className={cn(
                                    'flex-1 bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20',
                                    loading && 'opacity-80 cursor-not-allowed',
                                )}
                            >
                                {loading
                                    ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Savingâ€¦</>
                                    : 'Save Changes'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
