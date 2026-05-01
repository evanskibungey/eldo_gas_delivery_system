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
    size: {
        id: number; name: string; weight_kg: number;
        sort_order: number; is_commercial: boolean; is_active: boolean;
    };
}

const schema = z.object({
    name:          z.string().min(1, 'Name is required').max(20),
    weight_kg:     z.coerce.number().min(0.1, 'Weight must be greater than 0'),
    sort_order:    z.coerce.number().int().min(0),
    is_commercial: z.boolean(),
    is_active:     z.boolean(),
});

type FormData = z.infer<typeof schema>;

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function SizesEdit({ size }: Props) {
    const [loading, setLoading] = useState(false);

    const { register, handleSubmit, setError, formState: { errors } } = useForm<FormData>({
        resolver: zodResolver(schema),
        defaultValues: {
            name: size.name, weight_kg: size.weight_kg,
            sort_order: size.sort_order, is_commercial: size.is_commercial, is_active: size.is_active,
        },
    });

    function onSubmit(data: FormData) {
        setLoading(true);
        router.put(`/admin/catalogue/sizes/${size.id}`, data, {
            onError: (errs) => {
                setLoading(false);
                Object.entries(errs).forEach(([f, m]) => setError(f as keyof FormData, { message: m as string }));
            },
            onFinish: () => setLoading(false),
        });
    }

    const inputCls = (err?: { message?: string }) => cn(
        'mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm',
        'focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all',
        err && 'border-red-400 bg-red-50',
    );

    return (
        <AdminLayout title={`Edit ${size.name}`} subtitle="Update cylinder size details">
            <div className="mb-6">
                <Link href="/admin/catalogue/sizes" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <ArrowLeft className="h-4 w-4" /> Back to Cylinder Sizes
                </Link>
            </div>

            <div className="max-w-lg">
                <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                    <div className="p-6">
                        <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

                            <div>
                                <Label htmlFor="name" className="text-sm font-medium text-slate-700">Size Name</Label>
                                <Input id="name" {...register('name')} className={inputCls(errors.name)} />
                                <FieldError message={errors.name?.message} />
                            </div>

                            <div>
                                <Label htmlFor="weight_kg" className="text-sm font-medium text-slate-700">Weight (kg)</Label>
                                <Input id="weight_kg" type="number" step="0.1" {...register('weight_kg')} className={inputCls(errors.weight_kg)} />
                                <FieldError message={errors.weight_kg?.message} />
                            </div>

                            <div>
                                <Label htmlFor="sort_order" className="text-sm font-medium text-slate-700">Sort Order</Label>
                                <Input id="sort_order" type="number" min="0" {...register('sort_order')} className={inputCls(errors.sort_order)} />
                                <p className="mt-1 text-xs text-slate-400">Lower numbers appear first in ordering screens.</p>
                            </div>

                            <div className="space-y-2 pt-1">
                                <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                                    <input id="is_commercial" type="checkbox" {...register('is_commercial')} className="h-4 w-4 rounded border-slate-300 accent-orange-500" />
                                    <Label htmlFor="is_commercial" className="text-sm font-normal text-slate-600 cursor-pointer">Commercial size</Label>
                                </div>

                                <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                                    <input id="is_active" type="checkbox" {...register('is_active')} className="h-4 w-4 rounded border-slate-300 accent-orange-500" />
                                    <Label htmlFor="is_active" className="text-sm font-normal text-slate-600 cursor-pointer">Active â€” available for ordering</Label>
                                </div>
                            </div>

                            <div className="flex gap-3 pt-1">
                                <Button type="button" variant="outline" className="flex-1" onClick={() => router.visit('/admin/catalogue/sizes')}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={loading} className={cn('flex-1 bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20', loading && 'opacity-80')}>
                                    {loading ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Savingâ€¦</> : 'Save Changes'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
