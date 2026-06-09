import AdminLayout from '@/Layouts/AdminLayout';
import { router, useForm, usePage } from '@inertiajs/react';
import {
    Settings,
    Clock,
    Truck,
    Percent,
    User,
    Check,
    AlertCircle,
    ChevronRight,
} from 'lucide-react';
import { useState, FormEvent } from 'react';
import { cn } from '@/lib/utils';

// ── Types ─────────────────────────────────────────────────────────────────────

interface SystemSettings {
    app_name:            string;
    shop_always_open:    string;
    shop_open_time:      string;
    shop_close_time:     string;
    delivery_fee_mode:   'per_size' | 'flat_rate' | 'per_km';
    delivery_base_fee:   string;
    delivery_per_km_fee: string;
    shop_lat:            string;
    shop_lng:            string;
    commission_rate:     string;
}

interface Account {
    name:  string;
    email: string;
}

interface Props {
    settings: SystemSettings;
    account:  Account;
}

// ── Tab config ────────────────────────────────────────────────────────────────

const TABS = [
    { id: 'general',   label: 'General',       icon: Settings },
    { id: 'shop',      label: 'Shop Hours',     icon: Clock    },
    { id: 'delivery',  label: 'Delivery',       icon: Truck    },
    { id: 'commission',label: 'Commission',     icon: Percent  },
    { id: 'account',   label: 'My Account',     icon: User     },
] as const;

type TabId = typeof TABS[number]['id'];

// ── Reusable UI ───────────────────────────────────────────────────────────────

function Field({
    label, error, children, hint,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
    hint?: string;
}) {
    return (
        <div className="space-y-1.5">
            <label className="block text-sm font-medium text-slate-700">{label}</label>
            {children}
            {hint && !error && <p className="text-xs text-slate-400">{hint}</p>}
            {error && <p className="flex items-center gap-1 text-xs text-red-500"><AlertCircle className="h-3 w-3" />{error}</p>}
        </div>
    );
}

function Input(props: React.InputHTMLAttributes<HTMLInputElement> & { error?: boolean }) {
    const { error, className, ...rest } = props;
    return (
        <input
            {...rest}
            className={cn(
                'w-full rounded-lg border px-3 py-2 text-sm text-slate-800 outline-none transition-colors',
                'focus:border-orange-400 focus:ring-2 focus:ring-orange-100',
                error ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-white',
                className,
            )}
        />
    );
}

function SaveButton({ processing }: { processing: boolean }) {
    return (
        <button
            type="submit"
            disabled={processing}
            className="flex items-center gap-2 rounded-lg bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-orange-200 transition hover:bg-orange-600 disabled:opacity-60"
        >
            {processing ? (
                <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
            ) : (
                <Check className="h-4 w-4" />
            )}
            Save Changes
        </button>
    );
}

function SectionCard({ title, description, children }: {
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-5">
                <h3 className="text-base font-semibold text-slate-900">{title}</h3>
                {description && <p className="mt-0.5 text-sm text-slate-500">{description}</p>}
            </div>
            {children}
        </div>
    );
}

// ── Flash banner ──────────────────────────────────────────────────────────────

function FlashBanner() {
    const { props } = usePage();
    const flash = (props as any).flash as { success?: string; error?: string } | undefined;
    if (!flash?.success && !flash?.error) return null;
    return (
        <div className={cn(
            'mb-4 flex items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium',
            flash.success
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-red-200 bg-red-50 text-red-700',
        )}>
            <Check className="h-4 w-4 shrink-0" />
            {flash.success ?? flash.error}
        </div>
    );
}

// ── Tab panels ────────────────────────────────────────────────────────────────

