import { Link, usePage } from '@inertiajs/react';
import { Flame, Phone } from 'lucide-react';

const quickLinks = [
    { label: 'Home',       href: '/home' },
    { label: 'My Orders',  href: '/orders' },
    { label: 'GasPoints',  href: '/gaspoints' },
    { label: 'My Profile', href: '/profile' },
];

function fmt24to12(t: string): string {
    const [h, m] = t.split(':').map(Number);
    const suffix = h >= 12 ? 'PM' : 'AM';
    const hour   = h > 12 ? h - 12 : h === 0 ? 12 : h;
    return m > 0 ? `${hour}:${String(m).padStart(2, '0')} ${suffix}` : `${hour}:00 ${suffix}`;
}

export default function Footer() {
    const year = new Date().getFullYear();
    const { app_name, shop_hours } = usePage().props as any;
    const appName   = (app_name  as string)                           ?? 'EldoGas';
    const openTime  = (shop_hours as { open: string; close: string })?.open  ?? '07:00';
    const closeTime = (shop_hours as { open: string; close: string })?.close ?? '21:00';

    return (
        <footer className="hidden md:block border-t border-slate-200 bg-white mt-auto">
            <div className="mx-auto max-w-5xl px-6 py-10">
                <div className="grid grid-cols-3 gap-8">

                    {/* Brand */}
                    <div>
                        <div className="mb-3 flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-500 shadow-sm shadow-orange-500/30">
                                <Flame className="h-4 w-4 text-white" />
                            </div>
                            <span className="font-bold text-slate-900">{appName}</span>
                        </div>
                        <p className="text-sm text-slate-500 leading-relaxed">
                            Fast, safe LPG gas delivery in Eldoret.<br />
                            Certified cylinders, trained riders.
                        </p>
                        <p className="mt-2 text-xs text-slate-400">
                            Mon – Sun · {fmt24to12(openTime)} – {fmt24to12(closeTime)}
                        </p>
                    </div>

                    {/* Quick Links */}
                    <div>
                        <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Quick Links
                        </p>
                        <div className="space-y-2">
                            {quickLinks.map(link => (
                                <div key={link.href}>
                                    <Link
                                        href={link.href}
                                        className="text-sm text-slate-500 transition-colors hover:text-orange-500"
                                    >
                                        {link.label}
                                    </Link>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Safety */}
                    <div>
                        <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Safety
                        </p>
                        <div className="space-y-2 text-sm text-slate-500">
                            <p>Smell gas? Ventilate immediately.</p>
                            <p>Never store cylinders indoors.</p>
                            <p>Turn off gas at cylinder when not in use.</p>
                            <a
                                href="tel:999"
                                className="mt-3 flex items-center gap-1.5 font-semibold text-red-500 hover:text-red-600 transition-colors"
                            >
                                <Phone className="h-3.5 w-3.5" />
                                Emergency: 999
                            </a>
                        </div>
                    </div>
                </div>

                {/* Copyright */}
                <div className="mt-8 flex items-center justify-between border-t border-slate-100 pt-5">
                    <p className="text-xs text-slate-400">© {year} {appName}. All rights reserved.</p>
                    <p className="text-xs text-slate-400">Gas delivered. No stress.</p>
                </div>
            </div>
        </footer>
    );
}
