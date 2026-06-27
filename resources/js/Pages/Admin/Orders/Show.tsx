import AdminLayout from '@/Layouts/AdminLayout';
import AssignRiderModal from '@/components/Admin/AssignRiderModal';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft, Phone, RefreshCw, Package, Star,
    ShieldCheck, MapPin, AlertCircle, CheckCircle2,
    XCircle, Truck, Clock, CreditCard, ChevronRight,
    UserPen, Ban, ArrowRight, Wrench, PlayCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { OrderStatus } from '@/types/models';

// â”€â”€ Types â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

interface HistoryEntry {
    status:     string;
    note:       string | null;
    actor_type: string | null;
    at:         string | null;
}

interface Addon {
    name:  string | null;
    price: number;
}

interface OrderDetail {
    id:               number;
    order_number:     string;
    status:           OrderStatus;
    order_type:       'swap' | 'new_cylinder';
    size_name:        string | null;
    brand_name:       string | null;
    gas_price:        number;
    cylinder_price:   number;
    delivery_fee:     number;
    addons_total:     number;
    total_amount:     number;
    payment_method:   'cash' | 'mpesa';
    payment_status:   string;
    delivery_lat:     number;
    delivery_lng:     number;
    delivery_notes:   string | null;
    has_issue:            boolean;
    issue_type:           string | null;
    issue_description:    string | null;
    issue_resolved:       boolean;
    cancel_reason:        string | null;
    cancelled_by:         string | null;
    rider_assigned_at:    string | null;
    picked_up_at:         string | null;
    on_the_way_at:        string | null;
    delivered_at:         string | null;
    cancelled_at:         string | null;
    created_at:           string;
    customer:             { id: number; name: string; phone: string } | null;
    rider:                { id: number; name: string; phone: string; avg_rating: number; avatar_url: string | null; is_safety_certified: boolean } | null;
    addons:               Addon[];
    history:              HistoryEntry[];
    can_assign:                   boolean;
    can_reassign:                 boolean;
    can_cancel:                   boolean;
    can_report_out_of_stock:      boolean;
    can_resolve_payment_dispute:  boolean;
    can_resume_delivery:          boolean;
    can_collect_payment:          boolean;
    next_status:                  string | null;
}

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
    order:           OrderDetail;
    availableRiders: AvailableRider[];
}

// â”€â”€ Config â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const STATUS_CFG: Record<string, { label: string; dot: string; chip: string; icon: React.ElementType }> = {
    pending:                { label: 'Pending',        dot: 'bg-amber-400',   chip: 'border-amber-200   bg-amber-50   text-amber-700',   icon: Clock        },
    rider_assigned:         { label: 'Assigned',       dot: 'bg-blue-400',    chip: 'border-blue-200    bg-blue-50    text-blue-700',    icon: Truck        },
    picked_up:              { label: 'Picked Up',      dot: 'bg-indigo-400',  chip: 'border-indigo-200  bg-indigo-50  text-indigo-700',  icon: Package      },
    on_the_way:             { label: 'On the Way',     dot: 'bg-violet-400',  chip: 'border-violet-200  bg-violet-50  text-violet-700',  icon: ArrowRight   },
    correction_in_progress: { label: 'Correction',     dot: 'bg-rose-400',    chip: 'border-rose-200    bg-rose-50    text-rose-700',    icon: Wrench       },
    delivered:              { label: 'Delivered',      dot: 'bg-emerald-400', chip: 'border-emerald-200 bg-emerald-50 text-emerald-700', icon: CheckCircle2 },
    cancelled:              { label: 'Cancelled',      dot: 'bg-red-400',     chip: 'border-red-200     bg-red-50     text-red-600',     icon: XCircle      },
};

const NEXT_STATUS_LABEL: Record<string, string> = {
    picked_up:  'Mark as Picked Up',
    on_the_way: 'Mark as On the Way',
    delivered:  'Mark as Delivered',
};

// â”€â”€ Sub-components â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

