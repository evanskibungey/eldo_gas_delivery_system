import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link } from '@inertiajs/react';
import { Star, TrendingUp, TrendingDown, Gift, Users, Copy, Check, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

// ── Types ─────────────────────────────────────────────────────────────────────

interface Tier {
    label:       string;
    threshold:   number;
    description: string;
}

interface NextTier extends Tier {
    progress: number;
}

interface Transaction {
    id:            number;
    type:          'earned' | 'redeemed' | 'bonus' | 'referral' | 'referral_bonus';
    points:        number;
    balance_after: number;
    description:   string;
    order_id:      number | null;
    created_at:    string;
}

interface Paginated {
    data:          Transaction[];
    current_page:  number;
    last_page:     number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    balance:       number;
    transactions:  Paginated;
    tiers:         Tier[];
    nextTier:      NextTier | null;
    referralCode:  string;
    referralCount: number;
}

// ── Config ────────────────────────────────────────────────────────────────────

const TYPE_CFG: Record<string, { label: string; icon: typeof TrendingUp; color: string; sign: string }> = {
    earned:         { label: 'Earned',         icon: TrendingUp,   color: 'text-emerald-600', sign: '+' },
    redeemed:       { label: 'Redeemed',        icon: TrendingDown, color: 'text-red-500',     sign: '-' },
    bonus:          { label: 'Bonus',           icon: Gift,         color: 'text-violet-600',  sign: '+' },
    referral:       { label: 'Referral',        icon: Users,        color: 'text-blue-600',    sign: '+' },
    referral_bonus: { label: 'Referral Bonus',  icon: Gift,         color: 'text-violet-600',  sign: '+' },
};

const TIER_COLORS: Record<string, { bg: string; border: string; text: string; bar: string }> = {
    Bronze:   { bg: 'bg-amber-50',   border: 'border-amber-200',   text: 'text-amber-700',   bar: 'bg-amber-400'   },
    Silver:   { bg: 'bg-slate-50',   border: 'border-slate-200',   text: 'text-slate-600',   bar: 'bg-slate-400'   },
    Gold:     { bg: 'bg-yellow-50',  border: 'border-yellow-200',  text: 'text-yellow-700',  bar: 'bg-yellow-400'  },
    Platinum: { bg: 'bg-indigo-50',  border: 'border-indigo-200',  text: 'text-indigo-700',  bar: 'bg-indigo-500'  },
};

// ── Sub-components ────────────────────────────────────────────────────────────

function BalanceCard({ balance, referralCode }: { balance: number; referralCode: string }) {
    const isPlatinum = balance >= 5000;
    return (
        <div className={cn(
            'relative overflow-hidden rounded-2xl p-5 text-white shadow-lg',
            isPlatinum
                ? 'bg-gradient-to-br from-indigo-600 to-violet-700'
                : 'bg-gradient-to-br from-orange-500 to-amber-500',
        )}>
            <div className="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10" />
            <div className="absolute -right-2 -bottom-8 h-20 w-20 rounded-full bg-white/10" />
            <p className="text-xs font-semibold uppercase tracking-widest text-white/70">
                {isPlatinum ? '⭐ Platinum Member' : 'GasPoints Balance'}
            </p>
            <div className="mt-2 flex items-baseline gap-2">
                <span className="text-4xl font-black tabular-nums">{balance.toLocaleString()}</span>
                <span className="text-sm font-medium text-white/80">pts</span>
            </div>
            <p className="mt-1 text-xs text-white/70">Your code: <span className="font-mono font-bold">{referralCode}</span></p>
        </div>
    );
}

function TierCard({ tier, balance }: { tier: Tier; balance: number }) {
    const colors = TIER_COLORS[tier.label] ?? TIER_COLORS.Bronze;
    const achieved = balance >= tier.threshold;
    const progress = achieved ? 100 : Math.min(100, Math.round((balance / tier.threshold) * 100));
    const prev = { Bronze: 0, Silver: 500, Gold: 1000, Platinum: 2000 }[tier.label] ?? 0;
    const range = tier.threshold - prev;
    const filled = achieved ? range : Math.min(range, balance - prev);
    const segProgress = range > 0 ? Math.max(0, Math.min(100, Math.round((filled / range) * 100))) : 0;

    return (
        <div className={cn('rounded-xl border p-4', colors.bg, colors.border, achieved ? 'opacity-60' : '')}>
            <div className="flex items-center justify-between">
                <div>
                    <p className={cn('text-sm font-bold', colors.text)}>{tier.label}</p>
                    <p className="text-xs text-slate-500">{tier.description}</p>
                </div>
                <div className="text-right">
                    {achieved ? (
                        <span className={cn('rounded-full px-2 py-0.5 text-[10px] font-bold', colors.bg, colors.text, 'border', colors.border)}>
                            ✓ Unlocked
                        </span>
                    ) : (
                        <p className="text-xs text-slate-500 tabular-nums">
                            {(tier.threshold - balance).toLocaleString()} pts to go
                        </p>
                    )}
                </div>
            </div>
            <div className="mt-3 h-1.5 w-full rounded-full bg-slate-200">
                <div
                    className={cn('h-1.5 rounded-full transition-all', colors.bar)}
                    style={{ width: `${achieved ? 100 : segProgress}%` }}
                />
            </div>
        </div>
    );
}

function TransactionRow({ tx }: { tx: Transaction }) {
    const cfg = TYPE_CFG[tx.type] ?? TYPE_CFG.earned;
    const Icon = cfg.icon;
    const isPositive = tx.points > 0;
    return (
        <div className="flex items-center gap-3 py-3">
            <div className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full', isPositive ? 'bg-emerald-50' : 'bg-red-50')}>
                <Icon className={cn('h-4 w-4', cfg.color)} />
            </div>
            <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-slate-800 truncate">{tx.description}</p>
                <p className="text-[11px] text-slate-400">{tx.created_at}</p>
            </div>
            <div className="text-right shrink-0">
                <p className={cn('text-sm font-bold tabular-nums', isPositive ? 'text-emerald-600' : 'text-red-500')}>
                    {cfg.sign}{Math.abs(tx.points).toLocaleString()} pts
                </p>
                <p className="text-[11px] text-slate-400 tabular-nums">{tx.balance_after.toLocaleString()} bal</p>
            </div>
        </div>
    );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function GasPointsIndex({
    balance, transactions, tiers, nextTier, referralCode, referralCount,
}: Props) {
    const [copied, setCopied] = useState(false);

    function copyCode() {
        navigator.clipboard.writeText(referralCode).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    function shareWhatsApp() {
        const msg = encodeURIComponent(
            `🔥 Order gas easily with EldoGas! Use my referral code *${referralCode}* when you sign up and we both earn bonus GasPoints. Download the app or visit eldogas.co.ke`
        );
        window.open(`https://wa.me/?text=${msg}`, '_blank');
    }

    return (
        <CustomerLayout title="GasPoints" showBack backHref="/home">
            <div className="mx-auto max-w-sm px-4 py-5 space-y-5">

                {/* Balance card */}
                <BalanceCard balance={balance} referralCode={referralCode} />

                {/* Next tier progress */}
                {nextTier && (
                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <p className="text-xs font-semibold text-slate-700">Progress to {nextTier.label}</p>
                            <p className="text-xs text-slate-500 tabular-nums">
                                {balance.toLocaleString()} / {nextTier.threshold.toLocaleString()} pts
                            </p>
                        </div>
                        <div className="h-2 w-full rounded-full bg-slate-100">
                            <div
                                className="h-2 rounded-full bg-gradient-to-r from-orange-400 to-amber-500 transition-all"
                                style={{ width: `${Math.min(100, Math.round((balance / nextTier.threshold) * 100))}%` }}
                            />
                        </div>
                        <p className="mt-1.5 text-xs text-slate-500">{nextTier.description}</p>
                    </div>
                )}

                {/* Redemption tiers */}
                <div>
                    <h2 className="mb-3 text-sm font-bold text-slate-900">Redemption Tiers</h2>
                    <div className="space-y-2">
                        {tiers.map(tier => (
                            <TierCard key={tier.label} tier={tier} balance={balance} />
                        ))}
                    </div>
                </div>

                {/* Referral section */}
                <div className="rounded-xl border border-orange-200 bg-orange-50 p-4">
                    <div className="flex items-center gap-2 mb-3">
                        <Users className="h-4 w-4 text-orange-500" />
                        <h2 className="text-sm font-bold text-slate-900">Refer Friends & Earn</h2>
                    </div>
                    <p className="text-xs text-slate-600 mb-3">
                        Earn <span className="font-semibold text-orange-600">250 points</span> when a friend places their first order
                        using your code. They get a <span className="font-semibold">welcome bonus</span> too!
                    </p>
                    <div className="flex items-center gap-2 mb-3">
                        <div className="flex-1 rounded-lg border border-orange-200 bg-white px-3 py-2">
                            <p className="font-mono text-base font-bold tracking-widest text-slate-800">{referralCode}</p>
                        </div>
                        <button
                            onClick={copyCode}
                            className={cn(
                                'flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition-colors',
                                copied
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                    : 'border-orange-300 bg-white text-orange-700 hover:bg-orange-50',
                            )}
                        >
                            {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                            {copied ? 'Copied!' : 'Copy'}
                        </button>
                    </div>
                    <div className="flex gap-2">
                        <button
                            onClick={shareWhatsApp}
                            className="flex-1 rounded-lg bg-[#25D366] py-2 text-xs font-semibold text-white hover:bg-[#1ebe5d] transition-colors"
                        >
                            Share via WhatsApp
                        </button>
                    </div>
                    {referralCount > 0 && (
                        <p className="mt-2 text-center text-[11px] text-slate-500">
                            {referralCount} friend{referralCount !== 1 ? 's' : ''} referred so far 🎉
                        </p>
                    )}
                </div>

                {/* Transaction history */}
                <div>
                    <h2 className="mb-2 text-sm font-bold text-slate-900">Transaction History</h2>
                    <div className="rounded-xl border border-slate-200 bg-white shadow-sm px-4 divide-y divide-slate-100">
                        {transactions.data.length === 0 ? (
                            <div className="flex flex-col items-center py-10 text-center">
                                <Star className="h-8 w-8 text-slate-200" />
                                <p className="mt-2 text-sm text-slate-400">No transactions yet</p>
                                <p className="text-xs text-slate-300">Place an order to start earning points!</p>
                            </div>
                        ) : (
                            transactions.data.map(tx => (
                                <TransactionRow key={tx.id} tx={tx} />
                            ))
                        )}
                    </div>

                    {/* Pagination */}
                    {transactions.last_page > 1 && (
                        <div className="flex justify-center gap-2 pt-3">
                            {transactions.prev_page_url && (
                                <Link
                                    href={transactions.prev_page_url}
                                    className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    ← Prev
                                </Link>
                            )}
                            <span className="flex items-center px-3 text-xs text-slate-500">
                                {transactions.current_page} / {transactions.last_page}
                            </span>
                            {transactions.next_page_url && (
                                <Link
                                    href={transactions.next_page_url}
                                    className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    Next →
                                </Link>
                            )}
                        </div>
                    )}
                </div>

            </div>
        </CustomerLayout>
    );
}
