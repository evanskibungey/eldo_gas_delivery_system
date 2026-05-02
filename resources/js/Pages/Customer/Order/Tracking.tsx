import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Phone, Star, ShieldCheck, AlertTriangle, X, ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

interface OrderProps {
    id:                 number;
    order_number:       string;
    status:             string;
    total_amount:       number;
    payment_method:     string;
    delivery_lat:       number | null;
    delivery_lng:       number | null;
    delivery_notes:     string | null;
    size_name:          string | null;
    stage_index:        number;
    has_issue:          boolean;
    issue_type:         string | null;
    rider_assigned_at:  string | null;
    can_cancel:         boolean;
}

interface RiderProps {
    id:           number;
    name:         string;
    phone:        string;
    avg_rating:   number | null;
    avatar_url:   string | null;
    is_certified: boolean;
    lat:          number | null;
    lng:          number | null;
    heading:      number | null;
}

interface Props {
    order:      OrderProps;
    rider:      RiderProps | null;
    mpesa_till: string;
}

const STAGES = [
    { key: 'pending',         label: 'Order Placed' },
    { key: 'rider_assigned',  label: 'Rider Assigned' },
    { key: 'picked_up',       label: 'On the Way' },
    { key: 'delivered',       label: 'Delivered' },
];

const ISSUE_LABELS: Record<string, string> = {
    out_of_stock:    'Out of Stock',
    wrong_cylinder:  'Wrong Cylinder — Correction in Progress',
    rider_no_show:   'Rider No-Show Reported',
    payment_dispute: 'Payment Dispute',
    damaged_cylinder: 'Damaged Cylinder — Safety Alert',
};

const fmt = (n: number) => `KES ${n.toLocaleString()}`;

// ── Modals ─────────────────────────────────────────────────────────────────────

function CancelConfirmModal({ status, onCancel, onConfirm }: {
    status: string;
    onCancel: () => void;
    onConfirm: (reason: string) => void;
}) {
    const [reason, setReason] = useState('');
    const isWarning = status === 'rider_assigned';

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4">
            <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                <p className="text-base font-semibold text-slate-900">Cancel this order?</p>
                {isWarning && (
                    <div className="mt-2 flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2.5">
                        <AlertTriangle className="h-4 w-4 text-amber-500 shrink-0 mt-0.5" />
                        <p className="text-xs text-amber-700">A rider has already been assigned. They will be notified of the cancellation.</p>
                    </div>
                )}
                <textarea
                    value={reason}
                    onChange={e => setReason(e.target.value)}
                    placeholder="Reason (optional)"
                    rows={2}
                    className="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:border-red-300 focus:outline-none resize-none"
                />
                <div className="mt-3 flex gap-3">
                    <button onClick={onCancel} className="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Keep Order
                    </button>
                    <button
                        onClick={() => onConfirm(reason || 'Cancelled by customer')}
                        className="flex-1 rounded-xl bg-red-500 py-2.5 text-sm font-semibold text-white hover:bg-red-600"
                    >
                        Cancel Order
                    </button>
                </div>
            </div>
        </div>
    );
}

