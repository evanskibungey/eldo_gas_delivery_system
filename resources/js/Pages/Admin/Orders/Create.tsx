import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { useFieldArray, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
    ArrowLeft, Loader2, Phone, Store, Plus, Trash2, Search,
    UserPlus, MapPin, Check, AlertTriangle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { errorMessage, postJson } from '@/lib/http';
import { cn } from '@/lib/utils';

// ── Types ─────────────────────────────────────────────────────────────────────

interface Size {
    id: number;
    name: string;
    weight_kg: number;
    is_commercial: boolean;
    filled_count: number;
    swap_price: number;
    new_price: number;
    delivery_fee: number;
    has_price: boolean;
}

interface Brand { id: number; name: string }

interface AddonGroup {
    id: number;
    name: string;
    selection_type: 'single' | 'multi';
    items: { id: number; name: string; price: number }[];
}

interface Catalogue {
    sizes: Size[];
    brands_by_size: Record<string, Brand[]>;
    addons_by_size: Record<string, AddonGroup[]>;
    delivery_fee_mode: string;
    delivery_base_fee: number;
}

interface Address {
    id: number;
    label: string;
    description: string | null;
    is_default: boolean;
}

interface PickedCustomer {
    id: number;
    name: string;
    phone: string;
    is_app_user: boolean;
    addresses?: Address[];
}

interface Place {
    place_id: string;
    display_name: string;
    short: string;
    lat: number;
    lon: number;
}

interface Props {
    catalogue: Catalogue;
    shop_label: string;
}

// ── Schema ────────────────────────────────────────────────────────────────────

const schema = z.object({
    channel: z.enum(['phone', 'walk_in']),
    customer_id: z.number().int().positive().nullable(),
    customer_name: z.string().max(100),
    customer_phone: z.string().max(20),
    items: z.array(z.object({
        size_id: z.coerce.number().int().positive('Choose a size'),
        brand_id: z.coerce.number().int().positive('Choose a brand'),
        order_type: z.enum(['swap', 'new_cylinder']),
        quantity: z.coerce.number().int().min(1).max(50),
    })).min(1, 'Add at least one cylinder to the order.'),
    addon_ids: z.array(z.number().int()),
    payment_method: z.enum(['cash', 'mpesa']),
    delivery_notes: z.string().max(255),
    address_id: z.number().int().positive().nullable(),
}).superRefine((data, ctx) => {
    if (!data.customer_id && data.customer_phone.trim() === '') {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['customer_phone'],
            message: 'Choose a customer, or add one with a name and phone number.',
        });
    }

    // A phone order is delivered, so it needs somewhere to go. A counter sale
    // is collected and never has an address.
    if (data.channel === 'phone' && !data.address_id) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            path: ['address_id'],
            message: 'A phone order needs a delivery address.',
        });
    }
});

type FormData = z.infer<typeof schema>;

const money = (value: number) => 'KES ' + Math.round(value).toLocaleString();

// ── Small pieces ──────────────────────────────────────────────────────────────

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-500">{message}</p>;
}

