import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link } from '@inertiajs/react';
import { CheckCircle2, MapPin, Home, Copy, Check } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

interface Order {
    id: number;
    order_number: string;
    total_amount: number;
    payment_method: string;
    payment_status: string;
    created_at: string;
}

interface Props {
    order: Order;
    mpesa_till: string;
}

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

export default function Confirmation({ order, mpesa_till }: Props) {
    const isMpesa = order.payment_method === 'mpesa';
    const [copied, setCopied] = useState(false);

    function copyOrderNumber(): void {
        navigator.clipboard.writeText(order.order_number).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    return (
        <CustomerLayout title="Order Confirmed">
            <div className="mx-auto flex max-w-sm flex-col items-center px-4 py-8 md:max-w-lg">
                <div className="relative flex h-24 w-24 items-center justify-center">
                    <div className="absolute inset-0 animate-ping rounded-full bg-emerald-100 opacity-30" />
                    <div className="relative flex h-24 w-24 items-center justify-center rounded-full bg-emerald-100">
                        <CheckCircle2 className="h-12 w-12 text-emerald-500" strokeWidth={1.5} />
                    </div>
                </div>

                <h1 className="mt-5 text-2xl font-bold text-slate-800">Order Placed</h1>
                <p className="mt-1 text-center text-sm text-slate-500">Your order is confirmed and is now moving through dispatch.</p>

                <div className="mt-6 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <button type="button" onClick={copyOrderNumber} className="group flex w-full items-center justify-between border-b border-slate-100 px-4 py-3.5 transition-colors hover:bg-slate-50">
                        <span className="text-xs text-slate-500">Order Number</span>
                        <div className="flex items-center gap-1.5">
                            <span className="font-mono text-sm font-bold text-slate-800">{order.order_number}</span>
                            <span className={cn('transition-all duration-200', copied ? 'text-emerald-500' : 'text-slate-300 group-hover:text-slate-400')}>
                                {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                            </span>
                        </div>
                    </button>

                    <div className="divide-y divide-slate-50">
                        <div className="flex justify-between px-4 py-3 text-sm">
                            <span className="text-slate-500">Total</span>
                            <span className="font-bold text-slate-800">{fmt(order.total_amount)}</span>
                        </div>
                        <div className="flex justify-between px-4 py-3 text-sm">
                            <span className="text-slate-500">Payment method</span>
                            <span className={cn('font-medium capitalize', isMpesa ? 'text-emerald-600' : 'text-slate-700')}>
                                {isMpesa ? 'M-Pesa' : 'Cash on Delivery'}
                            </span>
                        </div>
                        <div className="flex items-center justify-between px-4 py-3 text-sm">
                            <span className="text-slate-500">Payment status</span>
                            <span className={cn('rounded-full border px-2.5 py-1 text-xs font-semibold', PAYMENT_CHIPS[order.payment_status] ?? PAYMENT_CHIPS.pending)}>
                                {PAYMENT_LABELS[order.payment_status] ?? order.payment_status}
                            </span>
                        </div>
                        <div className="flex justify-between px-4 py-3 text-sm">
                            <span className="text-slate-500">Placed at</span>
                            <span className="text-slate-700">{order.created_at}</span>
                        </div>
                    </div>
                </div>

                {isMpesa && mpesa_till && order.payment_status === 'pending' && (
                    <div className="mt-4 w-full rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <p className="text-sm font-semibold text-emerald-800">Complete your M-Pesa payment</p>
                        <div className="mt-2 space-y-1 text-xs text-emerald-700">
                            <p>1. Go to M-Pesa -&gt; Lipa na M-Pesa -&gt; Buy Goods.</p>
                            <p>2. Enter till number <strong className="text-base text-emerald-800">{mpesa_till}</strong>.</p>
                            <p>3. Enter amount <strong>{fmt(order.total_amount)}</strong>.</p>
                            <p>4. Use <strong>{order.order_number}</strong> as your reference.</p>
                        </div>
                    </div>
                )}

                <div className="mt-6 w-full space-y-3">
                    <Link href={`/orders/${order.id}/tracking`} className="flex w-full items-center justify-center gap-2 rounded-xl bg-orange-500 py-3.5 text-sm font-semibold text-white shadow-md shadow-orange-500/20 transition-colors hover:bg-orange-600">
                        <MapPin className="h-4 w-4" />
                        Track Your Order
                    </Link>
                    <Link href="/home" className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white py-3.5 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50">
                        <Home className="h-4 w-4" />
                        Back to Home
                    </Link>
                </div>
            </div>
        </CustomerLayout>
    );
}