function GeneralTab({ settings }: { settings: SystemSettings }) {
    const { data, setData, post, processing, errors } = useForm({
        app_name: settings.app_name,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/admin/settings/general');
    }

    return (
        <SectionCard
            title="General Settings"
            description="Application-wide configuration."
        >
            <form onSubmit={submit} className="space-y-5">
                <Field label="Application Name" error={errors.app_name} hint="Displayed in the browser title and customer-facing UI.">
                    <Input
                        value={data.app_name}
                        onChange={e => setData('app_name', e.target.value)}
                        error={!!errors.app_name}
                        placeholder="EldoGas"
                    />
                </Field>
                <div className="flex justify-end pt-2">
                    <SaveButton processing={processing} />
                </div>
            </form>
        </SectionCard>
    );
}

function ShopHoursTab({ settings }: { settings: SystemSettings }) {
    const { data, setData, post, processing, errors } = useForm({
        always_open:     settings.shop_always_open === '1',
        shop_open_time:  settings.shop_open_time,
        shop_close_time: settings.shop_close_time,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/admin/settings/shop-hours');
    }

    return (
        <SectionCard
            title="Shop Operating Hours"
            description="Set the hours during which customers can place orders."
        >
            <form onSubmit={submit} className="space-y-5">

                {/* 24/7 toggle */}
                <label className={cn(
                    'flex cursor-pointer items-center justify-between gap-3 rounded-lg border p-4 transition-colors',
                    data.always_open
                        ? 'border-orange-300 bg-orange-50'
                        : 'border-slate-200 bg-white',
                )}>
                    <div>
                        <p className="text-sm font-semibold text-slate-800">Open 24/7</p>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Customers can place orders at any time of day or night.
                        </p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={data.always_open}
                        onClick={() => setData('always_open', !data.always_open)}
                        className={cn(
                            'relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors focus:outline-none',
                            data.always_open ? 'bg-orange-500' : 'bg-slate-200',
                        )}
                    >
                        <span className={cn(
                            'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transition-transform',
                            data.always_open ? 'translate-x-5' : 'translate-x-0',
                        )} />
                    </button>
                </label>

                {/* Time pickers — hidden when 24/7 is on */}
                {!data.always_open && (
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Opening Time" error={errors.shop_open_time}>
                            <Input
                                type="time"
                                value={data.shop_open_time}
                                onChange={e => setData('shop_open_time', e.target.value)}
                                error={!!errors.shop_open_time}
                            />
                        </Field>
                        <Field label="Closing Time" error={errors.shop_close_time}>
                            <Input
                                type="time"
                                value={data.shop_close_time}
                                onChange={e => setData('shop_close_time', e.target.value)}
                                error={!!errors.shop_close_time}
                            />
                        </Field>
                    </div>
                )}

                <div className="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                    {data.always_open ? (
                        <>Shop is <span className="font-semibold text-slate-700">always open</span> — customers can place orders 24 hours a day, 7 days a week.</>
                    ) : (
                        <>
                            Shop is shown as <span className="font-semibold text-slate-700">Open</span> between{' '}
                            <span className="font-mono font-semibold text-slate-700">{data.shop_open_time}</span> and{' '}
                            <span className="font-mono font-semibold text-slate-700">{data.shop_close_time}</span>{' '}
                            (Africa/Nairobi time). Customers cannot place orders outside these hours.
                        </>
                    )}
                </div>

                <div className="flex justify-end pt-2">
                    <SaveButton processing={processing} />
                </div>
            </form>
        </SectionCard>
    );
}

