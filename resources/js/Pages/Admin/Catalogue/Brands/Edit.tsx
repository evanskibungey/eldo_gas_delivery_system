import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Loader2, Upload, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useState, useRef } from 'react';

interface Props {
    brand: { id: number; name: string; logo_url: string | null; is_active: boolean; size_ids: number[] };
    sizes: { id: number; name: string }[];
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function BrandsEdit({ brand, sizes }: Props) {
    const [loading, setLoading]             = useState(false);
    const [name, setName]                   = useState(brand.name);
    const [isActive, setIsActive]           = useState(brand.is_active);
    const [selectedSizes, setSelectedSizes] = useState<number[]>(brand.size_ids.map(Number));
    const [logoFile, setLogoFile]           = useState<File | null>(null);
    const [logoPreview, setLogoPreview]     = useState<string | null>(brand.logo_url);
    const [errors, setErrors]               = useState<Record<string, string>>({});
    const fileRef                           = useRef<HTMLInputElement>(null);

    function toggleSize(id: number) {
        setSelectedSizes(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
    }

    function handleLogo(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        setLogoFile(file);
        setLogoPreview(URL.createObjectURL(file));
    }

    function onSubmit(e: React.FormEvent) {
        e.preventDefault();
        setLoading(true);
        const fd = new FormData();
        fd.append('_method', 'PUT');
        fd.append('name', name);
        fd.append('is_active', isActive ? '1' : '0');
        selectedSizes.forEach(id => fd.append('size_ids[]', String(id)));
        if (logoFile) fd.append('logo', logoFile);

        router.post(`/admin/catalogue/brands/${brand.id}`, fd, {
            onError: (errs) => { setLoading(false); setErrors(errs as Record<string, string>); },
            onFinish: () => setLoading(false),
        });
    }

    return (
        <AdminLayout title={`Edit ${brand.name}`} subtitle="Update brand details and availability">
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

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Brand Name</Label>
                                <Input
                                    value={name}
                                    onChange={e => setName(e.target.value)}
                                    className={cn('mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all', errors.name && 'border-red-400 bg-red-50')}
                                />
                                <FieldError message={errors.name} />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">
                                    Brand Logo <span className="text-slate-400 font-normal">(optional)</span>
                                </Label>
                                <div
                                    onClick={() => fileRef.current?.click()}
                                    className="mt-1.5 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-200 bg-slate-50 p-6 transition-colors hover:border-orange-300 hover:bg-orange-50/30"
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
                                            <p className="mt-2 text-xs text-slate-500">Click to upload a new logo</p>
                                            <p className="text-[10px] text-slate-400 mt-0.5">JPG, PNG, WebP · max 1 MB</p>
                                        </>
                                    )}
                                </div>
                                <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={handleLogo} className="hidden" />
                            </div>

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

                            <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                                <input id="is_active" type="checkbox" checked={isActive} onChange={e => setIsActive(e.target.checked)} className="h-4 w-4 rounded border-slate-300 accent-orange-500" />
                                <Label htmlFor="is_active" className="text-sm font-normal text-slate-600 cursor-pointer">Active — visible in ordering flow</Label>
                            </div>

                            <div className="flex gap-3 pt-1">
                                <Button type="button" variant="outline" className="flex-1" onClick={() => router.visit('/admin/catalogue/brands')}>Cancel</Button>
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
