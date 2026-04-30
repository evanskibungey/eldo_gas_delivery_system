import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { cn } from '@/lib/utils';

type RiderStatus = 'available' | 'on_delivery' | 'offline';

interface RiderPosition {
    id:         number;
    name:       string;
    status:     RiderStatus;
    lat:        number | null;
    lng:        number | null;
    heading:    number | null;
    updated_at: string | null;
}

const STATUS_CFG: Record<RiderStatus, { label: string; dot: string; row: string }> = {
    available:   { label: 'Available',   dot: 'bg-emerald-500 animate-pulse', row: '' },
    on_delivery: { label: 'On Delivery', dot: 'bg-amber-500 animate-pulse',   row: 'bg-amber-50/40' },
    offline:     { label: 'Offline',     dot: 'bg-slate-300',                 row: 'opacity-50' },
};

const POLL_INTERVAL_MS = 30_000;

export default function RiderStatusBoard() {
    const [riders, setRiders]     = useState<RiderPosition[]>([]);
    const [loading, setLoading]   = useState(true);
    const [lastSync, setLastSync] = useState<Date | null>(null);
    const [error, setError]       = useState(false);

    async function fetchPositions() {
        try {
            const res = await fetch('/admin/tracking/positions', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error();
            const data: RiderPosition[] = await res.json();
            setRiders(data);
            setLastSync(new Date());
            setError(false);
        } catch {
            setError(true);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        fetchPositions();
        const timer = setInterval(fetchPositions, POLL_INTERVAL_MS);
        return () => clearInterval(timer);
    }, []);

    const counts = {
        available:   riders.filter(r => r.status === 'available').length,
        on_delivery: riders.filter(r => r.status === 'on_delivery').length,
        offline:     riders.filter(r => r.status === 'offline').length,
    };

    return (
        <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />

            {/* Header */}
            <div className="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
                <div className="flex items-center gap-3">
                    <p className="text-sm font-semibold text-slate-800">Rider Status</p>
                    <div className="flex items-center gap-2">
                        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />{counts.available} available
                        </span>
                        {counts.on_delivery > 0 && (
                            <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                                <span className="h-1.5 w-1.5 rounded-full bg-amber-500" />{counts.on_delivery} on delivery
                            </span>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {lastSync && (
                        <span className="text-[10px] text-slate-400">
                            Updated {lastSync.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                        </span>
                    )}
                    <button
                        onClick={fetchPositions}
                        className="flex h-6 w-6 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
                        title="Refresh now"
                    >
                        <RefreshCw className={cn('h-3.5 w-3.5', loading && 'animate-spin')} />
                    </button>
                </div>
            </div>

            {/* Body */}
            {loading && riders.length === 0 ? (
                <div className="px-5 py-8 text-center">
                    <RefreshCw className="mx-auto h-5 w-5 animate-spin text-slate-300" />
                    <p className="mt-2 text-xs text-slate-400">Loading rider positions…</p>
                </div>
            ) : error ? (
                <p className="px-5 py-6 text-center text-xs text-red-500">Failed to load rider positions.</p>
            ) : riders.length === 0 ? (
                <p className="px-5 py-6 text-center text-xs text-slate-400 italic">No active riders.</p>
            ) : (
                <div className="divide-y divide-slate-50 max-h-72 overflow-y-auto">
                    {riders
                        .slice()
                        .sort((a, b) => {
                            const order: Record<RiderStatus, number> = { on_delivery: 0, available: 1, offline: 2 };
                            return order[a.status] - order[b.status];
                        })
                        .map(r => {
                            const cfg = STATUS_CFG[r.status];
                            return (
                                <div key={r.id} className={cn('flex items-center gap-3 px-5 py-2.5 hover:bg-slate-50/40 transition-colors', cfg.row)}>
                                    <span className={cn('h-2 w-2 shrink-0 rounded-full', cfg.dot)} />
                                    <div className="flex-1 min-w-0">
                                        <Link href={`/admin/riders/${r.id}`} className="text-sm font-medium text-slate-800 hover:text-orange-600 transition-colors">
                                            {r.name}
                                        </Link>
                                    </div>
                                    <span className="text-[10px] font-semibold text-slate-400 shrink-0">{cfg.label}</span>
                                    {r.updated_at && (
                                        <span className="text-[10px] text-slate-300 shrink-0 hidden sm:block">{r.updated_at}</span>
                                    )}
                                </div>
                            );
                        })}
                </div>
            )}

            {/* Footer hint */}
            <div className="border-t border-slate-50 px-5 py-2">
                <p className="text-[10px] text-slate-300">Auto-refreshes every 30 s · upgrades to live WebSocket in Phase 10</p>
            </div>
        </div>
    );
}