function DeliveryTab({ settings }: { settings: SystemSettings }) {
    const { data, setData, post, processing, errors } = useForm({
        delivery_fee_mode:   settings.delivery_fee_mode,
        delivery_base_fee:   settings.delivery_base_fee,
        delivery_per_km_fee: settings.delivery_per_km_fee,
        shop_lat:            settings.shop_lat,
        shop_lng:            settings.shop_lng,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/admin/settings/delivery');
    }

    const modeOptions: { value: string; label: string; description: string }[] = [
        { value: 'per_size',  label: 'Per Cylinder Size',    description: 'Use the delivery fee set on each cylinder size in Pricing.' },
        { value: 'flat_rate', label: 'Flat Rate',            description: 'Charge a single flat delivery fee for all orders.' },
        { value: 'per_km',    label: 'Base + Per Km',        description: 'Charge a base fee plus a per-kilometre rate (requires shop coordinates).' },
    ];

    return (
        <SectionCard
            title="Delivery Fee Settings"
            description="Control how delivery fees are calculated at checkout."
        >
            <form onSubmit={submit} className="space-y-5">
                <Field label="Fee Calculation Mode" error={errors.delivery_fee_mode}>
                    <div className="space-y-2">
                        {modeOptions.map(opt => (
                            <label
                                key={opt.value}
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors',
                                    data.delivery_fee_mode === opt.value
                                        ? 'border-orange-300 bg-orange-50'
                                        : 'border-slate-200 bg-white hover:bg-slate-50',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="delivery_fee_mode"
                                    value={opt.value}
                                    checked={data.delivery_fee_mode === opt.value}
                                    onChange={() => setData('delivery_fee_mode', opt.value as any)}
                                    className="mt-0.5 accent-orange-500"
                                />
                                <div>
                                    <p className="text-sm font-medium text-slate-800">{opt.label}</p>
                                    <p className="text-xs text-slate-500">{opt.description}</p>
                                </div>
                            </label>
                        ))}
                    </div>
                </Field>

                {data.delivery_fee_mode !== 'per_size' && (
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Base Delivery Fee (KES)" error={errors.delivery_base_fee}>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.delivery_base_fee}
                                onChange={e => setData('delivery_base_fee', e.target.value)}
                                error={!!errors.delivery_base_fee}
                                placeholder="100.00"
                            />
                        </Field>
                        {data.delivery_fee_mode === 'per_km' && (
                            <Field label="Per-Km Fee (KES)" error={errors.delivery_per_km_fee}>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.delivery_per_km_fee}
                                    onChange={e => setData('delivery_per_km_fee', e.target.value)}
                                    error={!!errors.delivery_per_km_fee}
                                    placeholder="20.00"
                                />
                            </Field>
                        )}
                    </div>
                )}

                {data.delivery_fee_mode === 'per_km' && (
                    <div>
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Shop Coordinates</p>
                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Latitude" error={errors.shop_lat} hint="e.g. -0.5143">
                                <Input
                                    type="number"
                                    step="any"
                                    value={data.shop_lat}
                                    onChange={e => setData('shop_lat', e.target.value)}
                                    error={!!errors.shop_lat}
                                    placeholder="-0.5143"
                                />
                            </Field>
                            <Field label="Longitude" error={errors.shop_lng} hint="e.g. 35.2698">
                                <Input
                                    type="number"
                                    step="any"
                                    value={data.shop_lng}
                                    onChange={e => setData('shop_lng', e.target.value)}
                                    error={!!errors.shop_lng}
                                    placeholder="35.2698"
                                />
                            </Field>
                        </div>
                    </div>
                )}

                <div className="flex justify-end pt-2">
                    <SaveButton processing={processing} />
                </div>
            </form>
        </SectionCard>
    );
}

