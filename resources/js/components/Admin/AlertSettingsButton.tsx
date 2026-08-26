import { useEffect, useState } from 'react';
import { Bell, BellOff, BellRing, WifiOff } from 'lucide-react';
import { armSound, isMuted, isSoundArmed, playNewOrderChime, setMuted } from '@/lib/notificationSound';
import { useAdminRealtime } from './AdminRealtime';
import { cn } from '@/lib/utils';

/**
 * Sound toggle for new-order alerts, plus a live-connection indicator.
 *
 * Browsers refuse to play audio until the page has been interacted with, and
 * an admin watching the dispatch board has often clicked nothing at all. So the
 * button shows three states rather than two — on, off, and "on but the browser
 * has not allowed sound yet" — because a silently blocked chime is
 * indistinguishable from a broken one.
 */
export default function AlertSettingsButton() {
    const { connected, echoAvailable } = useAdminRealtime();
    const [muted, setMutedState] = useState(false);
    const [armed, setArmed] = useState(false);

    // localStorage is not readable during the first render in every environment.
    useEffect(() => setMutedState(isMuted()), []);

    // isSoundArmed() flips from a gesture anywhere on the page, which React has
    // no way to observe. Poll it cheaply so the warning badge clears itself.
    useEffect(() => {
        const check = () => setArmed(isSoundArmed());

        check();
        const interval = window.setInterval(check, 2000);

        return () => window.clearInterval(interval);
    }, []);

    function toggle() {
        const next = !muted;
        setMuted(next);
        setMutedState(next);

        if (!next) {
            // This click is a real user gesture — the one moment the browser
            // will grant audio permission. Use it.
            armSound();
            playNewOrderChime();
            setArmed(isSoundArmed());

            if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
                void Notification.requestPermission().catch(() => undefined);
            }
        }
    }

    const needsGesture = !muted && !armed;

    return (
        <div className="flex items-center gap-1.5">
            {!connected && (
                <span
                    title={echoAvailable
                        ? 'Live updates offline — falling back to polling every 30s'
                        : 'Live updates are not configured on this build — polling every 30s'}
                    className="inline-flex items-center gap-1 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-2xs font-semibold text-amber-700"
                >
                    <WifiOff className="h-3 w-3" />
                    <span className="hidden sm:inline">Live updates offline · polling</span>
                    <span className="sm:hidden">Polling</span>
                </span>
            )}

            {needsGesture && (
                <button
                    onClick={toggle}
                    className="inline-flex items-center gap-1 rounded-md border border-orange-200 bg-orange-50 px-2 py-1 text-2xs font-semibold text-orange-700 transition-colors hover:bg-orange-100"
                    title="Your browser blocks sound until you interact with the page. Click to enable the new-order chime."
                >
                    <BellRing className="h-3 w-3" />
                    <span className="hidden sm:inline">Click to enable sound</span>
                    <span className="sm:hidden">Sound off</span>
                </button>
            )}

            <button
                onClick={toggle}
                aria-pressed={!muted}
                aria-label={muted ? 'Turn on new order sound' : 'Turn off new order sound'}
                title={muted ? 'New order sound is off' : 'New order sound is on'}
                className={cn(
                    'relative flex h-9 w-9 items-center justify-center rounded-lg transition-colors',
                    muted
                        ? 'text-slate-400 hover:bg-slate-100 hover:text-slate-600'
                        : 'text-orange-500 hover:bg-orange-50',
                )}
            >
                {muted ? <BellOff className="h-4 w-4" /> : <Bell className="h-4 w-4" />}
                {!muted && armed && connected && (
                    <span className="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-emerald-400" />
                )}
            </button>
        </div>
    );
}
