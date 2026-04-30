import CustomerLayout from '@/Layouts/CustomerLayout';
import { router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import {
    MapPin, Crosshair, Loader2, ChevronDown, ChevronUp,
    AlertCircle, RefreshCcw, Package, Check,
    StickyNote,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface Size {
    id:            number;
    name:          string;
    weight_kg:     number;
    is_commercial: boolean;
    in_stock:      boolean;
    swap_price:    number | null;
    new_price:     number | null;
    delivery_fee:  number | null;
}

interface Brand {
    id:       number;
    name:     string;
    logo_url: string | null;
}

interface AddonItem {
    id:          number;
    name:        string;
    description: string | null;
    price:       number;
    photo_url:   string | null;
}

interface AddonGroup {
    id:             number;
    name:           string;
    selection_type: 'single' | 'multi';
    items:          AddonItem[];
}

interface Address {
    id:          number;
    label:       string;
    description: string | null;
    is_default:  boolean;
}

interface Props {
    sizes:           Size[];
    brands_by_size:  Record<string, Brand[]>;
    addons_by_size:  Record<string, AddonGroup[]>;
    addresses:       Address[];
    default_address: number | null;
    mpesa_till:      string;
    prefill:         { order_type: string; size_id: number; brand_id: number } | null;
}

const fmt = (n: number) => `KES ${n.toLocaleString()}`;

// ── Section header with step number + optional completion check ──────────────
function SectionHeader({
    step, label, done, locked, optional,
}: {
    step: number;
    label: string;
    done: boolean;
    locked?: boolean;
    optional?: boolean;
}) {
    return (
        <div className="flex items-center gap-2.5 px-4 pt-4 pb-2">
            <span className={cn(
                'flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold transition-all duration-200',
                done    ? 'bg-emerald-500 text-white'
                : locked ? 'bg-slate-200 text-slate-400'
                         : 'bg-orange-500 text-white',
            )}>
                {done ? <Check className="h-3 w-3" strokeWidth={3} /> : step}
            </span>
            <p className={cn(
                'text-[11px] font-semibold uppercase tracking-wider transition-colors duration-200',
                locked ? 'text-slate-300' : 'text-slate-500',
            )}>
                {label}
                {optional && (
                    <span className="ml-1 font-normal normal-case text-slate-300">(optional)</span>
                )}
            </p>
        </div>
    );
}

// ── Horizontal scroll row with right-side fade hint ──────────────────────────
function ScrollRow({ children, className }: { children: React.ReactNode; className?: string }) {
    return (
        <div className="relative">
            <div className={cn('flex gap-2 overflow-x-auto px-4 pb-4 snap-x scrollbar-none', className)}>
                {children}
            </div>
            {/* fade hint — indicates more chips to the right */}
            <div className="pointer-events-none absolute right-0 top-0 h-full w-8 bg-gradient-to-l from-white to-transparent" />
        </div>
    );
}

export default function OrderBuilder({
    sizes, brands_by_size, addons_by_size,
    addresses: initialAddresses, default_address, mpesa_till, prefill,
}: Props) {
    const [orderType,    setOrderType]    = useState<'swap' | 'new_cylinder'>(
        (prefill?.order_type as 'swap' | 'new_cylinder') ?? 'swap',
    );
    const [sizeId,       setSizeId]       = useState<number | null>(prefill?.size_id ?? null);
    const [brandId,      setBrandId]      = useState<number | null>(prefill?.brand_id ?? null);
    const [addonIds,     setAddonIds]     = useState<number[]>([]);
    const [addresses,    setAddresses]    = useState<Address[]>(initialAddresses);
    const [addressId,    setAddressId]    = useState<number | null>(
        default_address ?? (initialAddresses[0]?.id ?? null),
    );
    const [payMethod,    setPayMethod]    = useState('mpesa');
    const [notes,        setNotes]        = useState('');
    const [loading,      setLoading]      = useState(false);
    const [addonsOpen,   setAddonsOpen]   = useState(true);
    const [addrOpen,     setAddrOpen]     = useState(false);
    const [autoLocating, setAutoLocating] = useState(false);
    const [geoError,     setGeoError]     = useState('');
    const [errors,       setErrors]       = useState<Record<string, string>>({});

    const isSwap        = orderType === 'swap';
    const selectedSize  = sizes.find(s => s.id === sizeId);
    const brandsForSize = sizeId ? (brands_by_size[String(sizeId)] ?? []) : [];
    const addonsForSize = sizeId ? (addons_by_size[String(sizeId)] ?? []) : [];
    const chosenAddress = addresses.find(a => a.id === addressId);

    const basePrice   = selectedSize ? (isSwap ? (selectedSize.swap_price ?? 0) : (selectedSize.new_price ?? 0)) : 0;
    const deliveryFee = selectedSize?.delivery_fee ?? 0;

    const addonsTotal = useMemo(() => {
        const all = addonsForSize.flatMap(g => g.items);
        return addonIds.reduce((sum, id) => sum + (all.find(i => i.id === id)?.price ?? 0), 0);
    }, [addonIds, addonsForSize]);

    const total = basePrice + deliveryFee + addonsTotal;

    // Section completion states
    const sizesDone    = !! sizeId;
    const brandDone    = !! brandId;
    const addrDone     = !! addressId;

    function handleTypeChange(type: 'swap' | 'new_cylinder') {
        setOrderType(type);
        setAddonIds([]);
        setErrors({});
    }

    function handleSizeChange(id: number) {
        if (! sizes.find(s => s.id === id)?.in_stock) return;
        setSizeId(id);
        setBrandId(null);
        setAddonIds([]);
        setErrors(e => ({ ...e, size_id: '' }));
    }

    function handleBrandChange(id: number) {
        setBrandId(id);
        setErrors(e => ({ ...e, brand_id: '' }));
    }

    function toggleAddon(group: AddonGroup, itemId: number) {
        if (group.selection_type === 'single') {
            const inGroup = group.items.map(i => i.id);
            setAddonIds(prev => [
                ...prev.filter(id => ! inGroup.includes(id)),
                ...(prev.includes(itemId) ? [] : [itemId]),
            ]);
        } else {
            setAddonIds(prev =>
                prev.includes(itemId) ? prev.filter(id => id !== itemId) : [...prev, itemId],
            );
        }
    }

    async function detectLocation() {
        if (! navigator.geolocation) { setGeoError('Geolocation not supported by your browser.'); return; }
        setAutoLocating(true);
        setGeoError('');
        navigator.geolocation.getCurrentPosition(
            async pos => {
                const { latitude, longitude } = pos.coords;
                let label       = 'Current Location';
                let description = '';
                try {
                    const geo  = await fetch(
                        `https://nominatim.openstreetmap.org/reverse?lat=${latitude}&lon=${longitude}&format=json`,
                        { headers: { 'User-Agent': 'EldoGasApp/1.0' } },
                    );
                    const data = await geo.json();
                    const addr = (data.address ?? {}) as Record<string, string>;

                    label = addr.suburb
                        ?? addr.neighbourhood
                        ?? addr.village
                        ?? addr.town
                        ?? addr.city_district
                        ?? addr.city
                        ?? (data.display_name ?? '').split(',')[0].trim()
                        ?? 'Current Location';

                    description = (data.display_name ?? '').split(',').slice(0, 3).join(', ');
                } catch { /* silent — keep defaults */ }

                try {
                    const csrfToken = (document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1] ?? '';
                    const res = await fetch('/addresses/detect', {
                        method:  'POST',
                        headers: {
                            'Content-Type':  'application/json',
                            'Accept':        'application/json',
                            'X-XSRF-TOKEN':  decodeURIComponent(csrfToken),
                        },
                        body: JSON.stringify({ label, latitude, longitude, description }),
                    });

                    if (res.ok) {
                        const saved = await res.json() as Address;
                        setAddresses(prev => [...prev, saved]);
                        setAddressId(saved.id);
                        setAddrOpen(false);
                    } else {
                        setGeoError('Could not save location. Please try again.');
                    }
                } catch {
                    setGeoError('Could not save location. Please try again.');
                } finally {
                    setAutoLocating(false);
                }
            },
            () => {
                setAutoLocating(false);
                setGeoError('Could not detect your location. Please allow access or pin manually.');
            },
            { timeout: 10_000 },
        );
    }

    function submit() {
        const errs: Record<string, string> = {};
        if (! sizeId)    errs.size_id    = 'Please select a cylinder size.';
        if (! brandId)   errs.brand_id   = 'Please select a brand.';
        if (! addressId) errs.address_id = 'Please add a delivery address.';
        if (Object.keys(errs).length) { setErrors(errs); return; }

        setLoading(true);
        router.post('/order', {
            order_type:     orderType,
            size_id:        sizeId,
            brand_id:       brandId,
            addon_ids:      addonIds,
            address_id:     addressId,
            payment_method: payMethod,
            delivery_notes: notes || null,
        }, {
            onError:  errs => { setErrors(errs); setLoading(false); },
            onFinish: ()   => setLoading(false),
        });
    }

    const canSubmit = sizesDone && brandDone && addrDone && ! loading;

    return (
        <CustomerLayout title="Order Gas" showBack backHref="/home">
            {/* pb-36: space for sticky CTA + BottomNav */}
            <div className="mx-auto max-w-sm px-4 pt-4 pb-36 space-y-3">

                {/* ── Order type toggle ───────────────────────────────────── */}
                <div className="grid grid-cols-2 gap-1.5 rounded-2xl bg-slate-100 p-1.5">
                    {([
                        { value: 'swap',        label: 'Swap / Refill', icon: RefreshCcw },
                        { value: 'new_cylinder', label: 'New Cylinder',  icon: Package   },
                    ] as const).map(({ value, label, icon: Icon }) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => handleTypeChange(value)}
                            className={cn(
                                'flex items-center justify-center gap-2 rounded-xl py-3 text-sm font-semibold transition-all duration-200',
                                orderType === value
                                    ? 'bg-white text-orange-600 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-700',
                            )}
                        >
                            <Icon className="h-4 w-4" />
                            {label}
                        </button>
                    ))}
                </div>

                {/* ── 1. Cylinder size ────────────────────────────────────── */}
                <div className="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                    <SectionHeader step={1} label="Cylinder Size" done={sizesDone} />

                    <ScrollRow>
                        {sizes.map(s => {
                            const price = isSwap ? s.swap_price : s.new_price;
                            const isSel = sizeId === s.id;
                            return (
                                <button
                                    key={s.id}
                                    type="button"
                                    disabled={! s.in_stock}
                                    onClick={() => handleSizeChange(s.id)}
                                    className={cn(
                                        'snap-start shrink-0 flex flex-col items-center rounded-xl border-2 px-4 py-3 transition-all duration-150',
                                        ! s.in_stock && 'opacity-35 cursor-not-allowed border-slate-100 bg-slate-50',
                                        s.in_stock && isSel  && 'border-orange-400 bg-orange-50 shadow-sm',
                                        s.in_stock && ! isSel && 'border-slate-200 bg-white hover:border-orange-200',
                                    )}
                                >
                                    <span className={cn(
                                        'text-base font-bold leading-none',
                                        isSel ? 'text-orange-600' : 'text-slate-700',
                                    )}>
                                        {s.name}
                                    </span>
                                    {price != null ? (
                                        <span className={cn(
                                            'mt-1 text-[10px] font-medium',
                                            isSel ? 'text-orange-500' : 'text-slate-400',
                                        )}>
                                            {fmt(price)}
                                        </span>
                                    ) : (
                                        <span className="mt-1 text-[10px] text-slate-300">—</span>
                                    )}
                                    {! s.in_stock && (
                                        <span className="mt-1 text-[9px] font-medium text-slate-300">Out</span>
                                    )}
                                    {isSel && s.in_stock && (
                                        <span className="mt-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-orange-500">
                                            <Check className="h-2.5 w-2.5 text-white" strokeWidth={3} />
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </ScrollRow>

                    {(errors.size_id || selectedSize) && (
                        <p className={cn(
                            'px-4 pb-3 -mt-1 text-[11px]',
                            errors.size_id ? 'text-red-500' : 'text-slate-400',
                        )}>
                            {errors.size_id
                                ? errors.size_id
                                : `${isSwap ? 'Refill' : 'Cylinder + gas'}: ${fmt(basePrice)} · Delivery: ${fmt(deliveryFee)}`}
                        </p>
                    )}
                </div>

                {/* ── 2. Brand ────────────────────────────────────────────── */}
                <div className={cn(
                    'rounded-2xl bg-white border shadow-sm overflow-hidden transition-opacity duration-200',
                    sizesDone ? 'border-slate-100 opacity-100' : 'border-slate-100 opacity-40 pointer-events-none',
                )}>
                    <SectionHeader step={2} label="Brand" done={brandDone} locked={! sizesDone} />

                    {! sizesDone ? (
                        <p className="px-4 pb-4 text-xs text-slate-400">Select a size first</p>
                    ) : brandsForSize.length === 0 ? (
                        <p className="px-4 pb-4 text-sm text-slate-400">No brands available for this size.</p>
                    ) : (
                        <ScrollRow>
                            {brandsForSize.map(b => {
                                const isSel = brandId === b.id;
                                return (
                                    <button
                                        key={b.id}
                                        type="button"
                                        onClick={() => handleBrandChange(b.id)}
                                        className={cn(
                                            'snap-start shrink-0 flex flex-col items-center gap-1.5 rounded-xl border-2 px-3.5 py-3 transition-all duration-150 min-w-[68px]',
                                            isSel
                                                ? 'border-orange-400 bg-orange-50 shadow-sm'
                                                : 'border-slate-200 bg-white hover:border-orange-200',
                                        )}
                                    >
                                        {b.logo_url ? (
                                            <img src={b.logo_url} alt={b.name} className="h-10 w-10 rounded-full object-contain" />
                                        ) : (
                                            <div className={cn(
                                                'flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold',
                                                isSel ? 'bg-orange-100 text-orange-600' : 'bg-slate-100 text-slate-600',
                                            )}>
                                                {b.name.charAt(0)}
                                            </div>
                                        )}
                                        <span className={cn(
                                            'text-[11px] font-medium text-center leading-tight max-w-[60px] truncate',
                                            isSel ? 'text-orange-600' : 'text-slate-700',
                                        )}>
                                            {b.name}
                                        </span>
                                        {isSel && (
                                            <span className="flex h-4 w-4 items-center justify-center rounded-full bg-orange-500">
                                                <Check className="h-2.5 w-2.5 text-white" strokeWidth={3} />
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </ScrollRow>
                    )}

                    {errors.brand_id && (
                        <p className="px-4 pb-3 -mt-1 text-[11px] text-red-500">{errors.brand_id}</p>
                    )}
                </div>

                {/* ── 3. Add-ons ──────────────────────────────────────────── */}
                <div className={cn(
                    'rounded-2xl bg-white border shadow-sm overflow-hidden transition-opacity duration-200',
                    sizesDone ? 'border-slate-100 opacity-100' : 'border-slate-100 opacity-40 pointer-events-none',
                )}>
                    {/* Collapsible header */}
                    <button
                        type="button"
                        onClick={() => sizesDone && setAddonsOpen(o => ! o)}
                        className="w-full flex items-center justify-between pr-4 hover:bg-slate-50 transition-colors"
                        disabled={! sizesDone}
                    >
                        <SectionHeader step={3} label="Add-ons" done={addonIds.length > 0} locked={! sizesDone} optional />
                        <div className="flex items-center gap-1.5">
                            {addonIds.length > 0 && (
                                <span className="rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold text-orange-600">
                                    {addonIds.length} selected
                                </span>
                            )}
                            {sizesDone && (
                                addonsOpen
                                    ? <ChevronUp className="h-4 w-4 text-slate-400" />
                                    : <ChevronDown className="h-4 w-4 text-slate-400" />
                            )}
                        </div>
                    </button>

                    {/* Collapsible body */}
                    {addonsOpen && (
                        ! sizesDone ? (
                            <p className="px-4 pb-4 text-xs text-slate-400">Select a size first.</p>
                        ) : addonsForSize.length === 0 ? (
                            <p className="px-4 pb-4 text-xs text-slate-400">No add-ons available for this size.</p>
                        ) : (
                            <div className="px-4 pb-4 space-y-4">
                                {addonsForSize.map(group => (
                                    <div key={group.id}>
                                        <p className="mb-2 text-xs font-medium text-slate-500">
                                            {group.name}
                                            <span className="ml-1 font-normal text-slate-300 text-[10px]">
                                                ({group.selection_type === 'single' ? 'pick one' : 'pick any'})
                                            </span>
                                        </p>
                                        <div className="space-y-1.5">
                                            {group.items.map(item => {
                                                const isSel = addonIds.includes(item.id);
                                                return (
                                                    <button
                                                        key={item.id}
                                                        type="button"
                                                        onClick={() => toggleAddon(group, item.id)}
                                                        className={cn(
                                                            'w-full flex items-center gap-3 rounded-xl border-2 p-3 text-left transition-all duration-150',
                                                            isSel
                                                                ? 'border-orange-300 bg-orange-50'
                                                                : 'border-slate-200 bg-white hover:border-orange-200',
                                                        )}
                                                    >
                                                        {item.photo_url ? (
                                                            <img src={item.photo_url} alt={item.name} className="h-10 w-10 rounded-lg object-cover shrink-0" />
                                                        ) : (
                                                            <div className={cn(
                                                                'h-10 w-10 shrink-0 rounded-lg flex items-center justify-center text-xs font-bold',
                                                                isSel ? 'bg-orange-100 text-orange-500' : 'bg-slate-100 text-slate-400',
                                                            )}>
                                                                {item.name.charAt(0)}
                                                            </div>
                                                        )}
                                                        <div className="flex-1 min-w-0">
                                                            <p className="text-sm font-semibold text-slate-800">{item.name}</p>
                                                            {item.description && (
                                                                <p className="mt-0.5 text-[11px] text-slate-500 truncate">{item.description}</p>
                                                            )}
                                                        </div>
                                                        <div className="shrink-0 text-right">
                                                            <p className="text-sm font-bold text-slate-700">{fmt(item.price)}</p>
                                                            <div className={cn(
                                                                'mt-1 ml-auto h-5 w-5 rounded-full border-2 flex items-center justify-center transition-all',
                                                                isSel ? 'border-orange-500 bg-orange-500' : 'border-slate-300',
                                                            )}>
                                                                {isSel && <Check className="h-3 w-3 text-white" strokeWidth={3} />}
                                                            </div>
                                                        </div>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )
                    )}
                </div>

                {/* ── 4. Delivery address ──────────────────────────────────── */}
                <div className="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                    <SectionHeader step={4} label="Deliver To" done={addrDone} />

                    <div className="px-4 pb-4">
                        {addresses.length === 0 ? (
                            <div className="space-y-2.5">
                                <button
                                    type="button"
                                    onClick={detectLocation}
                                    disabled={autoLocating}
                                    className="w-full flex items-center justify-center gap-2 rounded-xl bg-orange-500 py-3 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-60 transition-all"
                                >
                                    {autoLocating
                                        ? <><Loader2 className="h-4 w-4 animate-spin" />Detecting…</>
                                        : <><Crosshair className="h-4 w-4" />Use My Current Location</>}
                                </button>
                                <p className="text-center text-xs text-slate-400">
                                    or{' '}
                                    <a href="/addresses/create?redirect_to=order_new" className="text-orange-500 underline">
                                        pin on map
                                    </a>
                                </p>
                                {geoError && <p className="text-xs text-red-500 text-center">{geoError}</p>}
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {/* Address dropdown */}
                                <div className="relative">
                                    <button
                                        type="button"
                                        onClick={() => setAddrOpen(o => ! o)}
                                        className="w-full flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-left text-sm hover:bg-slate-100 transition-colors"
                                    >
                                        <MapPin className="h-4 w-4 shrink-0 text-orange-500" />
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium text-slate-800">{chosenAddress?.label ?? '—'}</p>
                                            {chosenAddress?.description && (
                                                <p className="text-[11px] text-slate-500 truncate">{chosenAddress.description}</p>
                                            )}
                                        </div>
                                        <ChevronDown className={cn('h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200', addrOpen && 'rotate-180')} />
                                    </button>

                                    {addrOpen && (
                                        <div className="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-xl overflow-hidden">
                                            {addresses.map(a => (
                                                <button
                                                    key={a.id}
                                                    type="button"
                                                    onClick={() => {
                                                        setAddressId(a.id);
                                                        setAddrOpen(false);
                                                        setErrors(e => ({ ...e, address_id: '' }));
                                                    }}
                                                    className={cn(
                                                        'w-full flex items-center gap-3 px-3 py-2.5 text-left text-sm transition-colors',
                                                        addressId === a.id ? 'bg-orange-50' : 'hover:bg-slate-50',
                                                    )}
                                                >
                                                    <MapPin className="h-4 w-4 shrink-0 text-orange-400" />
                                                    <div className="min-w-0 flex-1">
                                                        <p className="font-medium text-slate-800">{a.label}</p>
                                                        {a.description && (
                                                            <p className="text-[11px] text-slate-500 truncate">{a.description}</p>
                                                        )}
                                                    </div>
                                                    {addressId === a.id && (
                                                        <Check className="ml-auto h-4 w-4 shrink-0 text-orange-500" />
                                                    )}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                {/* Use current location */}
                                <button
                                    type="button"
                                    onClick={detectLocation}
                                    disabled={autoLocating}
                                    className="w-full flex items-center gap-2 rounded-xl border border-dashed border-slate-300 px-3 py-2 text-xs text-slate-500 hover:border-orange-300 hover:text-orange-600 disabled:opacity-50 transition-all"
                                >
                                    {autoLocating
                                        ? <><Loader2 className="h-3.5 w-3.5 animate-spin" />Detecting…</>
                                        : <><Crosshair className="h-3.5 w-3.5" />Deliver to my current location</>}
                                </button>

                                {geoError && <p className="text-xs text-red-500">{geoError}</p>}
                            </div>
                        )}

                        {errors.address_id && (
                            <p className="mt-1.5 text-xs text-red-500">{errors.address_id}</p>
                        )}
                    </div>
                </div>

                {/* ── 5. Payment ───────────────────────────────────────────── */}
                <div className="rounded-2xl bg-white border border-slate-100 shadow-sm p-4">
                    <p className="mb-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Payment</p>
                    <div className="grid grid-cols-2 gap-2">
                        {[
                            { value: 'mpesa', label: 'M-Pesa',  sub: 'Pay after order' },
                            { value: 'cash',  label: 'Cash',    sub: 'On delivery'     },
                        ].map(m => (
                            <button
                                key={m.value}
                                type="button"
                                onClick={() => setPayMethod(m.value)}
                                className={cn(
                                    'flex flex-col items-start rounded-xl border-2 px-3 py-2.5 text-left transition-all duration-150',
                                    payMethod === m.value
                                        ? 'border-orange-400 bg-orange-50'
                                        : 'border-slate-200 bg-white hover:border-orange-200',
                                )}
                            >
                                <div className="flex items-center gap-2">
                                    <div className={cn(
                                        'h-4 w-4 shrink-0 rounded-full border-2 flex items-center justify-center transition-all',
                                        payMethod === m.value ? 'border-orange-500' : 'border-slate-300',
                                    )}>
                                        {payMethod === m.value && <div className="h-2 w-2 rounded-full bg-orange-500" />}
                                    </div>
                                    <span className={cn(
                                        'text-sm font-semibold',
                                        payMethod === m.value ? 'text-orange-700' : 'text-slate-700',
                                    )}>
                                        {m.label}
                                    </span>
                                </div>
                                <p className="mt-0.5 pl-6 text-[10px] text-slate-400">{m.sub}</p>
                            </button>
                        ))}
                    </div>
                    {payMethod === 'mpesa' && mpesa_till && (
                        <div className="mt-3 flex items-start gap-2 rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-xs text-emerald-700">
                            <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-500" />
                            <span>
                                Pay <strong>{selectedSize ? fmt(total) : '—'}</strong> to M-Pesa Till{' '}
                                <strong className="text-base">{mpesa_till}</strong> after placing your order.
                            </span>
                        </div>
                    )}
                </div>

                {/* ── Delivery note ─────────────────────────────────────────── */}
                <div className="rounded-2xl bg-white border border-slate-100 shadow-sm p-4">
                    <div className="flex items-center gap-2 mb-2">
                        <StickyNote className="h-3.5 w-3.5 text-slate-400" />
                        <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                            Delivery Note
                            <span className="ml-1 font-normal normal-case text-slate-300">(optional)</span>
                        </p>
                    </div>
                    <input
                        type="text"
                        value={notes}
                        onChange={e => setNotes(e.target.value)}
                        placeholder="Gate code, floor number, landmark…"
                        className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 placeholder:text-slate-400 focus:border-orange-400 focus:bg-white focus:outline-none transition-colors"
                    />
                </div>

                {/* Server-side errors */}
                {errors.size_id && (
                    <div className="flex items-center gap-2 rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                        <AlertCircle className="h-4 w-4 shrink-0" />
                        {errors.size_id}
                    </div>
                )}
                {errors.addon_ids && (
                    <div className="flex items-center gap-2 rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                        <AlertCircle className="h-4 w-4 shrink-0" />
                        {errors.addon_ids}
                    </div>
                )}
            </div>

            {/* ── Sticky bottom CTA ─────────────────────────────────────────── */}
            <div className="fixed bottom-16 inset-x-0 z-40 bg-white/95 backdrop-blur-sm border-t border-slate-100 shadow-[0_-4px_20px_rgba(0,0,0,0.06)]">
                <div className="mx-auto max-w-sm flex items-center gap-3 px-4 py-3">

                    {/* Order summary + total */}
                    <div className="min-w-0 flex-1">
                        {selectedSize ? (
                            <>
                                <p className="text-[10px] text-slate-400 truncate leading-tight">
                                    {selectedSize.name} · {isSwap ? 'Swap / Refill' : 'New Cylinder'}
                                    {brandId && brandsForSize.find(b => b.id === brandId)
                                        ? ` · ${brandsForSize.find(b => b.id === brandId)!.name}`
                                        : ''}
                                </p>
                                <p className="text-xl font-bold text-slate-800 tabular-nums leading-tight">
                                    {fmt(total)}
                                </p>
                                {addonsTotal > 0 && (
                                    <p className="text-[10px] text-slate-400">incl. {fmt(addonsTotal)} add-ons</p>
                                )}
                            </>
                        ) : (
                            <p className="text-xs text-slate-400 leading-snug">Select a size<br />to see price</p>
                        )}
                    </div>

                    {/* CTA */}
                    <button
                        onClick={submit}
                        disabled={! canSubmit}
                        className={cn(
                            'shrink-0 rounded-xl px-5 py-3.5 text-sm font-bold transition-all duration-150',
                            canSubmit
                                ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/30 hover:bg-orange-600 active:scale-[0.97]'
                                : 'bg-slate-100 text-slate-400 cursor-not-allowed',
                        )}
                    >
                        {loading
                            ? <span className="flex items-center gap-2"><Loader2 className="h-4 w-4 animate-spin" />Placing…</span>
                            : 'Place Order →'}
                    </button>
                </div>
            </div>
        </CustomerLayout>
    );
}