function ReportIssueModal({ type, onCancel, onConfirm }: {
    type: 'wrong_cylinder' | 'damaged_cylinder';
    onCancel: () => void;
    onConfirm: (description: string) => void;
}) {
    const [description, setDescription] = useState('');
    const isDamaged = type === 'damaged_cylinder';

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4">
            <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                {isDamaged && (
                    <div className="mb-3 flex items-start gap-2 rounded-lg bg-red-50 border border-red-200 px-3 py-2.5">
                        <AlertTriangle className="h-4 w-4 text-red-500 shrink-0 mt-0.5" />
                        <p className="text-xs text-red-700 font-medium">
                            Safety first — move to a well-ventilated area, away from flames or heat sources. Do not attempt to repair or move the cylinder forcefully.
                        </p>
                    </div>
                )}
                <p className="text-base font-semibold text-slate-900">
                    {isDamaged ? 'Report Damaged / Unsafe Cylinder' : 'Report Wrong Cylinder'}
                </p>
                <p className="mt-1 text-xs text-slate-500">
                    {isDamaged
                        ? 'Describe the issue (e.g. leak, dent, wrong label). Our team will be alerted immediately.'
                        : 'Describe what was wrong (e.g. wrong size, wrong brand).'}
                </p>
                <textarea
                    value={description}
                    onChange={e => setDescription(e.target.value)}
                    placeholder="Describe the issue…"
                    rows={3}
                    className={cn(
                        'mt-3 w-full rounded-lg border px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:outline-none resize-none',
                        isDamaged ? 'border-red-200 focus:border-red-400 focus:ring-2 focus:ring-red-400/20' : 'border-slate-200 focus:border-orange-400',
                    )}
                />
                <div className="mt-3 flex gap-3">
                    <button onClick={onCancel} className="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        <X className="inline h-3.5 w-3.5 mr-1" />Back
                    </button>
                    <button
                        disabled={!description.trim()}
                        onClick={() => description.trim() && onConfirm(description)}
                        className={cn(
                            'flex-1 rounded-xl py-2.5 text-sm font-semibold text-white disabled:opacity-50',
                            isDamaged ? 'bg-red-600 hover:bg-red-700' : 'bg-orange-500 hover:bg-orange-600',
                        )}
                    >
                        {isDamaged ? 'Send Safety Alert' : 'Report Issue'}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Page ───────────────────────────────────────────────────────────────────────

export default function Tracking({ order: initialOrder, rider: initialRider, mpesa_till }: Props) {
    const [stageIndex,    setStageIndex]    = useState(initialOrder.stage_index);
    const [status,        setStatus]        = useState(initialOrder.status);
    const [rider,         setRider]         = useState(initialRider);
    const [showCancel,    setShowCancel]    = useState(false);
    const [reportType,    setReportType]    = useState<'wrong_cylinder' | 'damaged_cylinder' | null>(null);
    const [showIssueMenu, setShowIssueMenu] = useState(false);

    const mapRef  = useRef<HTMLDivElement>(null);
    const mapObj  = useRef<any>(null);
    const markerR = useRef<any>(null);
    const markerD = useRef<any>(null);

    const isActive  = ! ['delivered', 'cancelled'].includes(status);
    const canCancel = initialOrder.can_cancel && ['pending', 'rider_assigned'].includes(status);
    const contactShopOnly = ['picked_up', 'on_the_way', 'correction_in_progress'].includes(status);

    // Rider no-show: button visible when rider_assigned for > 15 min
    const [noShowVisible, setNoShowVisible] = useState(false);
    useEffect(() => {
        if (status !== 'rider_assigned' || !initialOrder.rider_assigned_at) return;
        const assignedAt = new Date(initialOrder.rider_assigned_at).getTime();
        const msSince = Date.now() - assignedAt;
        if (msSince >= 15 * 60 * 1000) {
            setNoShowVisible(true);
        } else {
            const timer = setTimeout(() => setNoShowVisible(true), 15 * 60 * 1000 - msSince);
            return () => clearTimeout(timer);
        }
    }, [status, initialOrder.rider_assigned_at]);

    // WebSocket: subscribe to private order channel for live updates
    useEffect(() => {
        if (status === 'delivered' || status === 'cancelled') return;

        const channel = window.Echo.private(`orders.${initialOrder.id}`);

        // Rider assigned — update rider info and stage
        channel.listen('.rider.assigned', (e: {
            status: string;
            rider_name:  string;
            rider_phone: string;
            rider_lat:   number | null;
            rider_lng:   number | null;
        }) => {
            if (e.status) {
                setStatus(e.status);
                const idx = STAGES.findIndex(s => s.key === e.status);
                if (idx >= 0) setStageIndex(idx);
            }
            if (e.rider_name) {
                setRider(prev => prev
                    ? { ...prev, name: e.rider_name, phone: e.rider_phone, lat: e.rider_lat, lng: e.rider_lng }
                    : null
                );
            }
        });

        // Generic status update broadcast (picked_up, on_the_way, delivered)
        channel.listen('.order.status_updated', (e: { status: string }) => {
            if (e.status) {
                setStatus(e.status);
                const idx = STAGES.findIndex(s => s.key === e.status);
                if (idx >= 0) setStageIndex(idx);
            }
        });

        return () => {
            window.Echo.leave(`orders.${initialOrder.id}`);
        };
    }, [status, initialOrder.id]);

    // Lightweight 30-second poll for rider GPS position only
    useEffect(() => {
        if (status === 'delivered' || status === 'cancelled') return;
        const id = setInterval(async () => {
            try {
                const res  = await fetch(`/orders/${initialOrder.id}/tracking/data`);
                if (!res.ok) return;
                const data = await res.json();
                if (data.rider_lat && data.rider_lng && markerR.current) {
                    markerR.current.setLatLng([data.rider_lat, data.rider_lng]);
                }
            } catch { /* ignore network errors */ }
        }, 30_000);
        return () => clearInterval(id);
    }, [status, initialOrder.id]);

    // Leaflet map
    useEffect(() => {
        if (! mapRef.current) return;
        import('leaflet').then(L => {
            if (mapObj.current) return;
            const defaultLat = initialOrder.delivery_lat ?? -0.05;
            const defaultLng = initialOrder.delivery_lng ?? 35.27;
            const map = L.map(mapRef.current!, { zoomControl: false }).setView([defaultLat, defaultLng], 14);
            mapObj.current = map;
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
            L.control.zoom({ position: 'bottomright' }).addTo(map);
            if (initialOrder.delivery_lat && initialOrder.delivery_lng) {
                const icon = L.divIcon({
                    className: '',
                    html: `<div class="flex h-8 w-8 items-center justify-center rounded-full bg-orange-500 text-white shadow-lg text-sm font-bold border-2 border-white">D</div>`,
                    iconSize: [32, 32], iconAnchor: [16, 32],
                });
                markerD.current = L.marker([initialOrder.delivery_lat, initialOrder.delivery_lng], { icon }).addTo(map);
            }
            if (rider?.lat && rider?.lng) {
                const icon = L.divIcon({
                    className: '',
                    html: `<div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-500 text-white shadow-lg text-sm font-bold border-2 border-white">R</div>`,
                    iconSize: [36, 36], iconAnchor: [18, 18],
                });
                markerR.current = L.marker([rider.lat, rider.lng], { icon }).addTo(map);
            }
        });
        return () => { mapObj.current?.remove(); mapObj.current = null; };
    }, []);

    function submitCancel(reason: string) {
        router.post(`/orders/${initialOrder.id}/cancel`, { reason }, {
            onSuccess: () => setShowCancel(false),
        });
    }

    function submitIssue(description: string) {
        if (!reportType) return;
        const endpoint = reportType === 'wrong_cylinder'
            ? `/orders/${initialOrder.id}/issues/wrong-cylinder`
            : `/orders/${initialOrder.id}/issues/damaged-cylinder`;
        router.post(endpoint, { description }, {
            onSuccess: () => setReportType(null),
        });
    }

    function submitNoShow() {
        router.post(`/orders/${initialOrder.id}/issues/rider-no-show`);
    }

    return (
        <CustomerLayout title="Track Order" showBack backHref={`/orders/${initialOrder.id}`}>
            <div className="flex flex-col min-h-[calc(100dvh-56px)] md:min-h-[calc(100dvh-64px)]">

                {/* Map */}
                <div ref={mapRef} className="h-56 md:h-96 w-full bg-slate-100" />

                <div className="flex-1 mx-auto w-full max-w-sm md:max-w-4xl px-4 py-4 space-y-4">

                    {/* Issue banner */}
                    {initialOrder.has_issue && initialOrder.issue_type && (
                        <div className={cn(
                            'flex items-start gap-2.5 rounded-xl border px-4 py-3',
                            initialOrder.issue_type === 'damaged_cylinder'
                                ? 'border-red-300 bg-red-50'
                                : 'border-amber-200 bg-amber-50',
                        )}>
                            <AlertTriangle className={cn(
                                'h-4 w-4 shrink-0 mt-0.5',
                                initialOrder.issue_type === 'damaged_cylinder' ? 'text-red-500' : 'text-amber-500',
                            )} />
                            <div>
                                <p className={cn(
                                    'text-sm font-semibold',
                                    initialOrder.issue_type === 'damaged_cylinder' ? 'text-red-700' : 'text-amber-700',
                                )}>
                                    {ISSUE_LABELS[initialOrder.issue_type] ?? initialOrder.issue_type.replace(/_/g, ' ')}
                                </p>
                                {initialOrder.issue_type === 'damaged_cylinder' && (
                                    <p className="text-xs text-red-600 mt-0.5">
                                        Move the cylinder to a ventilated area. Our team has been alerted.
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Progress */}
                    <section className="rounded-xl bg-white border border-slate-200 p-4">
                        <div className="relative flex justify-between">
                            <div className="absolute top-3.5 left-0 right-0 h-0.5 bg-slate-100" />
                            <div
                                className="absolute top-3.5 left-0 h-0.5 bg-orange-400 transition-all duration-700"
                                style={{ width: `${(stageIndex / (STAGES.length - 1)) * 100}%` }}
                            />
                            {STAGES.map((s, i) => (
                                <div key={s.key} className="relative flex flex-col items-center gap-1.5 z-10">
                                    <div className={cn(
                                        'h-7 w-7 rounded-full border-2 flex items-center justify-center text-xs font-bold transition-all',
                                        i <= stageIndex
                                            ? 'border-orange-500 bg-orange-500 text-white'
                                            : 'border-slate-200 bg-white text-slate-400',
                                    )}>
                                        {i < stageIndex ? '✓' : i + 1}
                                    </div>
                                    <p className={cn(
                                        'text-[10px] text-center w-14',
                                        i <= stageIndex ? 'text-orange-600 font-semibold' : 'text-slate-400',
                                    )}>{s.label}</p>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* Rider card */}
                    {rider && (
                        <section className="rounded-xl bg-white border border-slate-200 p-4 flex items-center gap-3">
                            {rider.avatar_url ? (
                                <img src={rider.avatar_url} alt={rider.name} className="h-12 w-12 rounded-full object-cover shrink-0" />
                            ) : (
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 font-bold text-lg">
                                    {rider.name.charAt(0)}
                                </div>
                            )}
                            <div className="flex-1 min-w-0">
                                <p className="font-semibold text-slate-800">{rider.name}</p>
                                <div className="flex items-center gap-1.5 mt-0.5">
                                    {rider.avg_rating != null && (
                                        <span className="flex items-center gap-0.5 text-xs text-amber-500">
                                            <Star className="h-3 w-3 fill-amber-400 stroke-amber-400" />{rider.avg_rating.toFixed(1)}
                                        </span>
                                    )}
                                    {rider.is_certified && (
                                        <span className="flex items-center gap-0.5 text-[10px] text-green-600 bg-green-50 rounded-full px-1.5 py-0.5">
                                            <ShieldCheck className="h-3 w-3" /> Certified
                                        </span>
                                    )}
                                </div>
                            </div>
                            <a
                                href={`tel:${rider.phone}`}
                                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600 hover:bg-orange-200"
                            >
                                <Phone className="h-5 w-5" />
                            </a>
                        </section>
                    )}

                    {/* Rider no-show — visible after 15 min */}
                    {noShowVisible && status === 'rider_assigned' && !initialOrder.has_issue && (
                        <button
                            onClick={submitNoShow}
                            className="w-full rounded-xl border border-amber-200 bg-amber-50 py-3 text-sm font-medium text-amber-700 hover:bg-amber-100"
                        >
                            My rider hasn't arrived — Report
                        </button>
                    )}

                    {/* Order info */}
                    <section className="rounded-xl bg-white border border-slate-200 p-4 text-sm space-y-1.5">
                        <div className="flex justify-between text-slate-600">
                            <span>Order No.</span>
                            <span className="font-mono font-semibold text-slate-800">{initialOrder.order_number}</span>
                        </div>
                        <div className="flex justify-between text-slate-600">
                            <span>Total</span>
                            <span className="font-bold text-slate-800">{fmt(initialOrder.total_amount)}</span>
                        </div>
                        <div className="flex justify-between text-slate-600">
                            <span>Payment</span>
                            <span className="capitalize text-slate-700">{initialOrder.payment_method.replace('_', ' ')}</span>
                        </div>
                        {initialOrder.delivery_notes && (
                            <div className="flex justify-between text-slate-600">
                                <span>Notes</span>
                                <span className="text-right ml-4 text-slate-700">{initialOrder.delivery_notes}</span>
                            </div>
                        )}
                    </section>

                    {initialOrder.payment_method === 'mpesa' && mpesa_till && status === 'pending' && (
                        <div className="rounded-xl bg-green-50 border border-green-200 p-4 text-xs text-green-800">
                            Pay <strong>{fmt(initialOrder.total_amount)}</strong> to M-Pesa Till <strong>{mpesa_till}</strong> · Ref: <strong>{initialOrder.order_number}</strong>
                        </div>
                    )}

                    {/* Rate button */}
                    {status === 'delivered' && (
                        <Link
                            href={`/orders/${initialOrder.id}/rate`}
                            className="block w-full rounded-xl bg-amber-500 py-3.5 text-center text-sm font-semibold text-white hover:bg-amber-600"
                        >
                            Rate your experience ★
                        </Link>
                    )}

                    {/* Report issue menu */}
                    {isActive && ! initialOrder.has_issue && ['rider_assigned', 'picked_up', 'on_the_way'].includes(status) && (
                        <div className="relative">
                            <button
                                onClick={() => setShowIssueMenu(v => !v)}
                                className="w-full flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500 hover:bg-slate-50"
                            >
                                <span>Report an issue</span>
                                <ChevronDown className={cn('h-4 w-4 transition-transform', showIssueMenu && 'rotate-180')} />
                            </button>
                            {showIssueMenu && (
                                <div className="absolute bottom-full mb-1 left-0 right-0 rounded-xl border border-slate-200 bg-white shadow-lg overflow-hidden z-10">
                                    <button
                                        onClick={() => { setReportType('wrong_cylinder'); setShowIssueMenu(false); }}
                                        className="w-full px-4 py-3 text-left text-sm text-slate-700 hover:bg-slate-50 border-b border-slate-100"
                                    >
                                        Wrong size or brand received
                                    </button>
                                    <button
                                        onClick={() => { setReportType('damaged_cylinder'); setShowIssueMenu(false); }}
                                        className="w-full px-4 py-3 text-left text-sm font-medium text-red-600 hover:bg-red-50"
                                    >
                                        Damaged or unsafe cylinder ⚠️
                                    </button>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Damaged cylinder report on delivered orders */}
                    {status === 'delivered' && !initialOrder.has_issue && (
                        <button
                            onClick={() => setReportType('damaged_cylinder')}
                            className="w-full rounded-xl border border-red-200 bg-red-50 py-3 text-sm font-medium text-red-600 hover:bg-red-100"
                        >
                            Report damaged or unsafe cylinder ⚠️
                        </button>
                    )}

                    {/* Cancel section */}
                    {canCancel && (
                        <button
                            onClick={() => setShowCancel(true)}
                            className="w-full rounded-xl border border-slate-200 py-3 text-sm text-slate-400 hover:text-red-500 hover:border-red-200 hover:bg-red-50 transition-colors"
                        >
                            Cancel order
                        </button>
                    )}
                    {contactShopOnly && (
                        <p className="text-center text-xs text-slate-400 py-2">
                            Your order is already on the way. To cancel, please call the shop directly.
                        </p>
                    )}
                </div>
            </div>

            {/* Modals */}
            {showCancel && (
                <CancelConfirmModal
                    status={status}
                    onCancel={() => setShowCancel(false)}
                    onConfirm={submitCancel}
                />
            )}

            {reportType && (
                <ReportIssueModal
                    type={reportType}
                    onCancel={() => setReportType(null)}
                    onConfirm={submitIssue}
                />
            )}
        </CustomerLayout>
    );
}
