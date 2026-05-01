import { Link, usePage } from '@inertiajs/react';
import { Home, ShoppingBag, Star, User } from 'lucide-react';
import { cn } from '@/lib/utils';

const tabs = [
    { label: 'Home',    href: '/home',      icon: Home },
    { label: 'Orders',  href: '/orders',    icon: ShoppingBag },
    { label: 'Points',  href: '/gaspoints', icon: Star },
    { label: 'Profile', href: '/profile',   icon: User },
];

export default function BottomNav() {
    const { url } = usePage();

    return (
        <nav className="fixed bottom-0 left-0 right-0 z-40 border-t bg-white pb-safe">
            <div className="flex">
                {tabs.map((tab) => {
                    const active = url === tab.href || url.startsWith(tab.href + '/');
                    return (
                        <Link
                            key={tab.href}
                            href={tab.href}
                            className={cn(
                                'flex flex-1 flex-col items-center gap-1 py-2 text-xs font-medium transition-colors',
                                active
                                    ? 'text-orange-500'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <tab.icon className={cn('h-5 w-5', active && 'fill-orange-100')} />
                            {tab.label}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