function Section({ title, hint, children }: {
    title: string; hint?: string; children: React.ReactNode;
}) {
    return (
        <div className="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
            <div className="border-b border-slate-100 px-5 py-3">
                <h2 className="text-sm font-semibold text-slate-800">{title}</h2>
                {hint && <p className="mt-0.5 text-xs text-slate-500">{hint}</p>}
            </div>
            <div className="p-5">{children}</div>
        </div>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function AdminOrderCreate({ catalogue, shop_label }: Props) {
    const [submitting, setSubmitting] = useState(false);

    // Customer picker
    const [customer, setCustomer] = useState<PickedCustomer | null>(null);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<PickedCustomer[]>([]);
    const [searching, setSearching] = useState(false);
    const [showNew, setShowNew] = useState(false);
    const [creating, setCreating] = useState(false);
    const [customerError, setCustomerError] = useState<string | null>(null);

    // Address panel (phone orders only)
    const [addresses, setAddresses] = useState<Address[]>([]);
    const [showNewAddress, setShowNewAddress] = useState(false);
    const [placeQuery, setPlaceQuery] = useState('');
    const [places, setPlaces] = useState<Place[]>([]);
    const [place, setPlace] = useState<Place | null>(null);
    const [placeLabel, setPlaceLabel] = useState('Home');
    const [lookupError, setLookupError] = useState<string | null>(null);
    const [savingAddress, setSavingAddress] = useState(false);

    const {
        register, control, handleSubmit, watch, setValue, setError,
        formState: { errors },
    } = useForm<FormData>({
        resolver: zodResolver(schema),
        defaultValues: {
            // The counter is the common case: somebody is standing there.
            channel: 'walk_in',
            customer_id: null,
            customer_name: '',
            customer_phone: '',
            items: [{ size_id: 0, brand_id: 0, order_type: 'swap', quantity: 1 }],
            addon_ids: [],
            payment_method: 'cash',
            delivery_notes: '',
            address_id: null,
        },
    });

    const { fields, append, remove } = useFieldArray({ control, name: 'items' });

    const channel = watch('channel');
    const items = watch('items');
    const addonIds = watch('addon_ids');
    const addressId = watch('address_id');

    const sizeById = useMemo(
        () => new Map(catalogue.sizes.map(size => [size.id, size])),
        [catalogue.sizes],
    );

    // ── Customer search ───────────────────────────────────────────────────────
    useEffect(() => {
        const controller = new AbortController();

        const timer = window.setTimeout(async () => {
            setSearching(true);
            try {
                const response = await fetch(
                    `/admin/customers/search?with_addresses=1&q=${encodeURIComponent(query)}`,
                    { signal: controller.signal, headers: { Accept: 'application/json' } },
                );
                if (response.ok) setResults(await response.json());
            } catch {
                // Aborted or offline; the last results stay on screen.
            } finally {
                setSearching(false);
            }
        }, 250);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [query]);

    function pick(found: PickedCustomer) {
        setCustomer(found);
        setValue('customer_id', found.id, { shouldValidate: true });
        setValue('customer_name', found.name ?? '');
        setValue('customer_phone', found.phone ?? '');
        setAddresses(found.addresses ?? []);
        setValue('address_id', found.addresses?.find(a => a.is_default)?.id ?? found.addresses?.[0]?.id ?? null);
        setShowNew(false);
        setShowNewAddress(false);
        setCustomerError(null);
    }

    function clearCustomer() {
        setCustomer(null);
        setAddresses([]);
        setValue('customer_id', null);
        setValue('customer_name', '');
        setValue('customer_phone', '');
        setValue('address_id', null);
    }

    /**
     * Create the caller, or find them if the number is already on file.
     *
     * Resolved to an id up front rather than sent along with the order, because
     * a delivery address has to hang off a customer that exists — and because a
     * repeat walk-in should visibly resolve to the record they already have
     * rather than silently merge at submit time.
     */
    async function addCustomer() {
        const name = (watch('customer_name') ?? '').trim();
        const phone = (watch('customer_phone') ?? '').trim();

        if (name === '' || phone === '') {
            setCustomerError('A name and a phone number, please.');
            return;
        }

        setCreating(true);
        setCustomerError(null);
        try {
            pick(await postJson<PickedCustomer>('/admin/customers', { name, phone }));
        } catch (error) {
            setCustomerError(errorMessage(error, 'Could not add that customer.'));
        } finally {
            setCreating(false);
        }
    }

    // ── Address lookup ────────────────────────────────────────────────────────
    useEffect(() => {
        if (placeQuery.trim().length < 2) {
            setPlaces([]);
            return;
        }

        const controller = new AbortController();

        const timer = window.setTimeout(async () => {
            setLookupError(null);
            try {
                const response = await fetch(
                    `/admin/geocode/search?q=${encodeURIComponent(placeQuery)}`,
                    { signal: controller.signal, headers: { Accept: 'application/json' } },
                );

                if (!response.ok) {
                    // An empty list would read as "no such place", which is a
                    // different and much more misleading answer.
                    setPlaces([]);
                    setLookupError('Address lookup is unavailable right now.');
                    return;
                }

                const body = await response.json();
                setPlaces(body.data ?? []);
            } catch {
                // Aborted or offline; leave what is on screen.
            }
        }, 350);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [placeQuery]);

    async function saveAddress() {
        if (!customer || !place) return;

        setSavingAddress(true);
        try {
            const saved = await postJson<Address>(`/admin/customers/${customer.id}/addresses`, {
                label: placeLabel,
                description: place.short || place.display_name,
                latitude: place.lat,
                longitude: place.lon,
            });

            setAddresses(current => [...current, saved]);
            setValue('address_id', saved.id, { shouldValidate: true });
            setShowNewAddress(false);
            setPlace(null);
            setPlaceQuery('');
            setPlaces([]);
        } catch (error) {
            setLookupError(errorMessage(error, 'Could not save that address.'));
        } finally {
            setSavingAddress(false);
        }
    }

    // ── Pricing, mirrored from PlaceOrderAction ───────────────────────────────

    const itemsSubtotal = items.reduce((sum, line) => {
        const size = sizeById.get(Number(line.size_id));
        if (!size) return sum;
        const unit = line.order_type === 'swap' ? size.swap_price : size.new_price;
        return sum + unit * Math.max(1, Number(line.quantity) || 1);
    }, 0);

    // One journey, one fee — the highest of the basket's sizes under per-size
    // pricing. Nothing travels on a counter sale, so nothing is charged for it.
    const deliveryFee = channel === 'walk_in'
        ? 0
        : catalogue.delivery_fee_mode === 'per_size'
            ? Math.max(0, ...items.map(line => sizeById.get(Number(line.size_id))?.delivery_fee ?? 0))
            : catalogue.delivery_base_fee;

    const addonById = useMemo(() => {
        const map = new Map<number, { id: number; name: string; price: number }>();
        Object.values(catalogue.addons_by_size).forEach(groups =>
            groups.forEach(group => group.items.forEach(item => map.set(item.id, item))));
        return map;
    }, [catalogue.addons_by_size]);

    // Only the add-ons belonging to a size actually in the basket, de-duplicated
    // when two lines share a size.
    const addonGroups = useMemo(() => {
        const seen = new Map<number, AddonGroup>();
        items.forEach(line => {
            (catalogue.addons_by_size[String(line.size_id)] ?? [])
                .forEach(group => seen.set(group.id, group));
        });
        return [...seen.values()];
    }, [items, catalogue.addons_by_size]);

    const addonsTotal = addonIds.reduce((sum, id) => sum + (addonById.get(id)?.price ?? 0), 0);
    const total = itemsSubtotal + deliveryFee + addonsTotal;

    // Warn before the order is built rather than letting the server refuse it:
    // somebody is standing at the counter.
    const shortages = useMemo(() => {
        const wanted = new Map<number, number>();
        items.forEach(line => {
            const id = Number(line.size_id);
            if (!id) return;
            wanted.set(id, (wanted.get(id) ?? 0) + Math.max(1, Number(line.quantity) || 1));
        });

        return [...wanted.entries()]
            .map(([id, quantity]) => ({ size: sizeById.get(id), quantity }))
            .filter(row => row.size && row.quantity > row.size.filled_count);
    }, [items, sizeById]);

    function toggleAddon(id: number, group: AddonGroup) {
        const current = addonIds ?? [];

        if (current.includes(id)) {
            setValue('addon_ids', current.filter(existing => existing !== id));
            return;
        }

        // A single-select group replaces rather than adds.
        const siblings = group.selection_type === 'single'
            ? group.items.map(item => item.id)
            : [];

        setValue('addon_ids', [...current.filter(existing => !siblings.includes(existing)), id]);
    }

    function onSubmit(data: FormData) {
        setSubmitting(true);

        router.post('/admin/orders', {
            ...data,
            // A counter sale is collected here, so anything picked in the
            // delivery panel before the toggle was flipped is dropped.
            address_id: data.channel === 'phone' ? data.address_id : null,
        } as never, {
            onError: (serverErrors) => {
                setSubmitting(false);
                Object.entries(serverErrors).forEach(([field, message]) => {
                    setError(field as keyof FormData, { message: message as string });
                });
            },
            onFinish: () => setSubmitting(false),
        });
    }

    const inputCls = (err?: { message?: string }) => cn(
        'mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm',
        'focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all',
        err && 'border-red-400 bg-red-50',
    );

    const selectCls = 'mt-1.5 h-10 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm focus:border-orange-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-orange-400/20';

    return (
        <AdminLayout title="New Order" subtitle="Record an order taken over the phone or at the counter">
            <div className="mb-6">
                <Link href="/admin/orders" className="inline-flex items-center gap-1.5 text-sm text-slate-500 transition-colors hover:text-slate-800">
                    <ArrowLeft className="h-4 w-4" /> Back to Orders
                </Link>
            </div>

            <form onSubmit={handleSubmit(onSubmit)} className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="space-y-5">

                    {/* ── Channel ── */}
                    <Section
                        title="How was this order taken?"
                        hint="A counter sale is recorded as already collected and paid. A phone order joins the dispatch board and waits for a rider."
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            {([
                                { value: 'walk_in', icon: Store, label: 'Counter sale', desc: 'Bought and collected at the shop' },
                                { value: 'phone', icon: Phone, label: 'Phone order', desc: 'Called in, to be delivered' },
                            ] as const).map(option => (
                                <label
                                    key={option.value}
                                    className={cn(
                                        'flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-all',
                                        channel === option.value
                                            ? 'border-orange-400 bg-orange-50 shadow-sm shadow-orange-500/10'
                                            : 'border-slate-200 bg-white hover:border-slate-300',
                                    )}
                                >
                                    <input type="radio" value={option.value} {...register('channel')} className="sr-only" />
                                    <option.icon className={cn('mt-0.5 h-5 w-5 shrink-0', channel === option.value ? 'text-orange-500' : 'text-slate-400')} />
                                    <span>
                                        <span className="block text-sm font-semibold text-slate-800">{option.label}</span>
                                        <span className="mt-0.5 block text-xs text-slate-500">{option.desc}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </Section>

                    {/* ── Customer ── */}
                    <Section
                        title="Customer"
                        hint="Search the list first — a repeat walk-in should keep one record, and their GasPoints with it."
                    >
                        {customer ? (
                            <div className="flex items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-slate-800">{customer.name || 'Unnamed'}</p>
                                    <p className="text-xs text-slate-600">{customer.phone}</p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <span className={cn(
                                        'rounded-full border px-2 py-0.5 text-2xs font-semibold',
                                        customer.is_app_user
                                            ? 'border-emerald-200 bg-white text-emerald-700'
                                            : 'border-amber-200 bg-amber-50 text-amber-700',
                                    )}>
                                        {customer.is_app_user ? 'App user' : 'Walk-in'}
                                    </span>
                                    <button type="button" onClick={clearCustomer} className="text-xs font-medium text-slate-500 underline hover:text-slate-700">
                                        Change
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        placeholder="Name or phone number…"
                                        value={query}
                                        onChange={event => setQuery(event.target.value)}
                                        className="h-10 border-slate-200 bg-slate-50 pl-8 text-sm focus:border-orange-400 focus:bg-white focus:ring-orange-400/20"
                                    />
                                </div>

                                <div className="mt-3 max-h-56 overflow-y-auto rounded-lg border border-slate-100">
                                    {searching && results.length === 0 && (
                                        <p className="px-4 py-3 text-xs text-slate-500">Searching…</p>
                                    )}
                                    {!searching && results.length === 0 && (
                                        <p className="px-4 py-3 text-xs text-slate-500">
                                            Nobody matches that. Add them below.
                                        </p>
                                    )}
                                    {results.map(row => (
                                        <button
                                            key={row.id}
                                            type="button"
                                            onClick={() => pick(row)}
                                            className="flex w-full items-center justify-between gap-3 border-b border-slate-50 px-4 py-2.5 text-left transition-colors last:border-b-0 hover:bg-orange-50"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-medium text-slate-800">{row.name || 'Unnamed'}</span>
                                                <span className="block text-xs text-slate-500">{row.phone}</span>
                                            </span>
                                            {!row.is_app_user && (
                                                <span className="shrink-0 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-2xs font-semibold text-amber-700">
                                                    Walk-in
                                                </span>
                                            )}
                                        </button>
                                    ))}
                                </div>

                                {showNew ? (
                                    <div className="mt-4 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <Label className="text-xs font-medium text-slate-700">Name</Label>
                                                <Input placeholder="e.g. Mercy Chebet" {...register('customer_name')} className={inputCls(errors.customer_name)} />
                                                <FieldError message={errors.customer_name?.message} />
                                            </div>
                                            <div>
                                                <Label className="text-xs font-medium text-slate-700">Phone</Label>
                                                <Input placeholder="07…" {...register('customer_phone')} className={inputCls(errors.customer_phone)} />
                                                <FieldError message={errors.customer_phone?.message} />
                                            </div>
                                        </div>

                                        {customerError && <p className="text-xs text-red-500">{customerError}</p>}

                                        <div className="flex gap-2">
                                            <Button type="button" size="sm" onClick={addCustomer} disabled={creating} className="bg-orange-500 hover:bg-orange-600">
                                                {creating ? <><Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />Adding…</> : 'Add & select'}
                                            </Button>
                                            <Button type="button" size="sm" variant="outline" onClick={() => setShowNew(false)}>
                                                Cancel
                                            </Button>
                                        </div>
                                        <p className="text-2xs text-slate-500">
                                            They stay marked as a walk-in until the day they sign into the app themselves.
                                        </p>
                                    </div>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => setShowNew(true)}
                                        className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-orange-600 hover:text-orange-700"
                                    >
                                        <UserPlus className="h-4 w-4" /> Add a new customer
                                    </button>
                                )}

                                <FieldError message={errors.customer_phone?.message} />
                            </>
                        )}
                    </Section>

                    {/* ── Basket ── */}
                    <Section title="Cylinders" hint="Prices and stock are the same ones the app uses.">
                        <div className="space-y-3">
                            {fields.map((field, index) => {
                                const line = items[index];
                                const sizeId = Number(line?.size_id ?? 0);
                                const size = sizeById.get(sizeId);
                                const brands = catalogue.brands_by_size[String(sizeId)] ?? [];
                                const unit = line?.order_type === 'swap' ? (size?.swap_price ?? 0) : (size?.new_price ?? 0);

                                return (
                                    <div key={field.id} className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <div className="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_84px]">
                                            <div>
                                                <Label className="text-xs font-medium text-slate-700">Size</Label>
                                                <select
                                                    {...register(`items.${index}.size_id` as const)}
                                                    onChange={event => {
                                                        setValue(`items.${index}.size_id`, Number(event.target.value) as never);
                                                        // The brand list is filtered by size, so a
                                                        // brand chosen for the old one may not be
                                                        // sold in the new one.
                                                        setValue(`items.${index}.brand_id`, 0 as never);
                                                    }}
                                                    className={selectCls}
                                                >
                                                    <option value={0}>Choose…</option>
                                                    {catalogue.sizes.map(option => (
                                                        <option key={option.id} value={option.id} disabled={!option.has_price}>
                                                            {option.name}
                                                            {option.filled_count === 0 ? ' — out of stock' : ` — ${option.filled_count} in stock`}
                                                        </option>
                                                    ))}
                                                </select>
                                                <FieldError message={errors.items?.[index]?.size_id?.message} />
                                            </div>

                                            <div>
                                                <Label className="text-xs font-medium text-slate-700">Brand</Label>
                                                <select {...register(`items.${index}.brand_id` as const)} className={selectCls} disabled={!sizeId}>
                                                    <option value={0}>{sizeId ? 'Choose…' : 'Pick a size first'}</option>
                                                    {brands.map(brand => (
                                                        <option key={brand.id} value={brand.id}>{brand.name}</option>
                                                    ))}
                                                </select>
                                                <FieldError message={errors.items?.[index]?.brand_id?.message} />
                                            </div>

                                            <div>
                                                <Label className="text-xs font-medium text-slate-700">Type</Label>
                                                <select {...register(`items.${index}.order_type` as const)} className={selectCls}>
                                                    <option value="swap">Gas swap</option>
                                                    <option value="new_cylinder">New cylinder</option>
                                                </select>
                                            </div>

                                            <div>
                                                <Label className="text-xs font-medium text-slate-700">Qty</Label>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={50}
                                                    {...register(`items.${index}.quantity` as const)}
                                                    className={inputCls(errors.items?.[index]?.quantity)}
                                                />
                                            </div>
                                        </div>

                                        <div className="mt-3 flex items-center justify-between">
                                            <span className="text-xs text-slate-500">
                                                {unit > 0
                                                    ? `${money(unit)} each · ${money(unit * Math.max(1, Number(line?.quantity) || 1))} for this line`
                                                    : 'Choose a size to price this line'}
                                            </span>
                                            {fields.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() => remove(index)}
                                                    className="inline-flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-600"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" /> Remove
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <button
                            type="button"
                            onClick={() => append({ size_id: 0, brand_id: 0, order_type: 'swap', quantity: 1 } as never)}
                            className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-orange-600 hover:text-orange-700"
                        >
                            <Plus className="h-4 w-4" /> Add another cylinder
                        </button>

                        <FieldError message={typeof errors.items?.message === 'string' ? errors.items.message : undefined} />

                        {shortages.length > 0 && (
                            <div className="mt-4 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                                <p className="text-xs text-amber-800">
                                    {shortages.map(row => `${row.quantity} × ${row.size!.name} ordered, ${row.size!.filled_count} on the shelf`).join('; ')}.
                                    {' '}The order will be refused until stock is adjusted.
                                </p>
                            </div>
                        )}
                    </Section>

                    {/* ── Add-ons ── */}
                    {addonGroups.length > 0 && (
                        <Section title="Add-ons" hint="Optional extras for the sizes in this order.">
                            <div className="space-y-4">
                                {addonGroups.map(group => (
                                    <div key={group.id}>
                                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{group.name}</p>
                                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                            {group.items.map(item => {
                                                const checked = addonIds.includes(item.id);
                                                return (
                                                    <button
                                                        key={item.id}
                                                        type="button"
                                                        onClick={() => toggleAddon(item.id, group)}
                                                        className={cn(
                                                            'flex items-center justify-between gap-3 rounded-lg border px-3 py-2.5 text-left transition-all',
                                                            checked
                                                                ? 'border-orange-400 bg-orange-50'
                                                                : 'border-slate-200 bg-white hover:border-slate-300',
                                                        )}
                                                    >
                                                        <span className="min-w-0">
                                                            <span className="block truncate text-sm text-slate-800">{item.name}</span>
                                                            <span className="block text-xs text-slate-500">{money(item.price)}</span>
                                                        </span>
                                                        {checked && <Check className="h-4 w-4 shrink-0 text-orange-500" />}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Section>
                    )}

                    {/* ── Delivery ── */}
                    {channel === 'phone' ? (
                        <Section title="Deliver to" hint="The rider navigates to this pin, so it has to be a real place.">
                            {!customer ? (
                                <p className="text-sm text-slate-500">Choose a customer first — addresses belong to them.</p>
                            ) : (
                                <>
                                    <div className="space-y-2">
                                        {addresses.map(address => (
                                            <label
                                                key={address.id}
                                                className={cn(
                                                    'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-all',
                                                    addressId === address.id
                                                        ? 'border-orange-400 bg-orange-50'
                                                        : 'border-slate-200 bg-white hover:border-slate-300',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    className="sr-only"
                                                    checked={addressId === address.id}
                                                    onChange={() => setValue('address_id', address.id, { shouldValidate: true })}
                                                />
                                                <MapPin className={cn('mt-0.5 h-4 w-4 shrink-0', addressId === address.id ? 'text-orange-500' : 'text-slate-400')} />
                                                <span className="min-w-0">
                                                    <span className="block text-sm font-medium text-slate-800">{address.label}</span>
                                                    <span className="block truncate text-xs text-slate-500">{address.description}</span>
                                                </span>
                                            </label>
                                        ))}
                                        {addresses.length === 0 && (
                                            <p className="text-sm text-slate-500">
                                                They have no saved address. Look one up below.
                                            </p>
                                        )}
                                    </div>

                                    {showNewAddress ? (
                                        <div className="mt-4 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                            <div className="grid gap-3 sm:grid-cols-[140px_1fr]">
                                                <div>
                                                    <Label className="text-xs font-medium text-slate-700">Label</Label>
                                                    <select
                                                        value={placeLabel}
                                                        onChange={event => setPlaceLabel(event.target.value)}
                                                        className={selectCls}
                                                    >
                                                        {['Home', 'Office', 'Restaurant', 'Other'].map(option => (
                                                            <option key={option} value={option}>{option}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div>
                                                    <Label className="text-xs font-medium text-slate-700">Search for the place</Label>
                                                    <Input
                                                        placeholder="e.g. Kimumu, near Elgon View…"
                                                        value={placeQuery}
                                                        onChange={event => { setPlaceQuery(event.target.value); setPlace(null); }}
                                                        className={inputCls()}
                                                    />
                                                </div>
                                            </div>

                                            {lookupError && <p className="text-xs text-red-500">{lookupError}</p>}

                                            {places.length > 0 && (
                                                <div className="max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-white">
                                                    {places.map(row => (
                                                        <button
                                                            key={row.place_id}
                                                            type="button"
                                                            onClick={() => setPlace(row)}
                                                            className={cn(
                                                                'block w-full border-b border-slate-50 px-3 py-2 text-left text-xs last:border-b-0 hover:bg-orange-50',
                                                                place?.place_id === row.place_id && 'bg-orange-50',
                                                            )}
                                                        >
                                                            <span className="block font-medium text-slate-800">{row.short}</span>
                                                            <span className="block truncate text-slate-500">{row.display_name}</span>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}

                                            <div className="flex gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={saveAddress}
                                                    disabled={!place || savingAddress}
                                                    className="bg-orange-500 hover:bg-orange-600"
                                                >
                                                    {savingAddress ? <><Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />Saving…</> : 'Save address'}
                                                </Button>
                                                <Button type="button" size="sm" variant="outline" onClick={() => setShowNewAddress(false)}>
                                                    Cancel
                                                </Button>
                                            </div>
                                        </div>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() => setShowNewAddress(true)}
                                            className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-orange-600 hover:text-orange-700"
                                        >
                                            <Plus className="h-4 w-4" /> Add an address
                                        </button>
                                    )}

                                    <FieldError message={errors.address_id?.message} />
                                </>
                            )}

                            <div className="mt-4">
                                <Label className="text-xs font-medium text-slate-700">Notes for the rider</Label>
                                <Input placeholder="Landmark, gate colour, who to ask for…" {...register('delivery_notes')} className={inputCls(errors.delivery_notes)} />
                                <FieldError message={errors.delivery_notes?.message} />
                            </div>
                        </Section>
                    ) : (
                        <Section title="Collected at the counter" hint={shop_label}>
                            <p className="text-sm text-slate-600">
                                Nothing is delivered, so no delivery fee is charged and no rider is assigned.
                                The order is recorded as delivered and paid the moment you save it.
                            </p>
                            <div className="mt-4">
                                <Label className="text-xs font-medium text-slate-700">Note <span className="font-normal text-slate-500">(optional)</span></Label>
                                <Input placeholder="Anything worth recording about this sale" {...register('delivery_notes')} className={inputCls(errors.delivery_notes)} />
                            </div>
                        </Section>
                    )}
                </div>

                {/* ── Summary ── */}
                <div className="lg:sticky lg:top-6 lg:self-start">
                    <Section title="Summary">
                        <div className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-slate-500">Cylinders</span>
                                <span className="font-medium text-slate-800">{money(itemsSubtotal)}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-slate-500">Delivery</span>
                                <span className="font-medium text-slate-800">{deliveryFee > 0 ? money(deliveryFee) : '—'}</span>
                            </div>
                            {addonsTotal > 0 && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-slate-500">Add-ons</span>
                                    <span className="font-medium text-slate-800">{money(addonsTotal)}</span>
                                </div>
                            )}
                            <div className="mt-2 flex justify-between border-t border-slate-100 pt-3">
                                <span className="text-sm font-semibold text-slate-800">Total</span>
                                <span className="text-lg font-bold text-orange-600">{money(total)}</span>
                            </div>
                            <p className="text-2xs text-slate-500">
                                Re-calculated on the server when you save — that figure is the one charged.
                            </p>
                        </div>

                        <div className="mt-5">
                            <Label className="text-xs font-medium text-slate-700">Payment</Label>
                            <div className="mt-2 grid grid-cols-2 gap-2">
                                {([
                                    { value: 'cash', label: 'Cash' },
                                    { value: 'mpesa', label: 'M-Pesa' },
                                ] as const).map(option => (
                                    <label
                                        key={option.value}
                                        className={cn(
                                            'cursor-pointer rounded-lg border px-3 py-2 text-center text-sm transition-all',
                                            watch('payment_method') === option.value
                                                ? 'border-orange-400 bg-orange-50 font-semibold text-orange-700'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
                                        )}
                                    >
                                        <input type="radio" value={option.value} {...register('payment_method')} className="sr-only" />
                                        {option.label}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <Button
                            type="submit"
                            disabled={submitting}
                            className="mt-5 w-full bg-orange-500 shadow-sm shadow-orange-500/20 hover:bg-orange-600"
                        >
                            {submitting
                                ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Saving…</>
                                : channel === 'walk_in' ? 'Record counter sale' : 'Create order'}
                        </Button>

                        <p className="mt-3 text-2xs leading-relaxed text-slate-500">
                            {channel === 'walk_in'
                                ? 'The customer gets one SMS: their receipt, the GasPoints they earned and the app link.'
                                : 'The customer gets the usual order confirmation. Assign a rider from the order page.'}
                        </p>
                    </Section>
                </div>
            </form>
        </AdminLayout>
    );
}
