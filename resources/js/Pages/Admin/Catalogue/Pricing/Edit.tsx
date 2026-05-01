import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ArrowLeft, Loader2, AlertCircle } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface Props {
    size:  { id: number; name: string };
    price: { gas_refill_price: number; new_cylinder_price: number; new_gas_fill_price: number; delivery_fee: number };
}

const schema = z.object({
    gas_refill_price:   z.coerce.number().int().min(0, 'Must be 0 or more'),
    new_cylinder_price: z.coerce.number().int().min(0, 'Must be 0 or more'),
    new_gas_fill_price: z.coerce.number().int().min(0, 'Must be 0 or more'),
    delivery_fee:       z.coerce.number().int().min(0, 'Must be 0 or more'),
});

type FormData = z.infer<typeof schema>;

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

function PriceField({ id, label, hint, register, error }: {
    id:       keyof FormData;
    label:    string;
    hint:     string;
    register: ReturnType<typeof useForm<FormData>>['register'];
    error?:   { message?: string };
}) {
    return (
        <div>
            <Label htmlFor={id} className="text-sm font-medium text-slate-700">{label}</Label>
            <p className="mt-0.5 mb-1.5 text-xs text-slate-400">{hint}</p>
            <div className="relative">
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-400">KES</span>
                <Input
                    id={id}
                    type="number"
                    min="0"
                    step="1"
                    {...register(id)}
                    className={cn(
                        'h-10 pl-12 border-slate-200 bg-slate-50 text-sm',
                        'focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all',
                        error && 'border-red-400 bg-red-50',
                    )}
                />
            </div>
            <FieldError message={error?.message} />
        </div>
    );
}

export default function PricingEdit({ size, price }: Props) {
    const [loading, setLoading] = useState(false);

    const { register, handleSubmit, setError, formState: { errors } } = useForm<FormData>({
        resolver: zodResolver(schema),
        defaultValues: price,
    });

    function onSubmit(data: FormData) {
        setLoading(true);
        router.put(`/admin/catalogue/pricing/${size.id}`, data, {
            onError: (errs) => {
                setLoading(false);
                Object.entries(errs).forEach(([f, m]) => setError(f as keyof FormData, { message: m as string }));
            },
            onFinish: () => setLoading(false),
        });
    }

    return (
        <AdminLayout title={`Edit Prices — ${size.name}`} subtitle="Update pricing for this cylinder size">
            <div className="mb-6">
                <Link href="/admin/catalogue/pricing" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <ArrowLeft className="h-4 w-4" /> Back to Pricing
                </Link>
            </div>

            <div className="mb-5 flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 max-w-xl">
                <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                <p className="text-sm text-amber-700">
                    Changes take effect immediately. Every update is logged with your name and a timestamp.
                </p>
            </div>

            <div className="max-w-xl">
                <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                    <div className="p-6">
                        <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

                            <PriceField id="gas_refill_price"   label="Swap / Refill Price"     hint="Customer brings empty cylinder, gets filled one." register={register} error={errors.gas_refill_price} />
                            <PriceField id="new_cylinder_price" label="New Cylinder Price"       hint="Cost of the physical cylinder hardware." register={register} error={errors.new_cylinder_price} />
                            <PriceField id="new_gas_fill_price" label="Gas Fill (New Cylinder)" hint="Cost of filling a brand-new cylinder with gas." register={register} error={errors.new_gas_fill_price} />
                            <PriceField id="delivery_fee"       label="Delivery Fee"             hint="Flat delivery charge added to every order." register={register} error={errors.delivery_fee} />

                            <div className="flex gap-3 pt-1">
                                <Button type="button" variant="outline" className="flex-1" onClick={() => router.visit('/admin/catalogue/pricing')}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={loading} className={cn('flex-1 bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20', loading && 'opacity-80')}>
                                    {loading ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Saving…</> : 'Update Prices'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
