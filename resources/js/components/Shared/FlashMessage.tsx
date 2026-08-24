import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { CheckCircle, XCircle, AlertTriangle, X } from 'lucide-react';
import { cn } from '@/lib/utils';

/** Longer messages get longer on screen. Errors linger. */
function dismissDelay(message: string, isError: boolean): number {
    const readingTime = Math.ceil(message.split(/\s+/).length / 3) * 1000;
    return Math.min(15000, Math.max(isError ? 8000 : 5000, readingTime));
}

export default function FlashMessage() {
    const { flash } = usePage().props as any;
    const [visible, setVisible] = useState(false);
    const [paused, setPaused] = useState(false);

    const type = flash?.success ? 'success' : flash?.error ? 'error' : 'warning';
    const message: string | undefined = flash?.success ?? flash?.error ?? flash?.warning;

    const timerRef = useRef<number | null>(null);

    useEffect(() => {
        if (!message) return;
        setVisible(true);
    }, [message]);

    useEffect(() => {
        if (!visible || !message || paused) return;

        timerRef.current = window.setTimeout(
            () => setVisible(false),
            dismissDelay(message, type === 'error'),
        );

        return () => {
            if (timerRef.current) window.clearTimeout(timerRef.current);
        };
    }, [visible, paused, message, type]);

    if (!visible || !message) return null;

    const styles = {
        success: 'bg-green-50 border-green-400 text-green-900',
        error:   'bg-red-50 border-red-400 text-red-900',
        warning: 'bg-amber-50 border-amber-400 text-amber-900',
    };

    const icons = {
        success: <CheckCircle className="h-5 w-5 text-green-600 shrink-0" />,
        error:   <XCircle className="h-5 w-5 text-red-600 shrink-0" />,
        warning: <AlertTriangle className="h-5 w-5 text-amber-600 shrink-0" />,
    };

    return (
        <div
            // Errors interrupt; success and warnings wait for a pause. Without
            // this the app's only success/error channel was silent to screen
            // readers — an order could be placed with no confirmation announced.
            role={type === 'error' ? 'alert' : 'status'}
            aria-live={type === 'error' ? 'assertive' : 'polite'}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
            className={cn(
                // Sits below the sticky mobile header rather than on top of it,
                // where it used to cover the GasPoints chip and the SOS button.
                'fixed top-16 inset-x-4 z-50 flex items-start gap-3 rounded-lg border px-4 py-3 shadow-lg',
                'sm:inset-x-auto sm:right-4 sm:top-4 sm:max-w-sm',
                styles[type],
            )}
        >
            {icons[type]}
            <p className="flex-1 text-sm font-medium">{message}</p>
            <button
                onClick={() => setVisible(false)}
                aria-label="Dismiss notification"
                className="-m-2 p-2 opacity-70 transition-opacity hover:opacity-100"
            >
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}
