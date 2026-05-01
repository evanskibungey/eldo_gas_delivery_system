import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ArrowLeft, Loader2, AlertTriangle } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface Props {
    size:  { id: number; name: string };
    stock: { filled_count: number; empty_count: number; low_stock_threshold: number };
}

const schema = z.object({
    filled_count: z.coerce.number().int().min(0, 'Must be 0 or more'),
    empty_count:  z.coerce.number().int().min(0, 'Must be 0 or more'),
    threshold:    z.coerce.number().int().min(1).optional(),
    note:         z.string().max(500).optional(),
});

type FormData = z.infer<typeof schema>;

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function StockAdjust({ size, stock }: Props) {
    const [loading, setLoading] = useState(false);

    const { register, handleSubmit, setError, formState: { errors } } = useForm<FormData>({
        resolver: zodResolver(schema),
        defaultValues: {
            filled_count: stock.filled_count,
            empty_count:  stock.empty_count,
            threshold:    stock.low_stock_threshold,
            note:         '',
        },
    });

    function onSubmit(data: FormData) {
        setLoading(true);
        router.put(`/admin/stock/${size.id}`, data, {
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
        <AdminLayout title={`Adjust Stock â€” ${size.name}`} subtitle="Set current filled and empty cylinder counts">
            <div className="mb-6">
                <Link href="/admin/stock" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <ArrowLeft className="h-4 w-4" /> Back to Stock
                </Link>
            </div>

            {/* Current snapshot */}
            <div className="mb-5 grid grid-cols-3 gap-4 max-w-xl">
                {[
                    { label: 'Filled Now',   value: stock.filled_count,        color: 'text-slate-800' },
                    { label: 'Empty Now',    value: stock.empty_count,          color: 'text-slate-500' },
                    { label: 'Low Threshold', value: stock.low_stock_threshold, color: 'text-amber-600' },
                ].map(({ label, value, color }) => (
                    <div key={label} className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
                        <p className={cn('mt-1 text-2xl font-bold tabular-nums', color)}>{value}</p>
                    </div>
                ))}
            </div>

            <div className="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 mb-5 max-w-xl">
                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                <p className="text-sm text-amber-700">
                    Setting filled count to 0 will automatically deactivate this cylinder size until stock is restored.
                </p>
            </div>

            <div className="max-w-xl">
                <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                    <div className="p-6">
                        <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="filled_count" className="text-sm font-medium text-slate-700">Filled Cylinders</Label>
                                    <Input id="filled_count" type="number" min="0" {...register('filled_count')} className={inputCls(errors.filled_count)} />
                                    <FieldError message={errors.filled_count?.message} />
                                </div>

                                <div>
                                    <Label htmlFor="empty_count" className="text-sm font-medium text-slate-700">Empty Cylinders</Label>
                                    <Input id="empty_count" type="number" min="0" {...register('empty_count')} className={inputCls(errors.empty_count)} />
                                    <FieldError message={errors.empty_count?.message} />
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="threshold" className="text-sm font-medium text-slate-700">
                                    Low-Stock Threshold <span className="text-slate-400 font-normal">(optional update)</span>
                                </Label>
                                <Input id="threshold" type="number" min="1" {...register('threshold')} className={inputCls(errors.threshold)} />
                                <p className="mt-1 text-xs text-slate-400">Alert fires when filled count reaches or falls below this number.</p>
                                <FieldError message={errors.threshold?.message} />
                            </div>

                            <div>
                                <Label htmlFor="note" className="text-sm font-medium text-slate-700">
                                    Note <span className="text-slate-400 font-normal">(optional)</span>
                                </Label>
                                <textarea
                                    id="note"
                                    rows={3}
                                    {...register('note')}
                                    placeholder="Reason for adjustment (e.g. received stock delivery, stock count correction)"
                                    className="mt-1.5 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20 focus:bg-white transition-all resize-none"
                                />
                                <FieldError message={errors.note?.message} />
                            </div>

                            <div className="flex gap-3 pt-1">
                                <Button type="button" variant="outline" className="flex-1" onClick={() => router.visit('/admin/stock')}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={loading} className={cn('flex-1 bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20', loading && 'opacity-80')}>
                                    {loading ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Savingâ€¦</> : 'Save Adjustment'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
