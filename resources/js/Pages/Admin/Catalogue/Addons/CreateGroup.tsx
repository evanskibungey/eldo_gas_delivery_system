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
    sizes:           { id: number; name: string }[];
    default_size_id: number;
}

const schema = z.object({
    size_id:        z.coerce.number().int().min(1, 'Select a cylinder size'),
    name:           z.string().min(1, 'Group name is required').max(100),
    selection_type: z.enum(['multi', 'single']),
    sort_order:     z.coerce.number().int().min(0).default(0),
    is_active:      z.boolean().default(true),
});

type FormData = z.infer<typeof schema>;

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function CreateGroup({ sizes, default_size_id }: Props) {
    const [loading, setLoading] = useState(false);

    const { register, handleSubmit, setError, formState: { errors } } = useForm<FormData>({
        resolver: zodResolver(schema),
        defaultValues: { size_id: default_size_id || sizes[0]?.id || 0, name: '', selection_type: 'multi', sort_order: 0, is_active: true },
    });

    function onSubmit(data: FormData) {
        setLoading(true);
        router.post('/admin/catalogue/addon-groups', data, {
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
        <AdminLayout title="Add Add-on Group" subtitle="Create a new option group for a cylinder size">
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
                                    <option value={0}>Select a sizeâ€¦</option>
                                    {sizes.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                                <FieldError message={errors.size_id?.message} />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Group Name</Label>
                                <Input placeholder="e.g. Regulator Options" {...register('name')}
                                    className={cn('mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all', errors.name && 'border-red-400 bg-red-50')} />
                                <FieldError message={errors.name?.message} />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Selection Type</Label>
                                <p className="mt-0.5 mb-2 text-xs text-slate-400">How many items can a customer pick from this group?</p>
                                <div className="grid grid-cols-2 gap-3">
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
                                <FieldError message={errors.selection_type?.message} />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Sort Order</Label>
                                <Input type="number" min="0" {...register('sort_order')}
                                    className="mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all" />
                                <p className="mt-1 text-xs text-slate-400">Lower numbers appear first.</p>
                            </div>

                            <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                                <input id="is_active" type="checkbox" {...register('is_active')} className="h-4 w-4 rounded border-slate-300 accent-orange-500" />
                                <Label htmlFor="is_active" className="text-sm font-normal text-slate-600 cursor-pointer">Active â€” shown in ordering flow</Label>
                            </div>

                            <div className="flex gap-3 pt-1">
                                <Button type="button" variant="outline" className="flex-1" onClick={() => router.visit('/admin/catalogue/addon-groups')}>Cancel</Button>
                                <Button type="submit" disabled={loading} className={cn('flex-1 bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20', loading && 'opacity-80')}>
                                    {loading ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Savingâ€¦</> : 'Create Group'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
