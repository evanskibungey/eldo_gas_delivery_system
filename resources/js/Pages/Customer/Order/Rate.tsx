import CustomerLayout from '@/Layouts/CustomerLayout';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Star, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Props {
    order: { id: number; order_number: string };
    rider: { name: string; avg_rating: number | null; avatar_url: string | null } | null;
}

export default function Rate({ order, rider }: Props) {
    const [hovered, setHovered] = useState(0);

    const { data, setData, post, processing, errors } = useForm({
        stars:  0,
        review: '',
    });

    function submit() {
        post(`/orders/${order.id}/rate`, { preserveScroll: true });
    }

    return (
        <CustomerLayout title="Rate Your Order" showBack backHref={`/orders/${order.id}`}>
            <div className="mx-auto max-w-sm md:max-w-lg px-4 py-10 flex flex-col items-center text-center">

                {rider?.avatar_url ? (
                    <img src={rider.avatar_url} alt={rider.name} className="h-20 w-20 rounded-full object-cover ring-4 ring-orange-100" />
                ) : rider ? (
                    <div className="flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-blue-600 text-3xl font-bold ring-4 ring-blue-50">
                        {rider.name.charAt(0)}
                    </div>
                ) : null}

                {rider && (
                    <p className="mt-3 text-base font-semibold text-slate-800">{rider.name}</p>
                )}
                <p className="mt-1 text-xs text-slate-500 font-mono">{order.order_number}</p>

                <p className="mt-6 text-sm text-slate-600">How was your delivery experience?</p>

                {/* Stars */}
                <div className="mt-4 flex gap-2">
                    {[1, 2, 3, 4, 5].map(n => (
                        <button
                            key={n}
                            type="button"
                            onMouseEnter={() => setHovered(n)}
                            onMouseLeave={() => setHovered(0)}
                            onClick={() => setData('stars', n)}
                            className="p-1 transition-transform hover:scale-110"
                        >
                            <Star
                                className={cn(
                                    'h-9 w-9 transition-colors',
                                    n <= (hovered || data.stars)
                                        ? 'fill-amber-400 stroke-amber-400'
                                        : 'fill-none stroke-slate-300',
                                )}
                            />
                        </button>
                    ))}
                </div>
                {errors.stars && <p className="mt-1 text-xs text-red-500">{errors.stars}</p>}

                {/* Review */}
                <textarea
                    value={data.review}
                    onChange={e => setData('review', e.target.value)}
                    placeholder="Leave a comment (optional)…"
                    rows={3}
                    className="mt-5 w-full resize-none rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-orange-400 focus:outline-none text-left"
                />

                <button
                    onClick={submit}
                    disabled={processing || data.stars === 0}
                    className={cn(
                        'mt-5 w-full rounded-xl py-3.5 text-sm font-semibold transition-all',
                        ! processing && data.stars > 0
                            ? 'bg-orange-500 text-white shadow-md shadow-orange-500/25 hover:bg-orange-600'
                            : 'bg-slate-100 text-slate-400 cursor-not-allowed',
                    )}
                >
                    {processing
                        ? <span className="flex items-center justify-center gap-2"><Loader2 className="h-4 w-4 animate-spin" />Submitting…</span>
                        : 'Submit Review'}
                </button>
            </div>
        </CustomerLayout>
    );
}