function SectionCard({ title, children, className }: { title: string; children: React.ReactNode; className?: string }) {
    return (
        <div className={cn('relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden', className)}>
            <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 to-amber-500" />
            <div className="border-b border-slate-100 px-5 py-3.5">
                <p className="text-sm font-semibold text-slate-800">{title}</p>
            </div>
            <div className="px-5 py-4">{children}</div>
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    const cfg = STATUS_CFG[status] ?? STATUS_CFG.pending;
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold', cfg.chip)}>
            <span className={cn('h-1.5 w-1.5 rounded-full shrink-0', cfg.dot)} />
            {cfg.label}
        </span>
    );
}

function CancelModal({
    orderNumber,
    inventoryRestoreRequired,
    onCancel,
    onConfirm,
}: {
    orderNumber: string;
    inventoryRestoreRequired: boolean;
    onCancel: () => void;
    onConfirm: (reason: string, inventoryReturned: boolean) => void;
}) {
    const [reason, setReason] = useState('');
    const [inventoryReturned, setInventoryReturned] = useState(false);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-50">
                    <Ban className="h-5 w-5 text-red-500" />
                </div>
                <h3 className="mt-3 text-base font-semibold text-slate-900">Cancel Order {orderNumber}?</h3>
                <p className="mt-1 text-sm text-slate-500">
                    This cannot be undone. Provide a reason for cancellation.
                </p>
                <textarea
                    value={reason}
                    onChange={e => setReason(e.target.value)}
                    placeholder="e.g. Customer requested cancellation, out of stock..."
                    rows={3}
                    className="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:border-red-300 focus:outline-none focus:ring-2 focus:ring-red-300/20 resize-none"
                />
                {inventoryRestoreRequired && (
                    <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <p className="text-xs font-medium text-amber-800">
                            This order has already moved beyond rider assignment. Only restore stock if the cylinder or gas physically returned to inventory.
                        </p>
                        <label className="mt-3 flex items-start gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={inventoryReturned}
                                onChange={e => setInventoryReturned(e.target.checked)}
                                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-amber-500 focus:ring-amber-400"
                            />
                            <span>Inventory was physically returned to stock</span>
                        </label>
                    </div>
                )}
                <div className="mt-4 flex gap-3">
                    <Button variant="outline" className="flex-1 h-9 text-sm" onClick={onCancel}>Keep Order</Button>
                    <Button
                        disabled={!reason.trim()}
                        className="flex-1 h-9 bg-red-500 hover:bg-red-600 text-white text-sm disabled:opacity-50"
                        onClick={() => reason.trim() && onConfirm(reason, inventoryReturned)}
                    >
                        Cancel Order
                    </Button>
                </div>
            </div>
        </div>
    );
}

function OutOfStockModal({ orderNumber, onCancel, onConfirm }: {
    orderNumber: string;
    onCancel: () => void;
    onConfirm: (reason: string) => void;
}) {
    const [reason, setReason] = useState('');
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-amber-50">
                    <AlertCircle className="h-5 w-5 text-amber-500" />
                </div>
                <h3 className="mt-3 text-base font-semibold text-slate-900">Mark as Out of Stock</h3>
                <p className="mt-1 text-sm text-slate-500">
                    Order {orderNumber} will be cancelled. Customer will be notified.
                </p>
                <textarea
                    value={reason}
                    onChange={e => setReason(e.target.value)}
                    placeholder="e.g. No 13kg cylinders in stockâ€¦"
                    rows={3}
                    className="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:border-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300/20 resize-none"
                />
                <div className="mt-4 flex gap-3">
                    <Button variant="outline" className="flex-1 h-9 text-sm" onClick={onCancel}>Go Back</Button>
                    <Button
                        disabled={!reason.trim()}
                        className="flex-1 h-9 bg-amber-500 hover:bg-amber-600 text-white text-sm disabled:opacity-50"
                        onClick={() => reason.trim() && onConfirm(reason)}
                    >
                        Confirm
                    </Button>
                </div>
            </div>
        </div>
    );
}

