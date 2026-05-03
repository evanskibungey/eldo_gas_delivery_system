import { PropsWithChildren } from 'react';
import { Flame } from 'lucide-react';
import FlashMessage from '@/components/Shared/FlashMessage';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-12">
            {/* Logo */}
            <div className="mb-8 flex flex-col items-center gap-2">
                <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-500 shadow-lg">
                    <Flame className="h-8 w-8 text-white" />
                </div>
                <h1 className="text-2xl font-bold tracking-tight">EldoGas</h1>
                <p className="text-sm text-muted-foreground">Gas delivered. No stress.</p>
            </div>

            {/* Card */}
            <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-sm border">
                {children}
            </div>

            <FlashMessage />
        </div>
    );
}
