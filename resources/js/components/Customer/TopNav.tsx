import { Link, usePage } from '@inertiajs/react';
import { Flame, Home, ShoppingBag, Star, User } from 'lucide-react';
import SosButton from '@/Components/Customer/SosButton';
import { cn } from '@/lib/utils';

const tabs = [
    { label: 'Home',    href: '/home',      icon: Home },
    { label: 'Orders',  href: '/orders',    icon: ShoppingBag },
    { label: 'Points',  href: '/gaspoints', icon: Star },
    { label: 'Profile', href: '/profile',   icon: User },
];

export default function TopNav() {
    const page = usePage();
    const url = page.url;
    const { auth, app_name } = page.props as any;
    const customer = auth?.customer;
    const appName  = (app_name as string) ?? 'EldoGas';

    return (
        <nav className="hidden md:flex sticky top-0 z-30 h-16 items-center border-b bg-white shadow-sm">
            <div className="mx-auto flex w-full max-w-5xl items-center justify-between px-6">

                {/* Logo */}
                <Link href="/home" className="flex items-center gap-2 shrink-0">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-500 shadow-sm shadow-orange-500/30">
                        <Flame className="h-4 w-4 text-white" />
                    </div>
                    <span className="font-bold text-slate-900 text-lg">{appName}</span>
                </Link>

                {/* Nav links */}
                <div className="flex items-center gap-1">
                    {tabs.map(tab => {
                        const active = url === tab.href || url.startsWith(tab.href + '/');
                        return (
                            <Link
                                key={tab.href}
                                href={tab.href}
                                className={cn(
                                    'flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                    active
                                        ? 'bg-orange-50 text-orange-600'
                                        : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800',
                                )}
                            >
                                <tab.icon className={cn('h-4 w-4', active && 'fill-orange-100')} />
                                {tab.label}
                            </Link>
                        );
                    })}
                </div>

                {/* Right: GasPoints + SOS */}
                <div className="flex items-center gap-3 shrink-0">
                    {customer && (
                        <Link
                            href="/gaspoints"
                            className="flex items-center gap-1.5 rounded-full border border-orange-200 bg-orange-50 px-3 py-1.5 text-sm font-semibold text-orange-600 transition-colors hover:bg-orange-100"
                        >
                            <Star className="h-3.5 w-3.5 fill-orange-400 text-orange-400" />
                            {customer.gaspoints_balance.toLocaleString()} pts
                        </Link>
                    )}
                    <SosButton />
                </div>
            </div>
        </nav>
    );
}
