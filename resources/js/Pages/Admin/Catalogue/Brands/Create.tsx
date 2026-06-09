import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, ImageOff, Loader2, Upload, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useState, useRef } from 'react';

interface Props {
    sizes: { id: number; name: string }[];
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function BrandsCreate({ sizes }: Props) {
    const [loading, setLoading]           = useState(false);
    const [name, setName]                 = useState('');
    const [isActive, setIsActive]         = useState(true);
    const [selectedSizes, setSelectedSizes] = useState<number[]>(sizes.map(s => s.id));
    const [logoFile, setLogoFile]         = useState<File | null>(null);
    const [logoPreview, setLogoPreview]   = useState<string | null>(null);
    const [sizeImages, setSizeImages]     = useState<Record<number, File>>({});
    const [sizePreviews, setSizePreviews] = useState<Record<number, string>>({});
    const [errors, setErrors]             = useState<Record<string, string>>({});
    const logoRef                         = useRef<HTMLInputElement>(null);

    function toggleSize(id: number) {
        setSelectedSizes(prev => {
            if (prev.includes(id)) {
                setSizeImages(p => { const n = { ...p }; delete n[id]; return n; });
                setSizePreviews(p => { const n = { ...p }; delete n[id]; return n; });
                return prev.filter(x => x !== id);
            }
            return [...prev, id];
        });
    }

    function handleLogo(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        setLogoFile(file);
        setLogoPreview(URL.createObjectURL(file));
    }

    function handleSizeImage(sizeId: number, e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        setSizeImages(p => ({ ...p, [sizeId]: file }));
        setSizePreviews(p => ({ ...p, [sizeId]: URL.createObjectURL(file) }));
        e.target.value = '';
    }

    function clearSizeImage(sizeId: number) {
        setSizeImages(p => { const n = { ...p }; delete n[sizeId]; return n; });
        setSizePreviews(p => { const n = { ...p }; delete n[sizeId]; return n; });
    }

    function onSubmit(e: React.FormEvent) {
        e.preventDefault();
        setLoading(true);
        const fd = new FormData();
        fd.append('name', name);
        fd.append('is_active', isActive ? '1' : '0');
        selectedSizes.forEach(id => fd.append('size_ids[]', String(id)));
        if (logoFile) fd.append('logo', logoFile);
        Object.entries(sizeImages).forEach(([sizeId, file]) => fd.append(`size_images[${sizeId}]`, file));

        router.post('/admin/catalogue/brands', fd, {
            onError: (errs) => { setLoading(false); setErrors(errs as Record<string, string>); },
            onFinish: () => setLoading(false),
        });
    }

    const selectedSizeObjects = sizes.filter(s => selectedSizes.includes(s.id));

    return (
        <AdminLayout title="Add Gas Brand" subtitle="Add a new LPG brand to the catalogue">
            <div className="mb-6">
                <Link href="/admin/catalogue/brands" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <ArrowLeft className="h-4 w-4" /> Back to Brands
                </Link>
            </div>

            <div className="max-w-lg">
                <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                    <div className="p-6">
                        <form onSubmit={onSubmit} className="space-y-5">

                            {/* Brand name */}
                            <div>
                                <Label className="text-sm font-medium text-slate-700">Brand Name</Label>
                                <Input
                                    placeholder="e.g. Total"
                                    value={name}
                                    onChange={e => setName(e.target.value)}
                                    className={cn('mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all', errors.name && 'border-red-400 bg-red-50')}
                                />
                                <FieldError message={errors.name} />
                            </div>

                            {/* Brand logo */}
                            <div>
                                <Label className="text-sm font-medium text-slate-700">
                                    Brand Logo <span className="text-slate-400 font-normal">(optional)</span>
                                </Label>
                                <div
                                    onClick={() => logoRef.current?.click()}
                                    className={cn(
                                        'mt-1.5 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-200 bg-slate-50 p-6 transition-colors hover:border-orange-300 hover:bg-orange-50/30',
                                        errors.logo && 'border-red-300 bg-red-50/30',
                                    )}
                                >
                                    {logoPreview ? (
                                        <div className="relative">
                                            <img src={logoPreview} alt="preview" className="h-20 w-20 rounded-lg object-contain" />
                                            <button
                                                type="button"
                                                onClick={e => { e.stopPropagation(); setLogoFile(null); setLogoPreview(null); }}
                                                className="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow"
                                            >
                                                <X className="h-3 w-3" />
                                            </button>
                                        </div>
                                    ) : (
                                        <>
                                            <Upload className="h-7 w-7 text-slate-300" />
                                            <p className="mt-2 text-xs text-slate-500">Click to upload logo</p>
                                            <p className="text-[10px] text-slate-400 mt-0.5">JPG, PNG, WebP · max 1 MB</p>
                                        </>
                                    )}
                                </div>
                                <input ref={logoRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={handleLogo} className="hidden" />
                                <FieldError message={errors.logo} />
                            </div>

                            {/* Available in sizes */}
                            <div>
                                <Label className="text-sm font-medium text-slate-700">Available In</Label>
                                <p className="mt-0.5 mb-2 text-xs text-slate-400">Select which cylinder sizes this brand is sold in.</p>
                                <div className="grid grid-cols-3 gap-2">
                                    {sizes.map(s => (
                                        <label
                                            key={s.id}
                                            className={cn(
                                                'flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm transition-colors',
                                                selectedSizes.includes(s.id)
                                                    ? 'border-orange-300 bg-orange-50 text-orange-700 font-medium shadow-sm'
                                                    : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300',
                                            )}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selectedSizes.includes(s.id)}
                                                onChange={() => toggleSize(s.id)}
                                                className="h-3.5 w-3.5 accent-orange-500"
                                            />
                                            {s.name}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {/* Per-size cylinder images */}
                            {selectedSizeObjects.length > 0 && (
                                <div>
                                    <Label className="text-sm font-medium text-slate-700">Cylinder Images per Size</Label>
                                    <p className="mt-0.5 mb-3 text-xs text-slate-400">
                                        Upload the specific cylinder photo for each size. Customers will see this image when selecting that size.
                                    </p>
                                    <div className="space-y-2">
                                        {selectedSizeObjects.map(s => (
                                            <div key={s.id} className="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                                                <span className="w-10 shrink-0 text-center text-xs font-semibold text-slate-600 bg-slate-200 rounded px-1.5 py-0.5">
                                                    {s.name}
                                                </span>
                                                {sizePreviews[s.id] ? (
                                                    <div className="relative">
                                                        <img
                                                            src={sizePreviews[s.id]}
                                                            alt={s.name}
                                                            className="h-12 w-12 rounded-md object-contain border border-slate-200 bg-white"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => clearSizeImage(s.id)}
                                                            className="absolute -right-1.5 -top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-white shadow"
                                                        >
                                                            <X className="h-2.5 w-2.5" />
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border border-dashed border-slate-300 bg-white">
                                                        <ImageOff className="h-4 w-4 text-slate-300" />
                                                    </div>
                                                )}
                                                <label className="flex cursor-pointer items-center gap-1.5 text-xs text-orange-600 hover:text-orange-700 font-medium">
                                                    <Upload className="h-3.5 w-3.5" />
                                                    {sizePreviews[s.id] ? 'Replace' : 'Upload'}
                                                    <input
                                                        type="file"
                                                        accept="image/jpeg,image/png,image/webp"
                                                        onChange={e => handleSizeImage(s.id, e)}
                                                        className="hidden"
                                                    />
                                                </label>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Active toggle */}
                            <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                                <input id="is_active" type="checkbox" checked={isActive} onChange={e => setIsActive(e.target.checked)} className="h-4 w-4 rounded border-slate-300 accent-orange-500" />
                                <Label htmlFor="is_active" className="text-sm font-normal text-slate-600 cursor-pointer">Active — visible in ordering flow</Label>
                            </div>

                            <div className="flex gap-3 pt-1">
                                <Button type="button" variant="outline" className="flex-1" onClick={() => router.visit('/admin/catalogue/brands')}>Cancel</Button>
                                <Button type="submit" disabled={loading} className={cn('flex-1 bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20', loading && 'opacity-80')}>
                                    {loading ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Saving…</> : 'Add Brand'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