function DeliveryConfirmModal({ order, onCancel, onConfirm }: {
    order: Pick<OrderDetail, 'order_number' | 'payment_method' | 'payment_status'>;
    onCancel: () => void;
    onConfirm: (note: string, paymentCollected: boolean) => void;
}) {
    const [note, setNote]                         = useState('');
    const [paymentCollected, setPaymentCollected] = useState(false);
    const showPaymentCheck = order.payment_method === 'cash' && order.payment_status !== 'collected';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50">
                    <CheckCircle2 className="h-5 w-5 text-emerald-500" />
                </div>
                <h3 className="mt-3 text-base font-semibold text-slate-900">Confirm Delivery</h3>
                <p className="mt-1 text-sm text-slate-500">
                    Order {order.order_number} will be marked as delivered.
                </p>

                <textarea
                    value={note}
                    onChange={e => setNote(e.target.value)}
                    placeholder="Optional delivery note (e.g. Left at gate, customer confirmed receipt)â€¦"
                    rows={3}
                    className="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-300 focus:border-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-300/20 resize-none"
                />

                {showPaymentCheck && (
                    <label className="mt-3 flex items-center gap-2.5 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            checked={paymentCollected}
                            onChange={e => setPaymentCollected(e.target.checked)}
                            className="h-4 w-4 rounded border-slate-300 text-emerald-500 focus:ring-emerald-400"
                        />
                        <span className="text-sm text-slate-700">
                            Cash payment collected from customer
                        </span>
                    </label>
                )}

                <div className="mt-4 flex gap-3">
                    <Button variant="outline" className="flex-1 h-9 text-sm" onClick={onCancel}>Go Back</Button>
                    <Button
                        className="flex-1 h-9 bg-emerald-500 hover:bg-emerald-600 text-white text-sm"
                        onClick={() => onConfirm(note, paymentCollected)}
                    >
                        Confirm Delivery
                    </Button>
                </div>
            </div>
        </div>
    );
}

// â”€â”€ Page â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

