import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Loader2, Upload, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';
import { useState, useRef } from 'react';

interface Props {
    sizes:            { id: number; name: string; groups: { id: number; name: string }[] }[];
    default_group_id: number;
}

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

export default function CreateItem({ sizes, default_group_id }: Props) {
    const [loading, setLoading]     = useState(false);
    const [groupId, setGroupId]     = useState<number>(default_group_id);
    const [name, setName]           = useState('');
    const [description, setDesc]    = useState('');
    const [price, setPrice]         = useState('0');
    const [sortOrder, setSortOrder] = useState('0');
    const [isActive, setIsActive]   = useState(true);
    const [photoFile, setPhotoFile] = useState<File | null>(null);
    const [photoPreview, setPreview]= useState<string | null>(null);
    const [errors, setErrors]       = useState<Record<string, string>>({});
    const fileRef                   = useRef<HTMLInputElement>(null);

    const allGroups = sizes.flatMap(s => s.groups.map(g => ({ ...g, sizeName: s.name })));

    function handlePhoto(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        setPhotoFile(file);
        setPreview(URL.createObjectURL(file));
    }

    function onSubmit(e: React.FormEvent) {
        e.preventDefault();
        setLoading(true);
        const fd = new FormData();
        fd.append('group_id', String(groupId));
        fd.append('name', name);
        fd.append('description', description);
        fd.append('price', price);
        fd.append('sort_order', sortOrder);
        fd.append('is_active', isActive ? '1' : '0');
        if (photoFile) fd.append('photo', photoFile);

        router.post('/admin/catalogue/addon-items', fd, {
            onError: (errs) => { setLoading(false); setErrors(errs as Record<string, string>); },
            onFinish: () => setLoading(false),
        });
    }

    const inputCls = (key: string) => cn(
        'mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all',
        errors[key] && 'border-red-400 bg-red-50',
    );

    return (
        <AdminLayout title="Add Add-on Item" subtitle="Add an item to an existing add-on group">
            <div className="mb-6">
                <Link href="/admin/catalogue/addon-groups" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <ArrowLeft className="h-4 w-4" /> Back to Add-ons
                </Link>
            </div>

            <div className="max-w-lg">
                <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                    <div className="p-6">
                        <form onSubmit={onSubmit} className="space-y-5">

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Add-on Group</Label>
                                <select
                                    value={groupId}
                                    onChange={e => setGroupId(Number(e.target.value))}
                                    className={cn('mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20 focus:bg-white transition-all', errors.group_id && 'border-red-400 bg-red-50')}
                                >
                                    <option value={0}>Select a groupâ€¦</option>
                                    {allGroups.map(g => (
                                        <option key={g.id} value={g.id}>{g.sizeName} â€” {g.name}</option>
                                    ))}
                                </select>
                                <FieldError message={errors.group_id} />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Item Name</Label>
                                <Input placeholder="e.g. Single Stage Regulator" value={name} onChange={e => setName(e.target.value)} className={inputCls('name')} />
                                <FieldError message={errors.name} />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">
                                    Description <span className="text-slate-400 font-normal">(optional)</span>
                                </Label>
                                <Input placeholder="Brief description shown to customer" value={description} onChange={e => setDesc(e.target.value)} className={inputCls('description')} />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Price (KES)</Label>
                                <div className="relative mt-1.5">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-400">KES</span>
                                    <Input type="number" min="0" value={price} onChange={e => setPrice(e.target.value)} className={cn('h-10 pl-12 border-slate-200 bg-slate-50 text-sm focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all', errors.price && 'border-red-400 bg-red-50')} />
                                </div>
                                <p className="mt-1 text-xs text-slate-400">Enter 0 for free items.</p>
                                <FieldError message={errors.price} />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">
                                    Photo <span className="text-slate-400 font-normal">(optional)</span>
                                </Label>
                                <div
                                    onClick={() => fileRef.current?.click()}
                                    className="mt-1.5 flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-200 bg-slate-50 p-5 transition-colors hover:border-orange-300 hover:bg-orange-50/30"
                                >
                                    {photoPreview ? (
                                        <div className="relative">
                                            <img src={photoPreview} alt="preview" className="h-20 w-20 rounded-lg object-cover" />
                                            <button
                                                type="button"
                                                onClick={e => { e.stopPropagation(); setPhotoFile(null); setPreview(null); }}
                                                className="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow"
                                            >
                                                <X className="h-3 w-3" />
                                            </button>
                                        </div>
                                    ) : (
                                        <>
                                            <Upload className="h-7 w-7 text-slate-300" />
                                            <p className="mt-2 text-xs text-slate-500">Click to upload</p>
                                            <p className="text-[10px] text-slate-400 mt-0.5">JPG, PNG, WebP Â· max 2 MB</p>
                                        </>
                                    )}
                                </div>
                                <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={handlePhoto} className="hidden" />
                                <FieldError message={errors.photo} />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-slate-700">Sort Order</Label>
                                <Input type="number" min="0" value={sortOrder} onChange={e => setSortOrder(e.target.value)}
                                    className="mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all" />
                                <p className="mt-1 text-xs text-slate-400">Lower numbers appear first.</p>
                            </div>

                            <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                                <input id="is_active" type="checkbox" checked={isActive} onChange={e => setIsActive(e.target.checked)} className="h-4 w-4 rounded border-slate-300 accent-orange-500" />
                                <Label htmlFor="is_active" className="text-sm font-normal text-slate-600 cursor-pointer">Active â€” shown in ordering flow</Label>
                            </div>

                            <div className="flex gap-3 pt-1">
                                <Button type="button" variant="outline" className="flex-1" onClick={() => router.visit('/admin/catalogue/addon-groups')}>Cancel</Button>
                                <Button type="submit" disabled={loading} className={cn('flex-1 bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20', loading && 'opacity-80')}>
                                    {loading ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Savingâ€¦</> : 'Add Item'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
