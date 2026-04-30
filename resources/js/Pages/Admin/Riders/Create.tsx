import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Loader2, Upload, X, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useState, useRef } from 'react';

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function RidersCreate() {
    const [loading, setLoading]           = useState(false);
    const [name, setName]                 = useState('');
    const [phone, setPhone]               = useState('');
    const [nationalId, setNationalId]     = useState('');
    const [isCertified, setIsCertified]   = useState(false);
    const [certDate, setCertDate]         = useState('');
    const [isActive, setIsActive]         = useState(true);
    const [photoFile, setPhotoFile]       = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const [errors, setErrors]             = useState<Record<string, string>>({});
    const fileRef                         = useRef<HTMLInputElement>(null);

    function handlePhoto(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        setPhotoFile(file);
        setPhotoPreview(URL.createObjectURL(file));
    }

    function onSubmit(e: React.FormEvent) {
        e.preventDefault();
        setLoading(true);
        const fd = new FormData();
        fd.append('name',                name);
        fd.append('phone',               phone);
        fd.append('national_id',         nationalId);
        fd.append('is_safety_certified', isCertified ? '1' : '0');
        if (isCertified) fd.append('certification_date', certDate);
        fd.append('is_active', isActive ? '1' : '0');
        if (photoFile) fd.append('photo', photoFile);

        router.post('/admin/riders', fd, {
            onError:  (errs) => { setLoading(false); setErrors(errs as Record<string, string>); },
            onFinish: () => setLoading(false),
        });
    }

    const inputCls = (key: string) => cn(
        'mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all',
        errors[key] && 'border-red-400 bg-red-50',
    );

    return (
        <AdminLayout title="Add Rider" subtitle="Register a new delivery rider">
            <div className="mb-6">
                <Link href="/admin/riders" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <ArrowLeft className="h-4 w-4" /> Back to Riders
                </Link>
            </div>

            <div className="max-w-lg">
                <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                    <div className="p-6">
                        <form onSubmit={onSubmit} className="space-y-5">

                            {/* Photo */}
                            <div>
                                <Label className="text-sm font-medium text-slate-700">
                                    Profile Photo <span className="text-slate-400 font-normal">(optional)</span>
                                </Label>
                                <div
                                    onClick={() => fileRef.current?.click()}
                                    className="mt-1.5 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-200 bg-slate-50 p-5 transition-colors hover:border-orange-300 hover:bg-orange-50/30"
                                >
                                    {photoPreview ? (
                                        <div className="relative">
                                            <img src={photoPreview} alt="preview" className="h-20 w-20 rounded-full object-cover border-2 border-white shadow" />
                                            <button type="button"
                                                onClick={e => { e.stopPropagation(); setPhotoFile(null); setPhotoPreview(null); }}
                                                className="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow">
                                                <X className="h-3 w-3" />
                                            </button>
                                        </div>
                                    ) : (
                                        <>
                                            <Upload className="h-7 w-7 text-slate-300" />
                                            <p className="mt-2 text-xs text-slate-500">Click to upload photo</p>
                                            <p className="text-[10px] text-slate-400 mt-0.5">JPG, PNG, WebP · max 2 MB</p>
                                        </>
                                    )}
                                </div>
                                <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={handlePhoto} className="hidden" />
                                <FieldError message={errors.photo} />
                            </div>

                            {/* Name & Phone */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label className="text-sm font-medium text-slate-700">Full Name</Label>
                                    <Input placeholder="e.g. John Kamau" value={name} onChange={e => setName(e.target.value)} className={inputCls('name')} />
                                    <FieldError message={errors.name} />
                                </div>
                                <div>
                                    <Label className="text-sm font-medium text-slate-700">Phone</Label>
                                    <Input placeholder="+254712345678" value={phone} onChange={e => setPhone(e.target.value)} className={inputCls('phone')} />
                                    <FieldError message={errors.phone} />
                                </div>
                            </div>

                            {/* National ID */}
                            <div>
                                <Label className="text-sm font-medium text-slate-700">
                                    National ID <span className="text-slate-400 font-normal">(optional)</span>
                                </Label>
                                <Input placeholder="e.g. 12345678" value={nationalId} onChange={e => setNationalId(e.target.value)} className={inputCls('national_id')} />
                                <FieldError message={errors.national_id} />
                            </div>

                            {/* Safety Certification */}
                            <div className="rounded-lg border border-slate-100 bg-slate-50 p-4 space-y-3">
                                <div className="flex items-center gap-3">
                                    <input id="is_certified" type="checkbox" checked={isCertified}
                                        onChange={e => { setIsCertified(e.target.checked); if (!e.target.checked) setCertDate(''); }}
                                        className="h-4 w-4 rounded border-slate-300 accent-orange-500" />
                                    <Label htmlFor="is_certified" className="flex items-center gap-1.5 text-sm font-normal text-slate-700 cursor-pointer">
                                        <ShieldCheck className="h-4 w-4 text-emerald-500" /> Safety Certified
                                    </Label>
                                </div>
                                {isCertified && (
                                    <div>
                                        <Label className="text-xs font-medium text-slate-600">Certification Date</Label>
                                        <Input type="date" value={certDate} onChange={e => setCertDate(e.target.value)}
                                            className={cn('mt-1 h-9 border-slate-200 bg-white text-sm focus:border-orange-400 focus:ring-orange-400/20 transition-all', errors.certification_date && 'border-red-400 bg-red-50')} />
                                        <p className="mt-1 text-xs text-slate-400">Certification is valid for 1 year from this date.</p>
                                        <FieldError message={errors.certification_date} />
                                    </div>
                                )}
                            </div>

                            {/* Active */}
                            <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                                <input id="is_active" type="checkbox" checked={isActive} onChange={e => setIsActive(e.target.checked)} className="h-4 w-4 rounded border-slate-300 accent-orange-500" />
                                <Label htmlFor="is_active" className="text-sm font-normal text-slate-600 cursor-pointer">
                                    Active — can receive deliveries
                                </Label>
                            </div>

                            <div className="flex gap-3 pt-1">
                                <Button type="button" variant="outline" className="flex-1" onClick={() => router.visit('/admin/riders')}>Cancel</Button>
                                <Button type="submit" disabled={loading} className={cn('flex-1 bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20', loading && 'opacity-80')}>
                                    {loading ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Saving…</> : 'Add Rider'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
