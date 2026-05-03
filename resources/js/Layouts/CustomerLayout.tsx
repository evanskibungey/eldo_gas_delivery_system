import { PropsWithChildren } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Flame, Star } from 'lucide-react';
import BottomNav from '@/components/Customer/BottomNav';
import TopNav from '@/components/Customer/TopNav';
import Footer from '@/components/Customer/Footer';
import SosButton from '@/components/Customer/SosButton';
import FlashMessage from '@/components/Shared/FlashMessage';

interface Props extends PropsWithChildren {
    title?: string;
    showBack?: boolean;
    backHref?: string;
}

export default function CustomerLayout({ children, title, showBack, backHref }: Props) {
    const { auth, app_name } = usePage().props as any;
    const customer = auth?.customer;
    const appName  = (app_name as string) ?? 'EldoGas';

    return (
        <div className="flex min-h-screen flex-col bg-gray-50">
            {/* Mobile header — hidden on md and above */}
            <header className="md:hidden sticky top-0 z-30 flex h-14 items-center justify-between border-b bg-white px-4 shadow-sm">
                <div className="flex items-center gap-2">
                    {showBack && backHref ? (
                        <Link
                            href={backHref}
                            className="flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground"
                        >
                            â† Back
                        </Link>
                    ) : (
                        <>
                            <Flame className="h-5 w-5 text-orange-500" />
                            <span className="font-bold text-orange-500">{appName}</span>
                        </>
                    )}
                </div>

                {title && !showBack && (
                    <h1 className="absolute left-1/2 -translate-x-1/2 text-sm font-semibold">
                        {title}
                    </h1>
                )}

                <div className="flex items-center gap-2">
                    {/* GasPoints chip */}
                    {customer && (
                        <Link
                            href="/gaspoints"
                            className="flex items-center gap-1 rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600 border border-orange-200"
                        >
                            <Star className="h-3 w-3 fill-orange-400 text-orange-400" />
                            {customer.gaspoints_balance.toLocaleString()} pts
                        </Link>
                    )}

                    <SosButton />
                </div>
            </header>

            {/* Desktop top nav */}
            <TopNav />

            {/* Page content */}
            <main className="flex-1 pb-20 md:pb-0">
                {children}
            </main>

            <Footer />

            {/* Bottom nav — mobile only */}
            <div className="md:hidden">
                <BottomNav />
            </div>

            <FlashMessage />
        </div>
    );
}
