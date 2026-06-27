import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Phone, Star, ShieldCheck, AlertTriangle, X, ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

interface OrderProps {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    total_amount: number;
    payment_method: string;
    delivery_lat: number | null;
    delivery_lng: number | null;
    delivery_notes: string | null;
    size_name: string | null;
    stage_index: number;
    has_issue: boolean;
    issue_type: string | null;
    rider_assigned_at: string | null;
    can_cancel: boolean;
}

interface RiderProps {
    id: number;
    name: string;
    phone: string;
    avg_rating: number | null;
    avatar_url: string | null;
    is_certified: boolean;
    lat: number | null;
    lng: number | null;
    heading: number | null;
}

interface Props {
    order: OrderProps;
    rider: RiderProps | null;
    mpesa_till: string;
}

interface TrackingResponse {
    status: string;
    payment_status: string;
    has_issue: boolean;
    issue_type: string | null;
    rider_lat: number | null;
    rider_lng: number | null;
    rider_heading: number | null;
    updated_at: string | null;
}

const STAGES = [
    { key: 'pending', label: 'Order Placed' },
    { key: 'rider_assigned', label: 'Rider Assigned' },
    { key: 'picked_up', label: 'Picked Up' },
    { key: 'on_the_way', label: 'On the Way' },
    { key: 'delivered', label: 'Delivered' },
];

const ISSUE_LABELS: Record<string, string> = {
    out_of_stock: 'Out of Stock',
    wrong_cylinder: 'Wrong Cylinder - Correction in Progress',
    rider_no_show: 'Rider No-Show Reported',
    payment_dispute: 'Payment Dispute',
    damaged_cylinder: 'Damaged Cylinder - Safety Alert',
};

const PAYMENT_CHIPS: Record<string, string> = {
    pending: 'border-amber-200 bg-amber-50 text-amber-700',
    collected: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    disputed: 'border-red-200 bg-red-50 text-red-600',
    refunded: 'border-slate-200 bg-slate-50 text-slate-600',
};

const PAYMENT_LABELS: Record<string, string> = {
    pending: 'Payment pending',
    collected: 'Payment received',
    disputed: 'Payment disputed',
    refunded: 'Refund pending',
};

const fmt = (amount: number) => `KES ${amount.toLocaleString()}`;

const stageIndexForStatus = (status: string): number => {
    const stageKey = status === 'correction_in_progress' ? 'on_the_way' : status;
    const index = STAGES.findIndex((stage) => stage.key === stageKey);
    return index >= 0 ? index : 0;
};

function CancelConfirmModal({
    status,
    onCancel,
    onConfirm,
}: {
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
                    <div className="mt-2 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                        <p className="text-xs text-amber-700">A rider has already been assigned. They will be notified of the cancellation.</p>
                    </div>
                )}
                <textarea
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                    placeholder="Reason (optional)"
                    rows={2}
                    className="mt-3 w-full resize-none rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:border-red-300 focus:outline-none"
                />
                <div className="mt-3 flex gap-3">
                    <button onClick={onCancel} className="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Keep Order
                    </button>
                    <button onClick={() => onConfirm(reason || 'Cancelled by customer')} className="flex-1 rounded-xl bg-red-500 py-2.5 text-sm font-semibold text-white hover:bg-red-600">
                        Cancel Order
                    </button>
                </div>
            </div>
        </div>
    );
}

function ReportIssueModal({
    type,
    onCancel,
    onConfirm,
}: {
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
                    <div className="mb-3 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
                        <p className="text-xs font-medium text-red-700">
                            Safety first - move to a well-ventilated area, away from flames or heat sources. Do not attempt to repair or force the cylinder.
                        </p>
                    </div>
                )}
                <p className="text-base font-semibold text-slate-900">
                    {isDamaged ? 'Report Damaged or Unsafe Cylinder' : 'Report Wrong Cylinder'}
                </p>
                <p className="mt-1 text-xs text-slate-500">
                    {isDamaged
                        ? 'Describe the issue (for example leak, dent, or wrong label). Our team will be alerted immediately.'
                        : 'Describe what was wrong (for example wrong size or wrong brand).'}
                </p>
                <textarea
                    value={description}
                    onChange={(event) => setDescription(event.target.value)}
                    placeholder="Describe the issue..."
                    rows={3}
                    className={cn(
                        'mt-3 w-full resize-none rounded-lg border px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:outline-none',
                        isDamaged ? 'border-red-200 focus:border-red-400 focus:ring-2 focus:ring-red-400/20' : 'border-slate-200 focus:border-orange-400',
                    )}
                />
                <div className="mt-3 flex gap-3">
                    <button onClick={onCancel} className="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        <X className="mr-1 inline h-3.5 w-3.5" />Back
                    </button>
                    <button
                        disabled={!description.trim()}
                        onClick={() => description.trim() && onConfirm(description.trim())}
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

