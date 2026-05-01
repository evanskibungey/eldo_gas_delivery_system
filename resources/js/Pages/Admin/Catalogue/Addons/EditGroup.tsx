import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface Props {
    group: { id: number; size_id: number; name: string; selection_type: 'multi' | 'single'; sort_order: number; is_active: boolean };
    sizes: { id: number; name: string }[];
}

const schema = z.object({
    size_id:        z.coerce.number().int().min(1, 'Select a cylinder size'),
    name:           z.string().min(1, 'Group name is required').max(100),
    selection_type: z.enum(['multi', 'single']),
    sort_order:     z.coerce.number().int().min(0),
    is_active:      z.boolean(),
});

type FormData = z.infer<typeof schema>;

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function EditGroup({ group, sizes }: Props) {
    const [loading, setLoading] = useState(false);

    const { register, handleSubmit, setError, formState: { errors } } = useForm<FormData>({
        resolver: zodResolver(schema),
        defaultValues: {
            size_id: group.size_id, name: group.name,
            selection_type: group.selection_type, sort_order: group.sort_order, is_active: group.is_active,
        },
    });

    function onSubmit(data: FormData) {
        setLoading(true);
        router.put(`/admin/catalogue/addon-groups/${group.id}`, data, {
            onError: (errs) => { setLoading(false); Object.entries(errs).forEach(([f, m]) => setError(f as keyof FormData, { message: m as string })); },
            onFinish: () => setLoading(false),
        });
    }

    const selectCls = (err?: { message?: string }) => cn(
        'mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800',
        'focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20 focus:bg-white transition-all',
        err && 'border-red-400 bg-red-50',
    );

    return (
        <AdminLayout title={`Edit Group — ${group.name}`} subtitle="Update add-on group details">
            <div className="mb-6">
                <Link href="/admin/catalogue/addon-groups" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <ArrowLeft className="h-4 w-4" /> Back to Add-ons
                </Link>
            </div>

            <div className="max-w-lg">
                <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                    <div className="p-6">
                        <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Cylinder Size</Label>
                                <select {...register('size_id')} className={selectCls(errors.size_id)}>
                                    {sizes.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                                <FieldError message={errors.size_id?.message} />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Group Name</Label>
                                <Input {...register('name')}
                                    className={cn('mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all', errors.name && 'border-red-400 bg-red-50')} />
                                <FieldError message={errors.name?.message} />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Selection Type</Label>
                                <div className="mt-2 grid grid-cols-2 gap-3">
                                    {[
                                        { value: 'multi',  label: 'Multi-select',  desc: 'Customer can pick multiple items' },
                                        { value: 'single', label: 'Single-select', desc: 'Customer picks exactly one item' },
                                    ].map(opt => (
                                        <label key={opt.value} className="relative flex cursor-pointer rounded-lg border border-slate-200 bg-slate-50 p-3 hover:border-orange-300 transition-colors has-[:checked]:border-orange-400 has-[:checked]:bg-orange-50 has-[:checked]:shadow-sm">
                                            <input type="radio" value={opt.value} {...register('selection_type')} className="sr-only" />
                                            <div>
                                                <p className="text-sm font-semibold text-slate-800">{opt.label}</p>
                                                <p className="text-xs text-slate-400 mt-0.5">{opt.desc}</p>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Sort Order</Label>
                                <Input type="number" min="0" {...register('sort_order')}
                                    className="mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all" />
                                <p className="mt-1 text-xs text-slate-400">Lower numbers appear first.</p>
                            </div>

                            <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                                <input id="is_active" type="checkbox" {...register('is_active')} className="h-4 w-4 rounded border-slate-300 accent-orange-500" />
                                <Label htmlFor="is_active" className="text-sm font-normal text-slate-600 cursor-pointer">Active — shown in ordering flow</Label>
                            </div>

                            <div className="flex gap-3 pt-1">
                                <Button type="button" variant="outline" className="flex-1" onClick={() => router.visit('/admin/catalogue/addon-groups')}>Cancel</Button>
                                <Button type="submit" disabled={loading} className={cn('flex-1 bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20', loading && 'opacity-80')}>
                                    {loading ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Saving…</> : 'Save Changes'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