export default function OrdersShow({ order, availableRiders }: Props) {
    const [showAssign, setShowAssign]                 = useState(false);
    const [showCancel, setShowCancel]                 = useState(false);
    const [showOutOfStock, setShowOutOfStock]         = useState(false);
    const [showDeliveryConfirm, setShowDeliveryConfirm] = useState(false);
    const [advancing, setAdvancing]                   = useState(false);

    const fmt = (n: number) => `KES ${n.toLocaleString()}`;
    const isSwap = order.order_type === 'swap';
    const statusCfg = STATUS_CFG[order.status] ?? STATUS_CFG.pending;

    function advanceStatus() {
        if (!order.next_status) return;
        if (order.next_status === 'delivered') {
            setShowDeliveryConfirm(true);
            return;
        }
        setAdvancing(true);
        router.post(`/admin/orders/${order.id}/status`, { status: order.next_status }, {
            onFinish: () => setAdvancing(false),
        });
    }

    function confirmDelivery(note: string, paymentCollected: boolean) {
        setShowDeliveryConfirm(false);
        setAdvancing(true);
        router.post(`/admin/orders/${order.id}/status`, {
            status:             'delivered',
            delivery_note:      note || undefined,
            payment_collected:  paymentCollected || undefined,
        }, {
            onFinish: () => setAdvancing(false),
        });
    }

    function confirmCancel(reason: string, inventoryReturned: boolean) {
        const payload: Record<string, unknown> = { reason };

        if (order.inventory_restore_required) {
            payload.inventory_returned = inventoryReturned;
        }

        router.post(`/admin/orders/${order.id}/cancel`, payload, {
            onSuccess: () => setShowCancel(false),
        });
    }

    function confirmOutOfStock(reason: string) {
        router.post(`/admin/orders/${order.id}/issues/out-of-stock`, { reason }, {
            onSuccess: () => setShowOutOfStock(false),
        });
    }

    function resolveDispute(resolution: 'paid' | 'refund') {
        router.post(`/admin/orders/${order.id}/issues/payment-dispute/resolve`, { resolution });
    }

    function resumeDelivery() {
        router.post(`/admin/orders/${order.id}/issues/resolve-correction`);
    }

    function collectPayment() {
        router.post(`/admin/orders/${order.id}/collect-payment`);
    }

    return (
        <AdminLayout title={order.order_number} subtitle="Order details and dispatch">

            {/* Back nav */}
            <div className="mb-5 flex items-center justify-between">
                <Link href="/admin/orders" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 transition-colors">
                    <ArrowLeft className="h-4 w-4" /> Back to Orders
                </Link>
                <div className="flex items-center gap-2">
                    <StatusBadge status={order.status} />
                    <span className="text-xs text-slate-400">{order.created_at}</span>
                </div>
            </div>

            {/* Issue banner */}
            {order.has_issue && (
                <div className="mb-5 flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
                    <div>
                        <p className="text-sm font-semibold text-red-700">Issue reported</p>
                        {order.issue_type && <p className="text-xs text-red-500 mt-0.5 capitalize">{order.issue_type.replace(/_/g, ' ')}</p>}
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">

                {/* â”€â”€ Left column â”€â”€ */}
                <div className="lg:col-span-2 space-y-5">

                    {/* Action bar */}
                    {(order.can_assign || order.can_reassign || order.next_status || order.can_cancel
                        || order.can_report_out_of_stock || order.can_resume_delivery || order.can_collect_payment) && (
                        <div className="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3.5 shadow-sm">
                            <span className="text-xs font-semibold text-slate-400 uppercase tracking-wide mr-1">Actions</span>

                            {order.can_assign && (
                                <Button
                                    size="sm" onClick={() => setShowAssign(true)}
                                    className="h-8 gap-1.5 bg-orange-500 hover:bg-orange-600 text-white text-xs shadow-sm shadow-orange-500/20"
                                >
                                    <Truck className="h-3.5 w-3.5" /> Assign Rider
                                </Button>
                            )}

                            {order.can_reassign && (
                                <Button
                                    size="sm" variant="outline" onClick={() => setShowAssign(true)}
                                    className="h-8 gap-1.5 text-xs border-slate-200 hover:border-blue-300 hover:text-blue-600"
                                >
                                    <UserPen className="h-3.5 w-3.5" /> Reassign
                                </Button>
                            )}

                            {order.can_resume_delivery && (
                                <Button
                                    size="sm" onClick={resumeDelivery}
                                    className="h-8 gap-1.5 bg-violet-500 hover:bg-violet-600 text-white text-xs shadow-sm"
                                >
                                    <PlayCircle className="h-3.5 w-3.5" /> Resume Delivery
                                </Button>
                            )}

                            {order.next_status && (
                                <Button
                                    size="sm" disabled={advancing} onClick={advanceStatus}
                                    className="h-8 gap-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs"
                                >
                                    <ChevronRight className="h-3.5 w-3.5" />
                                    {advancing ? 'Savingâ€¦' : (NEXT_STATUS_LABEL[order.next_status] ?? 'Advance')}
                                </Button>
                            )}

                            {order.can_collect_payment && (
                                <Button
                                    size="sm" onClick={collectPayment}
                                    className="h-8 gap-1.5 bg-emerald-500 hover:bg-emerald-600 text-white text-xs shadow-sm"
                                >
                                    <CreditCard className="h-3.5 w-3.5" />
                                    {order.payment_method === 'mpesa' ? 'Mark M-Pesa Paid' : 'Mark Cash Collected'}
                                </Button>
                            )}

                            {order.can_report_out_of_stock && (
                                <Button
                                    size="sm" variant="outline" onClick={() => setShowOutOfStock(true)}
                                    className="h-8 gap-1.5 text-xs border-amber-200 text-amber-600 hover:bg-amber-50 hover:border-amber-300"
                                >
                                    <AlertCircle className="h-3.5 w-3.5" /> Out of Stock
                                </Button>
                            )}

                            {order.can_cancel && (
                                <Button
                                    size="sm" variant="outline" onClick={() => setShowCancel(true)}
                                    className="h-8 gap-1.5 text-xs border-red-200 text-red-500 hover:bg-red-50 hover:border-red-300 ml-auto"
                                >
                                    <Ban className="h-3.5 w-3.5" /> Cancel Order
                                </Button>
                            )}
                        </div>
                    )}

                    {/* Order items */}
                    <SectionCard title="Order Summary">
                        <div className="flex items-center gap-3 mb-4 pb-4 border-b border-slate-100">
                            <div className={cn(
                                'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                                isSwap ? 'bg-orange-50' : 'bg-blue-50',
                            )}>
                                {isSwap
                                    ? <RefreshCw className="h-5 w-5 text-orange-500" />
                                    : <Package   className="h-5 w-5 text-blue-500"   />}
                            </div>
                            <div>
                                <p className="font-semibold text-slate-900">
                                    {isSwap ? 'Gas Refill (Swap)' : 'New Cylinder'}
                                </p>
                                <p className="text-sm text-slate-500">
                                    {order.size_name}{order.brand_name ? ` Â· ${order.brand_name}` : ''}
                                </p>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-slate-500">{isSwap ? 'Gas refill' : 'Gas fill'}</span>
                                <span className="text-slate-800 font-medium">{fmt(order.gas_price)}</span>
                            </div>
                            {order.cylinder_price > 0 && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-slate-500">Cylinder</span>
                                    <span className="text-slate-800 font-medium">{fmt(order.cylinder_price)}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-sm">
                                <span className="text-slate-500">Delivery fee</span>
                                <span className="text-slate-800 font-medium">{fmt(order.delivery_fee)}</span>
                            </div>
                            {order.addons_total > 0 && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-slate-500">Add-ons</span>
                                    <span className="text-slate-800 font-medium">{fmt(order.addons_total)}</span>
                                </div>
                            )}
                            <div className="flex justify-between border-t border-slate-100 pt-2 text-sm font-semibold">
                                <span className="text-slate-800">Total</span>
                                <span className="text-slate-900 text-base">{fmt(order.total_amount)}</span>
                            </div>
                        </div>

                        {/* Add-ons detail */}
                        {order.addons.length > 0 && (
                            <div className="mt-4 pt-4 border-t border-slate-100 space-y-1">
                                <p className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Add-ons</p>
                                {order.addons.map((a, i) => (
                                    <div key={i} className="flex justify-between text-xs">
                                        <span className="text-slate-600">{a.name}</span>
                                        <span className="text-slate-500">{fmt(a.price)}</span>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Payment */}
                        <div className="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2">
                            <CreditCard className="h-4 w-4 text-slate-400 shrink-0" />
                            <span className="text-sm text-slate-600 capitalize">{order.payment_method}</span>
                            <span className={cn(
                                'ml-auto rounded-full border px-2 py-0.5 text-[10px] font-semibold capitalize',
                                order.payment_status === 'collected'
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : order.payment_status === 'disputed'
                                        ? 'border-red-200 bg-red-50 text-red-600'
                                        : 'border-amber-200 bg-amber-50 text-amber-700',
                            )}>
                                {order.payment_status}
                            </span>
                        </div>
                    </SectionCard>

                    {/* Payment dispute resolution (9.4) */}
                    {order.can_resolve_payment_dispute && (
                        <div className="relative rounded-xl border border-red-200 bg-red-50 overflow-hidden shadow-sm">
                            <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-red-400 to-rose-500" />
                            <div className="px-5 py-4 flex items-start gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100">
                                    <CreditCard className="h-4 w-4 text-red-600" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-semibold text-red-800">Payment Dispute</p>
                                    <p className="text-xs text-red-600 mt-0.5">
                                        {order.issue_description ?? 'Customer or rider flagged a payment issue. Verify with M-Pesa records before resolving.'}
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        <Button
                                            size="sm"
                                            onClick={() => resolveDispute('paid')}
                                            className="h-8 bg-emerald-500 hover:bg-emerald-600 text-white text-xs gap-1.5"
                                        >
                                            <CheckCircle2 className="h-3.5 w-3.5" /> Mark as Paid
                                        </Button>
                                        <Button
                                            size="sm" variant="outline"
                                            onClick={() => resolveDispute('refund')}
                                            className="h-8 border-red-300 text-red-600 hover:bg-red-100 text-xs gap-1.5"
                                        >
                                            <XCircle className="h-3.5 w-3.5" /> Initiate Refund
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Status timeline */}
                    <SectionCard title="Status Timeline">
                        <div className="relative">
                            <div className="absolute left-[11px] top-2 bottom-2 w-px bg-slate-100" />
                            <div className="space-y-4">
                                {order.history.map((h, i) => {
                                    const cfg = STATUS_CFG[h.status] ?? STATUS_CFG.pending;
                                    const Icon = cfg.icon;
                                    return (
                                        <div key={i} className="relative flex items-start gap-3">
                                            <div className={cn(
                                                'relative z-10 flex h-5.5 w-5.5 shrink-0 items-center justify-center rounded-full border-2 border-white shadow-sm mt-0.5',
                                                cfg.dot,
                                            )}>
                                                <Icon className="h-2.5 w-2.5 text-white" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between">
                                                    <p className="text-xs font-semibold text-slate-800 capitalize">
                                                        {h.status.replace(/_/g, ' ')}
                                                    </p>
                                                    <span className="text-[10px] text-slate-400 shrink-0 ml-2">{h.at}</span>
                                                </div>
                                                {h.note && (
                                                    <p className="text-[11px] text-slate-500 mt-0.5">{h.note}</p>
                                                )}
                                                {h.actor_type && (
                                                    <p className="text-[10px] text-slate-400 capitalize mt-0.5">by {h.actor_type}</p>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </SectionCard>
                </div>

                {/* â”€â”€ Right column â”€â”€ */}
                <div className="space-y-5">

                    {/* Customer */}
                    <SectionCard title="Customer">
                        {order.customer ? (
                            <div className="space-y-3">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-slate-400 to-slate-600 text-white text-xs font-bold shrink-0">
                                        {order.customer.name.slice(0, 2).toUpperCase()}
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">{order.customer.name}</p>
                                        <Link
                                            href={`/admin/customers/${order.customer.id}`}
                                            className="text-[10px] text-orange-500 hover:text-orange-600"
                                        >
                                            View profile â†’
                                        </Link>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 text-sm text-slate-600">
                                    <Phone className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                                    {order.customer.phone}
                                </div>
                            </div>
                        ) : (
                            <p className="text-sm text-slate-400 italic">Customer data unavailable</p>
                        )}
                    </SectionCard>

                    {/* Delivery location */}
                    <SectionCard title="Delivery Location">
                        <div className="space-y-2">
                            <div className="flex items-start gap-2 text-sm text-slate-600">
                                <MapPin className="h-3.5 w-3.5 text-slate-400 shrink-0 mt-0.5" />
                                <span className="text-xs">
                                    {order.delivery_lat.toFixed(6)}, {order.delivery_lng.toFixed(6)}
                                </span>
                            </div>
                            {order.delivery_notes && (
                                <p className="text-xs text-slate-500 bg-slate-50 rounded-lg px-3 py-2">
                                    {order.delivery_notes}
                                </p>
                            )}
                            <a
                                href={`https://maps.google.com/?q=${order.delivery_lat},${order.delivery_lng}`}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 text-xs font-medium text-orange-500 hover:text-orange-600 mt-1"
                            >
                                Open in Maps <ChevronRight className="h-3 w-3" />
                            </a>
                        </div>
                    </SectionCard>

                    {/* Timestamps */}
                    {(order.rider_assigned_at || order.picked_up_at || order.on_the_way_at || order.delivered_at) && (
                        <SectionCard title="Timeline">
                            <div className="space-y-2">
                                {order.rider_assigned_at && (
                                    <div className="flex justify-between text-xs">
                                        <span className="text-slate-500">Assigned</span>
                                        <span className="text-slate-700 font-medium">{order.rider_assigned_at}</span>
                                    </div>
                                )}
                                {order.picked_up_at && (
                                    <div className="flex justify-between text-xs">
                                        <span className="text-slate-500">Picked up</span>
                                        <span className="text-slate-700 font-medium">{order.picked_up_at}</span>
                                    </div>
                                )}
                                {order.on_the_way_at && (
                                    <div className="flex justify-between text-xs">
                                        <span className="text-slate-500">On the way</span>
                                        <span className="text-slate-700 font-medium">{order.on_the_way_at}</span>
                                    </div>
                                )}
                                {order.delivered_at && (
                                    <div className="flex justify-between text-xs">
                                        <span className="text-slate-500">Delivered</span>
                                        <span className="text-emerald-600 font-medium">{order.delivered_at}</span>
                                    </div>
                                )}
                            </div>
                        </SectionCard>
                    )}

                    {/* Rider */}
                    <SectionCard title="Assigned Rider">
                        {order.rider ? (
                            <div className="space-y-3">
                                <div className="flex items-center gap-3">
                                    {order.rider.avatar_url ? (
                                        <img src={order.rider.avatar_url} alt={order.rider.name}
                                            className="h-10 w-10 rounded-full object-cover border border-slate-100" />
                                    ) : (
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-orange-400 to-orange-600 text-white text-xs font-bold">
                                            {order.rider.name.slice(0, 2).toUpperCase()}
                                        </div>
                                    )}
                                    <div>
                                        <div className="flex items-center gap-1.5">
                                            <p className="text-sm font-semibold text-slate-900">{order.rider.name}</p>
                                            {order.rider.is_safety_certified && (
                                                <ShieldCheck className="h-3.5 w-3.5 text-emerald-500" />
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1 text-[10px] text-slate-400">
                                            <Star className="h-2.5 w-2.5 fill-amber-400 text-amber-400" />
                                            {order.rider.avg_rating > 0 ? order.rider.avg_rating.toFixed(1) : 'â€”'}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 text-sm text-slate-600">
                                    <Phone className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                                    {order.rider.phone}
                                </div>
                                <Link
                                    href={`/admin/riders/${order.rider.id}`}
                                    className="inline-flex items-center gap-1 text-xs font-medium text-orange-500 hover:text-orange-600"
                                >
                                    View rider profile <ChevronRight className="h-3 w-3" />
                                </Link>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center gap-2 py-2">
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100">
                                    <Truck className="h-5 w-5 text-slate-300" />
                                </div>
                                <p className="text-xs text-slate-400">No rider assigned yet</p>
                                {order.can_assign && (
                                    <Button
                                        size="sm" onClick={() => setShowAssign(true)}
                                        className="mt-1 h-8 bg-orange-500 hover:bg-orange-600 text-white text-xs shadow-sm shadow-orange-500/20"
                                    >
                                        Assign Rider
                                    </Button>
                                )}
                            </div>
                        )}
                    </SectionCard>

                    {/* Cancellation info */}
                    {order.status === 'cancelled' && order.cancel_reason && (
                        <SectionCard title="Cancellation">
                            <div className="space-y-1">
                                <p className="text-xs text-slate-500">{order.cancel_reason}</p>
                                {order.cancelled_by && (
                                    <p className="text-[10px] text-slate-400 capitalize">Cancelled by {order.cancelled_by}</p>
                                )}
                                {order.cancelled_at && (
                                    <p className="text-[10px] text-slate-400">{order.cancelled_at}</p>
                                )}
                            </div>
                        </SectionCard>
                    )}
                </div>
            </div>

            {/* Modals */}
            {showAssign && (
                <AssignRiderModal
                    orderId={order.id}
                    orderNumber={order.order_number}
                    riders={availableRiders}
                    isReassign={order.can_reassign}
                    onClose={() => setShowAssign(false)}
                />
            )}

            {showCancel && (
                <CancelModal
                    orderNumber={order.order_number}
                    inventoryRestoreRequired={order.inventory_restore_required}
                    onCancel={() => setShowCancel(false)}
                    onConfirm={confirmCancel}
                />
            )}

            {showOutOfStock && (
                <OutOfStockModal
                    orderNumber={order.order_number}
                    onCancel={() => setShowOutOfStock(false)}
                    onConfirm={confirmOutOfStock}
                />
            )}

            {showDeliveryConfirm && (
                <DeliveryConfirmModal
                    order={order}
                    onCancel={() => setShowDeliveryConfirm(false)}
                    onConfirm={confirmDelivery}
                />
            )}
        </AdminLayout>
    );
}