export default function Tracking({ order: initialOrder, rider: initialRider, mpesa_till }: Props) {
    const [order, setOrder] = useState(initialOrder);
    const [rider, setRider] = useState(initialRider);
    const [showCancel, setShowCancel] = useState(false);
    const [reportType, setReportType] = useState<'wrong_cylinder' | 'damaged_cylinder' | null>(null);
    const [showIssueMenu, setShowIssueMenu] = useState(false);
    const [noShowVisible, setNoShowVisible] = useState(false);

    const mapRef = useRef<HTMLDivElement>(null);
    const mapObj = useRef<any>(null);
    const markerR = useRef<any>(null);
    const markerD = useRef<any>(null);

    const stageIndex = stageIndexForStatus(order.status);
    const isActive = !['delivered', 'cancelled'].includes(order.status);
    const canCancel = order.can_cancel && ['pending', 'rider_assigned'].includes(order.status);
    const contactShopOnly = ['picked_up', 'on_the_way', 'correction_in_progress'].includes(order.status);
    const canReportOrderIssue = isActive && !order.has_issue && ['rider_assigned', 'picked_up', 'on_the_way'].includes(order.status);
    const paymentChip = PAYMENT_CHIPS[order.payment_status] ?? PAYMENT_CHIPS.pending;
    const paymentLabel = PAYMENT_LABELS[order.payment_status] ?? order.payment_status;

    const applyTrackingUpdate = (next: Partial<TrackingResponse> & { status?: string; payment_status?: string }) => {
        setOrder((previous) => {
            const nextStatus = next.status ?? previous.status;
            const nextIssueType = next.issue_type !== undefined ? next.issue_type : previous.issue_type;
            let hasIssue = next.has_issue !== undefined ? next.has_issue : previous.has_issue;
            let issueType = nextIssueType;

            if (previous.status === 'correction_in_progress' && nextStatus === 'on_the_way' && previous.issue_type === 'wrong_cylinder') {
                hasIssue = false;
                issueType = null;
            }

            return {
                ...previous,
                status: nextStatus,
                payment_status: next.payment_status ?? previous.payment_status,
                has_issue: hasIssue,
                issue_type: issueType,
            };
        });
    };

    useEffect(() => {
        if (order.status !== 'rider_assigned' || !order.rider_assigned_at) {
            setNoShowVisible(false);
            return;
        }

        const assignedAt = new Date(order.rider_assigned_at).getTime();
        const elapsed = Date.now() - assignedAt;

        if (elapsed >= 15 * 60 * 1000) {
            setNoShowVisible(true);
            return;
        }

        const timer = window.setTimeout(() => setNoShowVisible(true), 15 * 60 * 1000 - elapsed);
        return () => window.clearTimeout(timer);
    }, [order.status, order.rider_assigned_at]);

    useEffect(() => {
        if (!isActive || !window.Echo) return;

        const channelName = `orders.${order.id}`;
        const channel = window.Echo.private(channelName);

        channel.listen('.rider.assigned', (event: {
            status?: string;
            rider_name?: string;
            rider_phone?: string;
            rider_lat?: number | null;
            rider_lng?: number | null;
        }) => {
            if (event.status) {
                applyTrackingUpdate({ status: event.status });
            }

            if (event.rider_name || event.rider_phone || event.rider_lat !== undefined || event.rider_lng !== undefined) {
                setRider((previous) => ({
                    id: previous?.id ?? 0,
                    name: event.rider_name ?? previous?.name ?? 'Assigned Rider',
                    phone: event.rider_phone ?? previous?.phone ?? '',
                    avg_rating: previous?.avg_rating ?? null,
                    avatar_url: previous?.avatar_url ?? null,
                    is_certified: previous?.is_certified ?? false,
                    lat: event.rider_lat ?? previous?.lat ?? null,
                    lng: event.rider_lng ?? previous?.lng ?? null,
                    heading: previous?.heading ?? null,
                }));
            }
        });

        channel.listen('.order.status_updated', (event: { status?: string; payment_status?: string }) => {
            applyTrackingUpdate({
                status: event.status,
                payment_status: event.payment_status,
            });
        });

        return () => {
            window.Echo.leave(channelName);
        };
    }, [isActive, order.id]);

    useEffect(() => {
        if (!isActive) return;

        const interval = window.setInterval(async () => {
            try {
                const response = await fetch(`/orders/${order.id}/tracking/data`);
                if (!response.ok) return;

                const data: TrackingResponse = await response.json();
                applyTrackingUpdate(data);

                if (data.rider_lat != null && data.rider_lng != null) {
                    setRider((previous) => (
                        previous
                            ? {
                                  ...previous,
                                  lat: data.rider_lat,
                                  lng: data.rider_lng,
                                  heading: data.rider_heading,
                              }
                            : previous
                    ));
                }
            } catch {
                // Ignore polling failures.
            }
        }, 30000);

        return () => window.clearInterval(interval);
    }, [isActive, order.id]);

    useEffect(() => {
        if (!mapRef.current) return;

        let mounted = true;

        import('leaflet').then((Leaflet) => {
            if (!mounted || mapObj.current) return;

            const defaultLat = order.delivery_lat ?? -0.05;
            const defaultLng = order.delivery_lng ?? 35.27;
            const map = Leaflet.map(mapRef.current!, { zoomControl: false }).setView([defaultLat, defaultLng], 14);

            mapObj.current = map;
            Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
            }).addTo(map);
            Leaflet.control.zoom({ position: 'bottomright' }).addTo(map);

            if (order.delivery_lat != null && order.delivery_lng != null) {
                const deliveryIcon = Leaflet.divIcon({
                    className: '',
                    html: '<div class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-white bg-orange-500 text-sm font-bold text-white shadow-lg">D</div>',
                    iconSize: [32, 32],
                    iconAnchor: [16, 32],
                });
                markerD.current = Leaflet.marker([order.delivery_lat, order.delivery_lng], { icon: deliveryIcon }).addTo(map);
            }
        });

        return () => {
            mounted = false;
            mapObj.current?.remove();
            mapObj.current = null;
            markerD.current = null;
            markerR.current = null;
        };
    }, [order.delivery_lat, order.delivery_lng]);

    useEffect(() => {
        if (!mapObj.current || rider?.lat == null || rider?.lng == null) return;

        import('leaflet').then((Leaflet) => {
            if (!mapObj.current) return;

            if (!markerR.current) {
                const riderIcon = Leaflet.divIcon({
                    className: '',
                    html: '<div class="flex h-9 w-9 items-center justify-center rounded-full border-2 border-white bg-blue-500 text-sm font-bold text-white shadow-lg">R</div>',
                    iconSize: [36, 36],
                    iconAnchor: [18, 18],
                });
                markerR.current = Leaflet.marker([rider.lat!, rider.lng!], { icon: riderIcon }).addTo(mapObj.current);
                return;
            }

            markerR.current.setLatLng([rider.lat, rider.lng]);
        });
    }, [rider?.lat, rider?.lng]);

    function submitCancel(reason: string): void {
        router.post(
            `/orders/${order.id}/cancel`,
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => setShowCancel(false),
            },
        );
    }

    function submitIssue(description: string): void {
        if (!reportType) return;

        const endpoint = reportType === 'wrong_cylinder'
            ? `/orders/${order.id}/issues/wrong-cylinder`
            : `/orders/${order.id}/issues/damaged-cylinder`;

        router.post(
            endpoint,
            { description },
            {
                preserveScroll: true,
                onSuccess: () => setReportType(null),
            },
        );
    }

    function submitNoShow(): void {
        router.post(`/orders/${order.id}/issues/rider-no-show`, {}, { preserveScroll: true });
    }

    return (
        <CustomerLayout title="Track Order" showBack backHref={`/orders/${order.id}`}>
            <div className="flex min-h-[calc(100dvh-56px)] flex-col md:min-h-[calc(100dvh-64px)]">
                <div ref={mapRef} className="h-56 w-full bg-slate-100 md:h-96" />

                <div className="mx-auto flex-1 w-full max-w-sm space-y-4 px-4 py-4 md:max-w-4xl">
                    {order.has_issue && order.issue_type && (
                        <div className={cn('flex items-start gap-2.5 rounded-xl border px-4 py-3', order.issue_type === 'damaged_cylinder' ? 'border-red-300 bg-red-50' : 'border-amber-200 bg-amber-50')}>
                            <AlertTriangle className={cn('mt-0.5 h-4 w-4 shrink-0', order.issue_type === 'damaged_cylinder' ? 'text-red-500' : 'text-amber-500')} />
                            <div>
                                <p className={cn('text-sm font-semibold', order.issue_type === 'damaged_cylinder' ? 'text-red-700' : 'text-amber-700')}>
                                    {ISSUE_LABELS[order.issue_type] ?? order.issue_type.replace(/_/g, ' ')}
                                </p>
                                {order.issue_type === 'damaged_cylinder' && (
                                    <p className="mt-0.5 text-xs text-red-600">Move the cylinder to a ventilated area. Our team has been alerted.</p>
                                )}
                            </div>
                        </div>
                    )}

                    <section className="rounded-xl border border-slate-200 bg-white p-4">
                        <div className="relative flex justify-between">
                            <div className="absolute left-0 right-0 top-3.5 h-0.5 bg-slate-100" />
                            <div className="absolute left-0 top-3.5 h-0.5 bg-orange-400 transition-all duration-700" style={{ width: `${(stageIndex / (STAGES.length - 1)) * 100}%` }} />
                            {STAGES.map((stage, index) => (
                                <div key={stage.key} className="relative z-10 flex flex-col items-center gap-1.5">
                                    <div
                                        className={cn(
                                            'flex h-7 w-7 items-center justify-center rounded-full border-2 text-xs font-bold transition-all',
                                            index <= stageIndex ? 'border-orange-500 bg-orange-500 text-white' : 'border-slate-200 bg-white text-slate-400',
                                        )}
                                    >
                                        {index < stageIndex ? '✓' : index + 1}
                                    </div>
                                    <p className={cn('w-16 text-center text-[10px]', index <= stageIndex ? 'font-semibold text-orange-600' : 'text-slate-400')}>
                                        {stage.label}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    {rider && (
                        <section className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4">
                            {rider.avatar_url ? (
                                <img src={rider.avatar_url} alt={rider.name} className="h-12 w-12 shrink-0 rounded-full object-cover" />
                            ) : (
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100 text-lg font-bold text-blue-600">
                                    {rider.name.charAt(0)}
                                </div>
                            )}
                            <div className="min-w-0 flex-1">
                                <p className="font-semibold text-slate-800">{rider.name}</p>
                                <div className="mt-0.5 flex items-center gap-1.5">
                                    {rider.avg_rating != null && (
                                        <span className="flex items-center gap-0.5 text-xs text-amber-500">
                                            <Star className="h-3 w-3 fill-amber-400 stroke-amber-400" />
                                            {rider.avg_rating.toFixed(1)}
                                        </span>
                                    )}
                                    {rider.is_certified && (
                                        <span className="flex items-center gap-0.5 rounded-full bg-green-50 px-1.5 py-0.5 text-[10px] text-green-600">
                                            <ShieldCheck className="h-3 w-3" /> Certified
                                        </span>
                                    )}
                                </div>
                            </div>
                            <a href={`tel:${rider.phone}`} className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600 hover:bg-orange-200">
                                <Phone className="h-5 w-5" />
                            </a>
                        </section>
                    )}

                    {noShowVisible && order.status === 'rider_assigned' && !order.has_issue && (
                        <button onClick={submitNoShow} className="w-full rounded-xl border border-amber-200 bg-amber-50 py-3 text-sm font-medium text-amber-700 hover:bg-amber-100">
                            My rider has not arrived - Report
                        </button>
                    )}

                    <section className="space-y-2 rounded-xl border border-slate-200 bg-white p-4 text-sm">
                        <div className="flex justify-between text-slate-600">
                            <span>Order No.</span>
                            <span className="font-mono font-semibold text-slate-800">{order.order_number}</span>
                        </div>
                        <div className="flex justify-between text-slate-600">
                            <span>Total</span>
                            <span className="font-bold text-slate-800">{fmt(order.total_amount)}</span>
                        </div>
                        <div className="flex justify-between text-slate-600">
                            <span>Payment method</span>
                            <span className="capitalize text-slate-700">{order.payment_method.replace('_', ' ')}</span>
                        </div>
                        <div className="flex items-center justify-between text-slate-600">
                            <span>Payment status</span>
                            <span className={cn('rounded-full border px-2 py-0.5 text-[11px] font-semibold', paymentChip)}>{paymentLabel}</span>
                        </div>
                        {order.delivery_notes && (
                            <div className="flex justify-between text-slate-600">
                                <span>Notes</span>
                                <span className="ml-4 text-right text-slate-700">{order.delivery_notes}</span>
                            </div>
                        )}
                    </section>

                    {order.payment_method === 'mpesa' && mpesa_till && order.payment_status === 'pending' && order.status !== 'cancelled' && (
                        <div className="rounded-xl border border-green-200 bg-green-50 p-4 text-xs text-green-800">
                            Pay <strong>{fmt(order.total_amount)}</strong> to M-Pesa Till <strong>{mpesa_till}</strong> using reference <strong>{order.order_number}</strong>.
                        </div>
                    )}

                    {order.status === 'delivered' && (
                        <Link href={`/orders/${order.id}/rate`} className="block w-full rounded-xl bg-amber-500 py-3.5 text-center text-sm font-semibold text-white hover:bg-amber-600">
                            Rate your experience
                        </Link>
                    )}

                    {canReportOrderIssue && (
                        <div className="relative">
                            <button
                                onClick={() => setShowIssueMenu((value) => !value)}
                                className="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500 hover:bg-slate-50"
                            >
                                <span>Report an issue</span>
                                <ChevronDown className={cn('h-4 w-4 transition-transform', showIssueMenu && 'rotate-180')} />
                            </button>
                            {showIssueMenu && (
                                <div className="absolute bottom-full left-0 right-0 z-10 mb-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                    <button
                                        onClick={() => {
                                            setReportType('wrong_cylinder');
                                            setShowIssueMenu(false);
                                        }}
                                        className="w-full border-b border-slate-100 px-4 py-3 text-left text-sm text-slate-700 hover:bg-slate-50"
                                    >
                                        Wrong size or brand received
                                    </button>
                                    <button
                                        onClick={() => {
                                            setReportType('damaged_cylinder');
                                            setShowIssueMenu(false);
                                        }}
                                        className="w-full px-4 py-3 text-left text-sm font-medium text-red-600 hover:bg-red-50"
                                    >
                                        Damaged or unsafe cylinder
                                    </button>
                                </div>
                            )}
                        </div>
                    )}

                    {order.status === 'delivered' && !order.has_issue && (
                        <button onClick={() => setReportType('damaged_cylinder')} className="w-full rounded-xl border border-red-200 bg-red-50 py-3 text-sm font-medium text-red-600 hover:bg-red-100">
                            Report damaged or unsafe cylinder
                        </button>
                    )}

                    {canCancel && (
                        <button onClick={() => setShowCancel(true)} className="w-full rounded-xl border border-slate-200 py-3 text-sm text-slate-400 transition-colors hover:border-red-200 hover:bg-red-50 hover:text-red-500">
                            Cancel order
                        </button>
                    )}

                    {contactShopOnly && (
                        <p className="py-2 text-center text-xs text-slate-400">Your order is already on the way. To cancel, please call the shop directly.</p>
                    )}
                </div>
            </div>

            {showCancel && <CancelConfirmModal status={order.status} onCancel={() => setShowCancel(false)} onConfirm={submitCancel} />}
            {reportType && <ReportIssueModal type={reportType} onCancel={() => setReportType(null)} onConfirm={submitIssue} />}
        </CustomerLayout>
    );
}