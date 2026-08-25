import { useEffect, useState } from 'react';
import { Bell, BellOff, WifiOff } from 'lucide-react';
import { isMuted, setMuted, playNewOrderChime } from '@/lib/notificationSound';
import { useAdminRealtime } from './AdminRealtime';
import { cn } from '@/lib/utils';

/**
 * Sound toggle for new-order alerts, plus a live-connection indicator.
 *
 * Un-muting doubles as the user gesture that unlocks the audio context and the
 * moment we ask for desktop-notification permission — browsers reject both
 * outside a gesture, and asking on page load is the fastest way to get
 * permanently denied.
 */
export default function AlertSettingsButton() {
    const { connected, echoAvailable } = useAdminRealtime();
    const [muted, setMutedState] = useState(false);

    // Read the stored preference after mount: localStorage is not available
    // during the first render in every environment.
    useEffect(() => setMutedState(isMuted()), []);

    function toggle() {
        const next = !muted;
        setMuted(next);
        setMutedState(next);

        if (!next) {
            // Confirm audibly that alerts are back on, and use this gesture to
            // ask about desktop notifications.
            playNewOrderChime();

            if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
                void Notification.requestPermission().catch(() => undefined);
            }
        }
    }

    const offline = !connected;

    return (
        <div className="flex items-center gap-1.5">
            {offline && (
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
                {!muted && connected && (
                    <span className="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-emerald-400" />
                )}
            </button>
        </div>
    );
}
