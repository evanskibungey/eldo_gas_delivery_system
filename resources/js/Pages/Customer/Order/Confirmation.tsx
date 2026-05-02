import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link } from '@inertiajs/react';
import { CheckCircle2, MapPin, Home, Copy, Check } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

interface Order {
    id:             number;
    order_number:   string;
    total_amount:   number;
    payment_method: string;
    created_at:     string;
}

interface Props {
    order:      Order;
    mpesa_till: string;
}

const fmt = (n: number) => `KES ${n.toLocaleString()}`;

export default function Confirmation({ order, mpesa_till }: Props) {
    const isMpesa  = order.payment_method === 'mpesa';
    const [copied, setCopied] = useState(false);

    function copyOrderNumber() {
        navigator.clipboard.writeText(order.order_number).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    return (
        <CustomerLayout title="Order Confirmed">
            <div className="mx-auto max-w-sm md:max-w-lg px-4 py-8 flex flex-col items-center">

                {/* Success icon */}
                <div className="relative flex h-24 w-24 items-center justify-center">
                    <div className="absolute inset-0 rounded-full bg-emerald-100 animate-ping opacity-30" />
                    <div className="relative flex h-24 w-24 items-center justify-center rounded-full bg-emerald-100">
                        <CheckCircle2 className="h-12 w-12 text-emerald-500" strokeWidth={1.5} />
                    </div>
                </div>

                <h1 className="mt-5 text-2xl font-bold text-slate-800">Order Placed!</h1>
                <p className="mt-1 text-sm text-slate-500 text-center">
                    Your order is confirmed and being processed.
                </p>

                {/* Order summary card */}
                <div className="mt-6 w-full rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    {/* Order number row — tappable to copy */}
                    <button
                        type="button"
                        onClick={copyOrderNumber}
                        className="w-full flex items-center justify-between px-4 py-3.5 border-b border-slate-100 hover:bg-slate-50 transition-colors group"
                    >
                        <span className="text-xs text-slate-500">Order Number</span>
                        <div className="flex items-center gap-1.5">
                            <span className="font-mono text-sm font-bold text-slate-800">{order.order_number}</span>
                            <span className={cn(
                                'transition-all duration-200',
                                copied ? 'text-emerald-500' : 'text-slate-300 group-hover:text-slate-400',
                            )}>
                                {copied
                                    ? <Check className="h-3.5 w-3.5" />
                                    : <Copy className="h-3.5 w-3.5" />}
                            </span>
                        </div>
                    </button>

                    {/* Detail rows */}
                    <div className="divide-y divide-slate-50">
                        <div className="flex justify-between px-4 py-3 text-sm">
                            <span className="text-slate-500">Total</span>
                            <span className="font-bold text-slate-800">{fmt(order.total_amount)}</span>
                        </div>
                        <div className="flex justify-between px-4 py-3 text-sm">
                            <span className="text-slate-500">Payment</span>
                            <span className={cn(
                                'font-medium capitalize',
                                isMpesa ? 'text-emerald-600' : 'text-slate-700',
                            )}>
                                {order.payment_method === 'mpesa' ? 'M-Pesa' : 'Cash on Delivery'}
                            </span>
                        </div>
                        <div className="flex justify-between px-4 py-3 text-sm">
                            <span className="text-slate-500">Placed at</span>
                            <span className="text-slate-700">{order.created_at}</span>
                        </div>
                    </div>
                </div>

                {/* M-Pesa payment instructions */}
                {isMpesa && mpesa_till && (
                    <div className="mt-4 w-full rounded-2xl bg-emerald-50 border border-emerald-200 p-4">
                        <p className="text-sm font-semibold text-emerald-800">Complete your M-Pesa payment</p>
                        <div className="mt-2 space-y-1 text-xs text-emerald-700">
                            <p>1. Go to <strong>M-Pesa → Lipa na M-Pesa → Buy Goods</strong></p>
                            <p>
                                2. Enter Till Number{' '}
                                <strong className="text-base text-emerald-800">{mpesa_till}</strong>
                            </p>
                            <p>
                                3. Enter amount{' '}
                                <strong>{fmt(order.total_amount)}</strong>
                            </p>
                            <p>
                                4. Use <strong>{order.order_number}</strong> as your reference
                            </p>
                        </div>
                    </div>
                )}

                {/* Actions */}
                <div className="mt-6 w-full space-y-3">
                    <Link
                        href={`/orders/${order.id}/tracking`}
                        className="flex w-full items-center justify-center gap-2 rounded-xl bg-orange-500 py-3.5 text-sm font-semibold text-white shadow-md shadow-orange-500/20 hover:bg-orange-600 transition-colors"
                    >
                        <MapPin className="h-4 w-4" />
                        Track Your Order
                    </Link>
                    <Link
                        href="/home"
                        className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white py-3.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors"
                    >
                        <Home className="h-4 w-4" />
                        Back to Home
                    </Link>
                </div>

            </div>
        </CustomerLayout>
    );
}
