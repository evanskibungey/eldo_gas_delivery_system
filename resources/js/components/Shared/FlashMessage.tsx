import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CheckCircle, XCircle, AlertTriangle, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function FlashMessage() {
    const { flash } = usePage().props as any;
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (flash?.success || flash?.error || flash?.warning) {
            setVisible(true);
            const timer = setTimeout(() => setVisible(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    if (!visible) return null;

    const type = flash?.success ? 'success' : flash?.error ? 'error' : 'warning';
    const message = flash?.success ?? flash?.error ?? flash?.warning;

    const styles = {
        success: 'bg-green-50 border-green-400 text-green-800',
        error:   'bg-red-50 border-red-400 text-red-800',
        warning: 'bg-amber-50 border-amber-400 text-amber-800',
    };

    const icons = {
        success: <CheckCircle className="h-5 w-5 text-green-500 shrink-0" />,
        error:   <XCircle className="h-5 w-5 text-red-500 shrink-0" />,
        warning: <AlertTriangle className="h-5 w-5 text-amber-500 shrink-0" />,
    };

    return (
        <div
            className={cn(
                'fixed top-4 right-4 z-50 flex items-start gap-3 rounded-lg border px-4 py-3 shadow-lg max-w-sm',
                styles[type],
            )}
        >
            {icons[type]}
            <p className="flex-1 text-sm font-medium">{message}</p>
            <button onClick={() => setVisible(false)} className="ml-2 opacity-60 hover:opacity-100">
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}
