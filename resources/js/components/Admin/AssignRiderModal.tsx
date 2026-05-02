import { router } from '@inertiajs/react';
import { X, ShieldCheck, Star, Truck, UserCheck } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

interface AvailableRider {
    id:                  number;
    name:                string;
    phone:               string;
    avatar_url:          string | null;
    avg_rating:          number;
    total_deliveries:    number;
    is_safety_certified: boolean;
}

interface Props {
    orderId:     number;
    orderNumber: string;
    riders:      AvailableRider[];
    isReassign:  boolean;
    onClose:     () => void;
}

function RiderAvatar({ name, avatarUrl }: { name: string; avatarUrl: string | null }) {
    return avatarUrl ? (
        <img src={avatarUrl} alt={name} className="h-10 w-10 rounded-full object-cover border border-slate-100" />
    ) : (
        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-orange-400 to-orange-600 text-white text-xs font-bold shadow-sm shadow-orange-500/20">
            {name.slice(0, 2).toUpperCase()}
        </div>
    );
}

export default function AssignRiderModal({ orderId, orderNumber, riders, isReassign, onClose }: Props) {
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [reason, setReason]         = useState('');
    const [submitting, setSubmitting] = useState(false);

    function handleSubmit() {
        if (!selectedId) return;
        setSubmitting(true);

        const endpoint = isReassign
            ? `/admin/orders/${orderId}/reassign`
            : `/admin/orders/${orderId}/assign`;

        const data: Record<string, unknown> = { rider_id: selectedId };
        if (isReassign) data.reason = reason || 'Reassigned by admin';

        router.post(endpoint, data, {
            onFinish: () => setSubmitting(false),
            onSuccess: () => onClose(),
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white shadow-2xl overflow-hidden">

                {/* Header */}
                <div className="relative flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                    <div>
                        <p className="text-sm font-semibold text-slate-900">
                            {isReassign ? 'Reassign Rider' : 'Assign Rider'}
                        </p>
                        <p className="text-xs text-slate-400 mt-0.5">Order {orderNumber}</p>
                    </div>
                    <button onClick={onClose} className="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                        <X className="h-4 w-4" />
                    </button>
                </div>

                {/* Rider list */}
                <div className="max-h-72 overflow-y-auto">
                    {riders.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 py-10">
                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                <Truck className="h-6 w-6 text-slate-300" />
                            </div>
                            <p className="text-sm text-slate-400 font-medium">No available riders</p>
                            <p className="text-xs text-slate-300">All riders are currently on delivery or offline.</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-50 px-2 py-2">
                            {riders.map(r => (
                                <button
                                    key={r.id}
                                    onClick={() => setSelectedId(r.id)}
                                    className={cn(
                                        'flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left transition-all duration-150',
                                        selectedId === r.id
                                            ? 'bg-orange-50 ring-1 ring-orange-300'
                                            : 'hover:bg-slate-50',
                                    )}
                                >
                                    <RiderAvatar name={r.name} avatarUrl={r.avatar_url} />
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-1.5">
                                            <p className="text-sm font-semibold text-slate-900">{r.name}</p>
                                            {r.is_safety_certified && (
                                                <ShieldCheck className="h-3.5 w-3.5 text-emerald-500 shrink-0" />
                                            )}
                                        </div>
                                        <div className="mt-0.5 flex items-center gap-2 text-[10px] text-slate-400">
                                            <span className="flex items-center gap-0.5">
                                                <Star className="h-2.5 w-2.5 fill-amber-400 text-amber-400" />
                                                {r.avg_rating > 0 ? r.avg_rating.toFixed(1) : '—'}
                                            </span>
                                            <span>·</span>
                                            <span>{r.total_deliveries} deliveries</span>
                                            <span>·</span>
                                            <span>{r.phone}</span>
                                        </div>
                                    </div>
                                    {selectedId === r.id && (
                                        <UserCheck className="h-4 w-4 text-orange-500 shrink-0" />
                                    )}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Reassign reason */}
                {isReassign && (
                    <div className="border-t border-slate-100 px-5 py-3">
                        <label className="block text-xs font-medium text-slate-600 mb-1.5">
                            Reason for reassignment
                        </label>
                        <input
                            type="text"
                            value={reason}
                            onChange={e => setReason(e.target.value)}
                            placeholder="e.g. Rider unavailable, wrong route…"
                            className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20"
                        />
                    </div>
                )}

                {/* Footer */}
                <div className="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-3">
                    <Button variant="outline" size="sm" onClick={onClose} className="h-8 text-xs">
                        Cancel
                    </Button>
                    <Button
                        size="sm"
                        disabled={!selectedId || submitting || (isReassign && !reason.trim())}
                        onClick={handleSubmit}
                        className="h-8 bg-orange-500 hover:bg-orange-600 text-xs text-white shadow-sm shadow-orange-500/20 disabled:opacity-50"
                    >
                        {submitting
                            ? 'Saving…'
                            : isReassign
                                ? 'Reassign'
                                : 'Assign Rider'}
                    </Button>
                </div>
            </div>
        </div>
    );
}
