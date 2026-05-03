import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { router } from '@inertiajs/react';
import {
    Flame,
    Loader2,
    Eye,
    EyeOff,
    ShieldCheck,
    Zap,
    BarChart3,
    Truck,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useState } from 'react';
import { cn } from '@/lib/utils';

const schema = z.object({
    email:       z.string().email('Enter a valid email address'),
    password:    z.string().min(1, 'Password is required'),
    remember_me: z.boolean().optional(),
});

type FormData = z.infer<typeof schema>;

const features = [
    {
        icon: Zap,
        title: 'Real-time Order Management',
        desc: 'Track every order from placement to doorstep delivery instantly.',
    },
    {
        icon: Truck,
        title: 'Live Rider Dispatch',
        desc: 'Assign, monitor and manage riders with live GPS tracking.',
    },
    {
        icon: BarChart3,
        title: 'Operational Analytics',
        desc: 'Revenue, stock, and performance insights at a glance.',
    },
    {
        icon: ShieldCheck,
        title: 'Safety-First Platform',
        desc: 'Built-in SOS alerts, safety checklists and compliance tracking.',
    },
];

export default function Login() {
    const [loading, setLoading]       = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const {
        register,
        handleSubmit,
        setError,
        formState: { errors },
    } = useForm<FormData>({
        resolver: zodResolver(schema),
        defaultValues: { email: '', password: '', remember_me: false },
    });

    function onSubmit(data: FormData) {
        setLoading(true);
        router.post('/admin/login', data, {
            onError: (errs) => {
                setLoading(false);
                Object.entries(errs).forEach(([field, message]) =>
                    setError(field as keyof FormData, { message: message as string }),
                );
            },
            onFinish: () => setLoading(false),
        });
    }

    return (
        <div className="flex min-h-screen">

            {/* â”€â”€ Left: Brand Panel â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
            <div className="relative hidden lg:flex lg:w-[58%] flex-col justify-between overflow-hidden bg-slate-950 px-14 py-12">

                {/* Background gradient orbs */}
                <div className="pointer-events-none absolute inset-0">
                    <div className="absolute -top-32 -left-32 h-[500px] w-[500px] rounded-full bg-orange-500/10 blur-[120px]" />
                    <div className="absolute bottom-0 right-0 h-[400px] w-[400px] rounded-full bg-orange-600/8 blur-[100px]" />
                    <div className="absolute top-1/2 left-1/2 h-[300px] w-[300px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-amber-500/5 blur-[80px]" />
                </div>

                {/* Subtle grid overlay */}
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.03]"
                    style={{
                        backgroundImage:
                            'linear-gradient(#ffffff 1px, transparent 1px), linear-gradient(90deg, #ffffff 1px, transparent 1px)',
                        backgroundSize: '60px 60px',
                    }}
                />

                {/* Logo */}
                <div className="relative flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-500 shadow-lg shadow-orange-500/30">
                        <Flame className="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p className="text-lg font-bold tracking-tight text-white">EldoGas</p>
                        <p className="text-xs text-slate-500 -mt-0.5">Admin Portal</p>
                    </div>
                </div>

                {/* Hero text */}
                <div className="relative space-y-6">
                    <div className="inline-flex items-center gap-2 rounded-full border border-orange-500/20 bg-orange-500/10 px-3 py-1">
                        <span className="h-1.5 w-1.5 rounded-full bg-orange-400 animate-pulse" />
                        <span className="text-xs font-medium text-orange-300">Operational Command Centre</span>
                    </div>

                    <h1 className="text-4xl font-bold leading-tight tracking-tight text-white">
                        Manage every delivery,
                        <span className="block text-orange-400">from one place.</span>
                    </h1>

                    <p className="text-base text-slate-400 leading-relaxed max-w-md">
                        The complete operations platform for EldoGas — orders, riders,
                        stock and analytics unified in a single, powerful interface.
                    </p>

                    {/* Feature list */}
                    <div className="grid grid-cols-1 gap-3 pt-2">
                        {features.map(({ icon: Icon, title, desc }) => (
                            <div
                                key={title}
                                className="flex items-start gap-3.5 rounded-xl border border-white/5 bg-white/[0.03] px-4 py-3 backdrop-blur-sm"
                            >
                                <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-orange-500/15">
                                    <Icon className="h-4 w-4 text-orange-400" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-white">{title}</p>
                                    <p className="text-xs text-slate-500 mt-0.5 leading-relaxed">{desc}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Footer */}
                <div className="relative">
                    <p className="text-xs text-slate-600">
                        Â© {new Date().getFullYear()} EldoGas · Gas delivered. No stress.
                    </p>
                </div>
            </div>

            {/* â”€â”€ Right: Auth Form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */}
            <div className="flex flex-1 flex-col items-center justify-center bg-white px-8 py-12">

                {/* Mobile logo */}
                <div className="mb-10 flex items-center gap-2 lg:hidden">
                    <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-orange-500">
                        <Flame className="h-5 w-5 text-white" />
                    </div>
                    <span className="text-lg font-bold">EldoGas Admin</span>
                </div>

                <div className="w-full max-w-[400px]">
                    {/* Heading */}
                    <div className="mb-8">
                        <h2 className="text-2xl font-bold tracking-tight text-slate-900">
                            Welcome back
                        </h2>
                        <p className="mt-1.5 text-sm text-slate-500">
                            Sign in to access the EldoGas admin portal
                        </p>
                    </div>

                    <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

                        {/* Email */}
                        <div className="space-y-1.5">
                            <Label
                                htmlFor="email"
                                className="text-sm font-medium text-slate-700"
                            >
                                Email address
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                placeholder="admin@eldogas.co.ke"
                                {...register('email')}
                                className={cn(
                                    'h-11 border-slate-200 bg-slate-50 text-sm placeholder:text-slate-400',
                                    'focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white',
                                    'transition-all duration-150',
                                    errors.email && 'border-red-400 bg-red-50 focus:border-red-400',
                                )}
                            />
                            {errors.email && (
                                <p className="flex items-center gap-1 text-xs text-red-500">
                                    {errors.email.message}
                                </p>
                            )}
                        </div>

                        {/* Password */}
                        <div className="space-y-1.5">
                            <Label
                                htmlFor="password"
                                className="text-sm font-medium text-slate-700"
                            >
                                Password
                            </Label>
                            <div className="relative">
                                <Input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="current-password"
                                    placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢"
                                    {...register('password')}
                                    className={cn(
                                        'h-11 border-slate-200 bg-slate-50 pr-10 text-sm placeholder:text-slate-400',
                                        'focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white',
                                        'transition-all duration-150',
                                        errors.password && 'border-red-400 bg-red-50',
                                    )}
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(p => !p)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                                    tabIndex={-1}
                                >
                                    {showPassword
                                        ? <EyeOff className="h-4 w-4" />
                                        : <Eye className="h-4 w-4" />}
                                </button>
                            </div>
                            {errors.password && (
                                <p className="text-xs text-red-500">{errors.password.message}</p>
                            )}
                        </div>

                        {/* Remember me */}
                        <div className="flex items-center gap-2">
                            <input
                                id="remember_me"
                                type="checkbox"
                                className="h-4 w-4 rounded border-slate-300 accent-orange-500 cursor-pointer"
                                {...register('remember_me')}
                            />
                            <Label
                                htmlFor="remember_me"
                                className="text-sm font-normal text-slate-600 cursor-pointer"
                            >
                                Keep me signed in for 30 days
                            </Label>
                        </div>

                        {/* Submit */}
                        <Button
                            type="submit"
                            disabled={loading}
                            className={cn(
                                'h-11 w-full text-sm font-semibold tracking-wide',
                                'bg-orange-500 hover:bg-orange-600 active:bg-orange-700',
                                'shadow-lg shadow-orange-500/25 hover:shadow-orange-500/40',
                                'transition-all duration-150',
                                loading && 'opacity-80 cursor-not-allowed',
                            )}
                        >
                            {loading ? (
                                <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Signing in…</>
                            ) : (
                                'Sign in to Admin Portal'
                            )}
                        </Button>
                    </form>

                    {/* Security note */}
                    <div className="mt-8 flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3">
                        <ShieldCheck className="h-4 w-4 shrink-0 text-slate-400" />
                        <p className="text-xs text-slate-500">
                            Access is restricted to authorised EldoGas administrators only.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