function CommissionTab({ settings }: { settings: SystemSettings }) {
    const { data, setData, post, processing, errors } = useForm({
        commission_rate: settings.commission_rate,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/admin/settings/commission');
    }

    const rate = parseFloat(data.commission_rate) || 0;
    const example = 500;
    const commissionOnExample = ((rate / 100) * example).toFixed(2);

    return (
        <SectionCard
            title="Commission Settings"
            description="Configure the commission percentage charged per completed order."
        >
            <form onSubmit={submit} className="space-y-5">
                <Field
                    label="Commission Rate (%)"
                    error={errors.commission_rate}
                    hint="Percentage of each order's total amount retained as commission."
                >
                    <div className="relative">
                        <Input
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            value={data.commission_rate}
                            onChange={e => setData('commission_rate', e.target.value)}
                            error={!!errors.commission_rate}
                            placeholder="10.00"
                            className="pr-8"
                        />
                        <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">%</span>
                    </div>
                </Field>

                <div className="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    Example: On a KES {example.toLocaleString()} order, commission would be{' '}
                    <span className="font-semibold text-slate-800">KES {commissionOnExample}</span>.
                    The rider receives the remainder.
                </div>

                <div className="flex justify-end pt-2">
                    <SaveButton processing={processing} />
                </div>
            </form>
        </SectionCard>
    );
}

function AccountTab({ account }: { account: Account }) {
    const { data, setData, post, processing, errors } = useForm({
        name:                  account.name,
        email:                 account.email,
        password:              '',
        password_confirmation: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/admin/settings/account');
    }

    return (
        <SectionCard
            title="My Account"
            description="Update your admin login credentials."
        >
            <form onSubmit={submit} className="space-y-5">
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Full Name" error={errors.name}>
                        <Input
                            value={data.name}
                            onChange={e => setData('name', e.target.value)}
                            error={!!errors.name}
                            placeholder="Jane Doe"
                        />
                    </Field>
                    <Field label="Email Address" error={errors.email}>
                        <Input
                            type="email"
                            value={data.email}
                            onChange={e => setData('email', e.target.value)}
                            error={!!errors.email}
                            placeholder="admin@example.com"
                        />
                    </Field>
                </div>

                <div className="border-t border-slate-100 pt-5">
                    <p className="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Change Password <span className="normal-case font-normal text-slate-400">(leave blank to keep current)</span>
                    </p>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="New Password" error={errors.password}>
                            <Input
                                type="password"
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                error={!!errors.password}
                                placeholder="••••••••"
                                autoComplete="new-password"
                            />
                        </Field>
                        <Field label="Confirm New Password" error={errors.password_confirmation}>
                            <Input
                                type="password"
                                value={data.password_confirmation}
                                onChange={e => setData('password_confirmation', e.target.value)}
                                error={!!errors.password_confirmation}
                                placeholder="••••••••"
                                autoComplete="new-password"
                            />
                        </Field>
                    </div>
                </div>

                <div className="flex justify-end pt-2">
                    <SaveButton processing={processing} />
                </div>
            </form>
        </SectionCard>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function SettingsIndex({ settings, account }: Props) {
    const [activeTab, setActiveTab] = useState<TabId>('general');

    return (
        <AdminLayout title="Settings">
            <div className="mx-auto max-w-4xl px-4 py-6">

                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-xl font-bold text-slate-900">Settings</h1>
                    <p className="text-sm text-slate-500">Manage system configuration and your account.</p>
                </div>

                <FlashBanner />

                <div className="flex gap-6">

                    {/* Sidebar nav */}
                    <aside className="w-52 shrink-0">
                        <nav className="space-y-0.5">
                            {TABS.map(tab => {
                                const Icon = tab.icon;
                                const active = activeTab === tab.id;
                                return (
                                    <button
                                        key={tab.id}
                                        onClick={() => setActiveTab(tab.id)}
                                        className={cn(
                                            'flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors text-left',
                                            active
                                                ? 'bg-orange-50 text-orange-600'
                                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                                        )}
                                    >
                                        <Icon className={cn('h-4 w-4 shrink-0', active ? 'text-orange-500' : 'text-slate-400')} />
                                        <span className="flex-1">{tab.label}</span>
                                        {active && <ChevronRight className="h-3.5 w-3.5 text-orange-400" />}
                                    </button>
                                );
                            })}
                        </nav>
                    </aside>

                    {/* Panel */}
                    <div className="flex-1 min-w-0">
                        {activeTab === 'general'    && <GeneralTab    settings={settings} />}
                        {activeTab === 'shop'       && <ShopHoursTab  settings={settings} />}
                        {activeTab === 'delivery'   && <DeliveryTab   settings={settings} />}
                        {activeTab === 'commission' && <CommissionTab settings={settings} />}
                        {activeTab === 'account'    && <AccountTab    account={account}   />}
                    </div>

                </div>
            </div>
        </AdminLayout>
    );
}
