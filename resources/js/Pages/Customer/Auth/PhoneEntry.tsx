import GuestLayout from '@/Layouts/GuestLayout';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Phone, ArrowRight, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export default function PhoneEntry() {
    const { errors } = usePage().props as any;
    const [local,    setLocal]   = useState('');
    const [loading,  setLoading] = useState(false);

    const digits = local.replace(/\D/g, '');
    // strip leading 0 so 0712345678 and 712345678 both → +254712345678
    const phone  = '+254' + digits.replace(/^0/, '');
    const ready  = digits.replace(/^0/, '').length === 9;

    function onSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (! ready) return;
        setLoading(true);
        router.post('/login/send-otp', { phone }, {
            onFinish: () => setLoading(false),
        });
    }

    return (
        <GuestLayout>
            <h2 className="text-lg font-semibold text-slate-800 text-center">Sign in to EldoGas</h2>
            <p className="mt-1 text-sm text-muted-foreground text-center">
                Enter your Kenyan phone number to receive a verification code.
            </p>

            <form onSubmit={onSubmit} className="mt-6 space-y-4">
                <div>
                    <Label className="text-sm font-medium text-slate-700">Phone Number</Label>
                    <div className="mt-1.5 flex overflow-hidden rounded-lg border border-slate-200 bg-slate-50 focus-within:border-orange-400 focus-within:ring-2 focus-within:ring-orange-400/20 transition-all">
                        <span className="flex items-center gap-1.5 border-r border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 select-none">
                            🇰🇪 +254
                        </span>
                        <Input
                            type="tel"
                            inputMode="numeric"
                            placeholder="0712 345 678"
                            value={local}
                            onChange={e => setLocal(e.target.value.replace(/\D/g, '').slice(0, 10))}
                            className={cn(
                                'flex-1 h-10 rounded-none border-0 bg-transparent px-3 text-sm shadow-none focus-visible:ring-0',
                                errors?.phone && 'text-red-600',
                            )}
                            autoFocus
                        />
                    </div>
                    {errors?.phone && (
                        <p className="mt-1.5 text-xs text-red-500">{errors.phone}</p>
                    )}
                </div>

                <Button
                    type="submit"
                    disabled={loading || ! ready}
                    className="w-full bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20 gap-2"
                >
                    {loading ? (
                        <><Loader2 className="h-4 w-4 animate-spin" /> Sending code…</>
                    ) : (
                        <><Phone className="h-4 w-4" /> Send Verification Code <ArrowRight className="h-4 w-4 ml-auto" /></>
                    )}
                </Button>
            </form>

            <p className="mt-4 text-center text-[11px] text-slate-400">
                A 4-digit code will be sent via SMS. Standard rates apply.
            </p>
        </GuestLayout>
    );
}
