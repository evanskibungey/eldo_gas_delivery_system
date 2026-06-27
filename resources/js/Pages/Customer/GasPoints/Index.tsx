import CustomerLayout from '@/Layouts/CustomerLayout';
import { Link } from '@inertiajs/react';
import { Star, TrendingUp, TrendingDown, Gift, Users, Copy, Check } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

interface Tier {
    label: string;
    threshold: number;
    description: string;
}

interface NextTier extends Tier {
    progress: number;
}

interface Transaction {
    id: number;
    type: string;
    event_code: string | null;
    points: number;
    balance_after: number;
    remaining_points: number | null;
    description: string;
    order_id: number | null;
    expires_at: string | null;
    expired_at: string | null;
    created_at: string;
}

interface Paginated {
    data: Transaction[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface EarnRates {
    new_cylinder: number;
    swap: number;
    large_cylinder: number;
    welcome: number;
    review: number;
    referral: number;
    referral_third_order: number;
}

interface Rules {
    expiry_days: number;
    min_order_amount: number;
    referral_apply_window_days: number;
    referral_reward_window_days: number;
    referral_min_order_amount: number;
    max_balance: number;
}

interface Props {
    enabled: boolean;
    balance: number;
    transactions: Paginated;
    tiers: Tier[];
    nextTier: NextTier | null;
    referralCode: string;
    referralCount: number;
    earnRates: EarnRates;
    rules: Rules;
}

const TIER_COLORS: Record<string, { bg: string; border: string; text: string; bar: string }> = {
    Bronze: { bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-700', bar: 'bg-amber-400' },
    Silver: { bg: 'bg-slate-50', border: 'border-slate-200', text: 'text-slate-600', bar: 'bg-slate-400' },
    Gold: { bg: 'bg-yellow-50', border: 'border-yellow-200', text: 'text-yellow-700', bar: 'bg-yellow-400' },
    Platinum: { bg: 'bg-indigo-50', border: 'border-indigo-200', text: 'text-indigo-700', bar: 'bg-indigo-500' },
};

function transactionConfig(transaction: Transaction): {
    label: string;
    icon: typeof TrendingUp;
    color: string;
    sign: string;
    background: string;
} {
    if (transaction.event_code === 'expiry') {
        return {
            label: 'Expired',
            icon: TrendingDown,
            color: 'text-amber-600',
            sign: '-',
            background: 'bg-amber-50',
        };
    }

    if (transaction.points < 0 || transaction.type === 'redeemed') {
        return {
            label: 'Redeemed',
            icon: TrendingDown,
            color: 'text-red-500',
            sign: '-',
            background: 'bg-red-50',
        };
    }

    if (transaction.type === 'bonus' || transaction.type === 'referral_bonus') {
        return {
            label: transaction.type === 'referral_bonus' ? 'Referral Bonus' : 'Bonus',
            icon: Gift,
            color: 'text-violet-600',
            sign: '+',
            background: 'bg-violet-50',
        };
    }

    if (transaction.type === 'referral') {
        return {
            label: 'Referral',
            icon: Users,
            color: 'text-blue-600',
            sign: '+',
            background: 'bg-blue-50',
        };
    }

    return {
        label: 'Earned',
        icon: TrendingUp,
        color: 'text-emerald-600',
        sign: '+',
        background: 'bg-emerald-50',
    };
}

function BalanceCard({ balance, referralCode }: { balance: number; referralCode: string }) {
    const isPlatinum = balance >= 5000;

    return (
        <div className={cn('relative overflow-hidden rounded-2xl p-5 text-white shadow-lg', isPlatinum ? 'bg-gradient-to-br from-indigo-600 to-violet-700' : 'bg-gradient-to-br from-orange-500 to-amber-500')}>
            <div className="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10" />
            <div className="absolute -bottom-8 -right-2 h-20 w-20 rounded-full bg-white/10" />
            <p className="text-xs font-semibold uppercase tracking-widest text-white/70">{isPlatinum ? 'Platinum Member' : 'GasPoints Balance'}</p>
            <div className="mt-2 flex items-baseline gap-2">
                <span className="text-4xl font-black tabular-nums">{balance.toLocaleString()}</span>
                <span className="text-sm font-medium text-white/80">pts</span>
            </div>
            <p className="mt-1 text-xs text-white/70">
                Your code: <span className="font-mono font-bold">{referralCode}</span>
            </p>
        </div>
    );
}

function TierCard({ tier, balance }: { tier: Tier; balance: number }) {
    const colors = TIER_COLORS[tier.label] ?? TIER_COLORS.Bronze;
    const achieved = balance >= tier.threshold;
    const previousThreshold = { Bronze: 0, Silver: 500, Gold: 1000, Platinum: 2000 }[tier.label] ?? 0;
    const range = tier.threshold - previousThreshold;
    const filled = achieved ? range : Math.min(range, Math.max(0, balance - previousThreshold));
    const progress = range > 0 ? Math.max(0, Math.min(100, Math.round((filled / range) * 100))) : 0;

    return (
        <div className={cn('rounded-xl border p-4', colors.bg, colors.border, achieved ? 'opacity-60' : '')}>
            <div className="flex items-center justify-between">
                <div>
                    <p className={cn('text-sm font-bold', colors.text)}>{tier.label}</p>
                    <p className="text-xs text-slate-500">{tier.description}</p>
                </div>
                <div className="text-right">
                    {achieved ? (
                        <span className={cn('rounded-full border px-2 py-0.5 text-[10px] font-bold', colors.bg, colors.border, colors.text)}>Unlocked</span>
                    ) : (
                        <p className="text-xs tabular-nums text-slate-500">{(tier.threshold - balance).toLocaleString()} pts to go</p>
                    )}
                </div>
            </div>
            <div className="mt-3 h-1.5 w-full rounded-full bg-slate-200">
                <div className={cn('h-1.5 rounded-full transition-all', colors.bar)} style={{ width: `${achieved ? 100 : progress}%` }} />
            </div>
        </div>
    );
}

function RuleCard({ rules, earnRates }: { rules: Rules; earnRates: EarnRates }) {
    const rows = [
        `Orders from KES ${rules.min_order_amount.toLocaleString()} earn GasPoints${earnRates.large_cylinder > 0 ? `, with ${earnRates.large_cylinder} extra points on large cylinders.` : '.'}`,
        rules.expiry_days > 0 ? `Earned points expire after ${rules.expiry_days} days if unused.` : 'Earned points do not currently expire.',
        rules.referral_apply_window_days > 0
            ? `Referral codes must be applied within ${rules.referral_apply_window_days} days of sign-up and before the first order.`
            : 'Referral codes can be applied any time before the first order.',
        rules.referral_reward_window_days > 0
            ? `Referral rewards are earned on qualifying orders placed within ${rules.referral_reward_window_days} days after applying a referral code.`
            : 'Referral rewards are not limited by a post-application time window.',
        rules.referral_min_order_amount > 0
            ? `Referral rewards require a qualifying order of at least KES ${rules.referral_min_order_amount.toLocaleString()}.`
            : 'Referral rewards do not have a minimum order amount requirement.',
        rules.max_balance > 0
            ? `Balances are capped at ${rules.max_balance.toLocaleString()} points.`
            : 'There is currently no GasPoints balance cap.',
    ];

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-bold text-slate-900">Program Rules</h2>
            <div className="mt-3 space-y-2 text-xs text-slate-600">
                {rows.map((row) => (
                    <p key={row}>{row}</p>
                ))}
            </div>
        </div>
    );
}

function TransactionRow({ transaction }: { transaction: Transaction }) {
    const config = transactionConfig(transaction);
    const Icon = config.icon;
    const meta = transaction.event_code === 'expiry'
        ? (transaction.expired_at ? `Expired on ${transaction.expired_at}` : null)
        : (transaction.expires_at && transaction.remaining_points && transaction.remaining_points > 0
            ? `Expires on ${transaction.expires_at}`
            : null);

    return (
        <div className="flex items-center gap-3 py-3">
            <div className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full', config.background)}>
                <Icon className={cn('h-4 w-4', config.color)} />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-slate-800">{transaction.description}</p>
                <p className="text-[11px] text-slate-400">{transaction.created_at}</p>
                {meta && <p className="text-[11px] text-slate-400">{meta}</p>}
            </div>
            <div className="shrink-0 text-right">
                <p className={cn('text-sm font-bold tabular-nums', config.color)}>
                    {config.sign}{Math.abs(transaction.points).toLocaleString()} pts
                </p>
                <p className="text-[11px] tabular-nums text-slate-400">{transaction.balance_after.toLocaleString()} bal</p>
            </div>
        </div>
    );
}

export default function GasPointsIndex({
    enabled,
    balance,
    transactions,
    tiers,
    nextTier,
    referralCode,
    referralCount,
    earnRates,
    rules,
}: Props) {
    const [copied, setCopied] = useState(false);

    function copyCode(): void {
        navigator.clipboard.writeText(referralCode).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    function shareWhatsApp(): void {
        const message = encodeURIComponent(
            `Order gas easily with EldoGas. Use my referral code ${referralCode} when you sign up and we both earn bonus GasPoints.`,
        );
        window.open(`https://wa.me/?text=${message}`, '_blank');
    }

    return (
        <CustomerLayout title="GasPoints" showBack backHref="/home">
            <div className="mx-auto max-w-sm px-4 py-5 md:max-w-4xl">
                <div className="space-y-5 md:grid md:grid-cols-[1fr_420px] md:gap-6 md:space-y-0">
                    <div className="space-y-5">
                        {!enabled && (
                            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                                GasPoints are currently disabled. Existing balances remain visible, but new awards and redemptions are paused.
                            </div>
                        )}

                        <BalanceCard balance={balance} referralCode={referralCode} />

                        {nextTier && (
                            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div className="mb-2 flex items-center justify-between">
                                    <p className="text-xs font-semibold text-slate-700">Progress to {nextTier.label}</p>
                                    <p className="text-xs tabular-nums text-slate-500">
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

                        <RuleCard rules={rules} earnRates={earnRates} />

                        <div>
                            <h2 className="mb-3 text-sm font-bold text-slate-900">Redemption Tiers</h2>
                            <div className="space-y-2">
                                {tiers.map((tier) => (
                                    <TierCard key={tier.label} tier={tier} balance={balance} />
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border border-orange-200 bg-orange-50 p-4">
                            <div className="mb-3 flex items-center gap-2">
                                <Users className="h-4 w-4 text-orange-500" />
                                <h2 className="text-sm font-bold text-slate-900">Refer Friends and Earn</h2>
                            </div>
                            <p className="mb-3 text-xs text-slate-600">
                                Earn <span className="font-semibold text-orange-600">{earnRates.referral} points</span> when a friend places their first qualifying order using your code.
                                They can unlock a further <span className="font-semibold text-orange-600">{earnRates.referral_third_order} points</span> on their third qualifying order.
                            </p>
                            <div className="mb-3 flex items-center gap-2">
                                <div className="flex-1 rounded-lg border border-orange-200 bg-white px-3 py-2">
                                    <p className="font-mono text-base font-bold tracking-widest text-slate-800">{referralCode}</p>
                                </div>
                                <button
                                    onClick={copyCode}
                                    className={cn(
                                        'flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition-colors',
                                        copied ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-orange-300 bg-white text-orange-700 hover:bg-orange-50',
                                    )}
                                >
                                    {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                                    {copied ? 'Copied' : 'Copy'}
                                </button>
                            </div>
                            <button onClick={shareWhatsApp} className="w-full rounded-lg bg-[#25D366] py-2 text-xs font-semibold text-white transition-colors hover:bg-[#1ebe5d]">
                                Share via WhatsApp
                            </button>
                            {referralCount > 0 && (
                                <p className="mt-2 text-center text-[11px] text-slate-500">
                                    {referralCount} friend{referralCount !== 1 ? 's' : ''} referred so far
                                </p>
                            )}
                        </div>
                    </div>

                    <div>
                        <h2 className="mb-2 text-sm font-bold text-slate-900">Transaction History</h2>
                        <div className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white px-4 shadow-sm">
                            {transactions.data.length === 0 ? (
                                <div className="flex flex-col items-center py-10 text-center">
                                    <Star className="h-8 w-8 text-slate-200" />
                                    <p className="mt-2 text-sm text-slate-400">No transactions yet</p>
                                    <p className="text-xs text-slate-300">Place an order to start earning points.</p>
                                </div>
                            ) : (
                                transactions.data.map((transaction) => <TransactionRow key={transaction.id} transaction={transaction} />)
                            )}
                        </div>

                        {transactions.last_page > 1 && (
                            <div className="flex justify-center gap-2 pt-3">
                                {transactions.prev_page_url && (
                                    <Link href={transactions.prev_page_url} className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Previous
                                    </Link>
                                )}
                                <span className="flex items-center px-3 text-xs text-slate-500">
                                    {transactions.current_page} / {transactions.last_page}
                                </span>
                                {transactions.next_page_url && (
                                    <Link href={transactions.next_page_url} className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Next
                                    </Link>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </CustomerLayout>
    );
}