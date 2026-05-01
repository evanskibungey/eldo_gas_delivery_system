import GuestLayout from '@/Layouts/GuestLayout';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Loader2, RotateCcw } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

interface Props {
    phone: string;
}

const RESEND_COOLDOWN = 60;

export default function OtpVerification({ phone }: Props) {
    const { errors } = usePage().props as any;
    const [digits,    setDigits]   = useState<string[]>(Array(4).fill(''));
    const [loading,   setLoading]  = useState(false);
    const [resending, setResending] = useState(false);
    const [countdown, setCountdown] = useState(RESEND_COOLDOWN);
    const inputs = useRef<(HTMLInputElement | null)[]>([]);

    useEffect(() => {
        if (countdown <= 0) return;
        const t = setInterval(() => setCountdown(c => c - 1), 1000);
        return () => clearInterval(t);
    }, [countdown]);

    const token = digits.join('');
    const complete = token.length === 4 && digits.every(d => /\d/.test(d));

    function onDigitChange(idx: number, val: string) {
        const cleaned = val.replace(/\D/g, '').slice(-1);
        const next = [...digits];
        next[idx] = cleaned;
        setDigits(next);
        if (cleaned && idx < 3) inputs.current[idx + 1]?.focus();
    }

    function onKeyDown(idx: number, e: React.KeyboardEvent) {
        if (e.key === 'Backspace' && ! digits[idx] && idx > 0) {
            inputs.current[idx - 1]?.focus();
        }
        if (e.key === 'ArrowLeft'  && idx > 0) inputs.current[idx - 1]?.focus();
        if (e.key === 'ArrowRight' && idx < 3) inputs.current[idx + 1]?.focus();
    }

    function onPaste(e: React.ClipboardEvent) {
        const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 4);
        if (pasted.length === 4) {
            setDigits(pasted.split(''));
            inputs.current[3]?.focus();
        }
        e.preventDefault();
    }

    function submit() {
        if (! complete) return;
        setLoading(true);
        router.post('/login/verify', { phone, token }, {
            onFinish: () => setLoading(false),
        });
    }

    function resend() {
        setResending(true);
        setDigits(Array(4).fill(''));
        inputs.current[0]?.focus();
        router.post('/login/send-otp', { phone }, {
            onFinish: () => { setResending(false); setCountdown(RESEND_COOLDOWN); },
        });
    }

    const maskedPhone = phone.replace(/(\+254)(\d{3})(\d{3})(\d{3})/, '$1 $2 $3 $4');

    return (
        <GuestLayout>
            <h2 className="text-lg font-semibold text-slate-800 text-center">Enter Verification Code</h2>
            <p className="mt-1 text-sm text-muted-foreground text-center">
                Sent to <span className="font-medium text-slate-700">{maskedPhone}</span>
            </p>

            <div className="mt-6 flex justify-center gap-2" onPaste={onPaste}>
                {digits.map((d, i) => (
                    <input
                        key={i}
                        ref={el => { inputs.current[i] = el; }}
                        type="text"
                        inputMode="numeric"
                        maxLength={1}
                        value={d}
                        onChange={e => onDigitChange(i, e.target.value)}
                        onKeyDown={e => onKeyDown(i, e)}
                        className={cn(
                            'h-12 w-10 rounded-lg border text-center text-lg font-bold transition-all outline-none',
                            'border-slate-200 bg-slate-50 focus:border-orange-400 focus:ring-2 focus:ring-orange-400/20 focus:bg-white',
                            d && 'border-orange-300 bg-orange-50',
                            errors?.token && 'border-red-400 bg-red-50',
                        )}
                        autoFocus={i === 0}
                    />
                ))}
            </div>

            {errors?.token && (
                <p className="mt-2 text-center text-xs text-red-500">{errors.token}</p>
            )}

            <Button
                onClick={submit}
                disabled={loading || ! complete}
                className="mt-5 w-full bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20 gap-2"
            >
                {loading ? (
                    <><Loader2 className="h-4 w-4 animate-spin" /> Verifying…</>
                ) : 'Verify & Sign In'}
            </Button>

            <div className="mt-4 text-center">
                {countdown > 0 ? (
                    <p className="text-xs text-slate-400">
                        Resend code in <span className="font-medium text-slate-600">{countdown}s</span>
                    </p>
                ) : (
                    <button
                        onClick={resend}
                        disabled={resending}
                        className="inline-flex items-center gap-1.5 text-xs font-medium text-orange-500 hover:text-orange-600 disabled:opacity-50"
                    >
                        <RotateCcw className={cn('h-3.5 w-3.5', resending && 'animate-spin')} />
                        {resending ? 'Sending…' : 'Resend code'}
                    </button>
                )}
            </div>
        </GuestLayout>
    );
}
