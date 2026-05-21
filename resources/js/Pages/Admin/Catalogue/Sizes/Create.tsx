import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ArrowLeft, Loader2, ImagePlus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useRef, useState } from 'react';

const schema = z.object({
    name:          z.string().min(1, 'Name is required').max(20),
    weight_kg:     z.coerce.number().min(0.1, 'Weight must be greater than 0'),
    sort_order:    z.coerce.number().int().min(0).default(0),
    is_commercial: z.boolean().default(false),
    is_active:     z.boolean().default(true),
});

type FormData = z.infer<typeof schema>;

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function SizesCreate() {
    const [loading, setLoading]       = useState(false);
    const [imageFile, setImageFile]   = useState<File | null>(null);
    const [imagePreview, setPreview]  = useState<string | null>(null);
    const fileInputRef                = useRef<HTMLInputElement>(null);

    const { register, handleSubmit, setError, formState: { errors } } = useForm<FormData>({
        resolver: zodResolver(schema),
        defaultValues: { name: '', weight_kg: 0, sort_order: 0, is_commercial: false, is_active: true },
    });

    function pickImage(file: File) {
        setImageFile(file);
        setPreview(URL.createObjectURL(file));
    }

    function clearImage() {
        setImageFile(null);
        setPreview(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    }

    function onSubmit(data: FormData) {
        setLoading(true);
        router.post('/admin/catalogue/sizes', { ...data, image: imageFile ?? undefined } as never, {
            forceFormData: true,
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
        <AdminLayout title="Add Cylinder Size" subtitle="Define a new gas cylinder size">
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

                            {/* ── Image upload ── */}
                            <div>
                                <Label className="text-sm font-medium text-slate-700">
                                    Cylinder Image <span className="text-slate-400 font-normal">(optional)</span>
                                </Label>

                                {imagePreview ? (
                                    <div className="mt-2 relative w-fit">
                                        <img
                                            src={imagePreview}
                                            alt="Preview"
                                            className="h-32 w-32 rounded-xl object-cover border border-slate-200 shadow-sm"
                                        />
                                        <button
                                            type="button"
                                            onClick={clearImage}
                                            className="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white shadow hover:bg-red-600 transition-colors"
                                        >
                                            <X className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="mt-2 flex h-32 w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 text-slate-400 transition-colors hover:border-orange-300 hover:bg-orange-50 hover:text-orange-500"
                                    >
                                        <ImagePlus className="h-5 w-5" />
                                        <span className="text-sm">Click to upload image</span>
                                    </button>
                                )}

                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    className="hidden"
                                    onChange={(e) => {
                                        const f = e.target.files?.[0];
                                        if (f) pickImage(f);
                                    }}
                                />
                                <p className="mt-1.5 text-xs text-slate-400">JPEG, PNG or WebP · max 2 MB</p>
                            </div>

                            <div>
                                <Label htmlFor="name" className="text-sm font-medium text-slate-700">Size Name</Label>
                                <Input id="name" placeholder="e.g. 13kg" {...register('name')} className={inputCls(errors.name)} />
                                <FieldError message={errors.name?.message} />
                            </div>

                            <div>
                                <Label htmlFor="weight_kg" className="text-sm font-medium text-slate-700">Weight (kg)</Label>
                                <Input id="weight_kg" type="number" step="0.1" placeholder="13.0" {...register('weight_kg')} className={inputCls(errors.weight_kg)} />
                                <FieldError message={errors.weight_kg?.message} />
                            </div>

                            <div>
                                <Label htmlFor="sort_order" className="text-sm font-medium text-slate-700">Sort Order</Label>
                                <Input id="sort_order" type="number" min="0" placeholder="0" {...register('sort_order')} className={inputCls(errors.sort_order)} />
                                <p className="mt-1 text-xs text-slate-400">Lower numbers appear first in ordering screens.</p>
                            </div>

                            <div className="space-y-2 pt-1">
                                <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                                    <input id="is_commercial" type="checkbox" {...register('is_commercial')} className="h-4 w-4 rounded border-slate-300 accent-orange-500" />
                                    <Label htmlFor="is_commercial" className="text-sm font-normal text-slate-600 cursor-pointer">
                                        Commercial size <span className="text-slate-400">(25 kg, 50 kg — shown separately)</span>
                                    </Label>
                                </div>

                                <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                                    <input id="is_active" type="checkbox" {...register('is_active')} className="h-4 w-4 rounded border-slate-300 accent-orange-500" />
                                    <Label htmlFor="is_active" className="text-sm font-normal text-slate-600 cursor-pointer">
                                        Active — available for ordering
                                    </Label>
                                </div>
                            </div>

                            <div className="flex gap-3 pt-1">
                                <Button type="button" variant="outline" className="flex-1" onClick={() => router.visit('/admin/catalogue/sizes')}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={loading} className={cn('flex-1 bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20', loading && 'opacity-80')}>
                                    {loading ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Saving…</> : 'Create Size'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
