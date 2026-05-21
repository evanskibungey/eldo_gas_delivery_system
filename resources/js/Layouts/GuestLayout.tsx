import { PropsWithChildren } from 'react';
import { Flame, Zap, ShieldCheck, CreditCard } from 'lucide-react';
import FlashMessage from '@/components/Shared/FlashMessage';

const FEATURES = [
    { icon: Zap,         text: 'Delivery in ~25 minutes' },
    { icon: ShieldCheck, text: 'Certified cylinders & trained riders' },
    { icon: CreditCard,  text: 'M-Pesa or cash on delivery' },
];

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen">

            {/* ── Left branding panel — desktop only ──────────────────────── */}
            <div className="hidden md:flex md:w-1/2 lg:w-[55%] flex-col items-center justify-center bg-gradient-to-br from-orange-500 via-orange-500 to-amber-400 px-12 py-16 text-white">
                <div className="max-w-sm w-full">
                    {/* Logo */}
                    <div className="flex h-20 w-20 items-center justify-center rounded-3xl bg-white/20 backdrop-blur-sm shadow-xl mb-8">
                        <Flame className="h-10 w-10 text-white" />
                    </div>

                    <h1 className="text-4xl font-bold tracking-tight">EldoGas</h1>
                    <p className="mt-3 text-lg text-orange-100 leading-relaxed">
                        LPG gas delivered to your door.<br />Fast, safe, and reliable.
                    </p>

                    {/* Feature list */}
                    <div className="mt-10 space-y-4">
                        {FEATURES.map(({ icon: Icon, text }) => (
                            <div key={text} className="flex items-center gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/20">
                                    <Icon className="h-4 w-4 text-white" />
                                </div>
                                <span className="text-sm text-orange-50">{text}</span>
                            </div>
                        ))}
                    </div>

                    {/* Testimonial */}
                    <div className="mt-12 rounded-2xl bg-white/10 backdrop-blur-sm p-5 border border-white/20">
                        <p className="text-sm text-orange-50 italic leading-relaxed">
                            "Ordered gas at 8am and it was at my door by 8:25. Rider was professional and the cylinder was sealed."
                        </p>
                        <p className="mt-3 text-xs font-semibold text-orange-200">— Customer, Eldoret</p>
                    </div>
                </div>
            </div>

            {/* ── Right panel — auth form ──────────────────────────────────── */}
            <div className="flex flex-1 flex-col items-center justify-center bg-gray-50 px-4 py-12">

                {/* Logo — mobile only */}
                <div className="md:hidden mb-8 flex flex-col items-center gap-2">
                    <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-500 shadow-lg">
                        <Flame className="h-8 w-8 text-white" />
                    </div>
                    <h1 className="text-2xl font-bold tracking-tight">EldoGas</h1>
                    <p className="text-sm text-muted-foreground">Gas delivered. No stress.</p>
                </div>

                {/* Desktop welcome text */}
                <div className="hidden md:block mb-8 text-center">
                    <h2 className="text-2xl font-bold text-slate-900">Welcome back</h2>
                    <p className="mt-1 text-sm text-slate-500">Sign in with your phone number to continue.</p>
                </div>

                {/* Auth card */}
                <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-sm border">
                    {children}
                </div>
            </div>

            <FlashMessage />
        </div>
    );
}
