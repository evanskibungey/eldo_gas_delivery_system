import AdminLayout from '@/Layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { AlertTriangle, BellOff, MessageSquare, Plus, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface Campaign {
    id:                   number;
    title:                string;
    message:              string;
    kind:                 'promo' | 'info';
    audience:             string;
    recipient_count:      number;
    skipped_opted_out:    number;
    segments_per_message: number;
    total_segments:       number;
    status:               'draft' | 'queued' | 'sent' | 'failed';
    sent_by:              string | null;
    sent_ago:             string;
}

interface Props {
    campaigns: {
        data:          Campaign[];
        current_page:  number;
        last_page:     number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total:         number;
    };
    pending_jobs:    number;
    opted_out_total: number;
}

export default function SmsIndex({ campaigns, pending_jobs, opted_out_total }: Props) {
    return (
        <AdminLayout title="SMS" subtitle="Promotional and informational messages to customers">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Button asChild className="gap-2 bg-orange-500 hover:bg-orange-600">
                    <Link href="/admin/sms/create">
                        <Plus className="h-4 w-4" /> New message
                    </Link>
                </Button>

                <span className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-600">
                    <BellOff className="h-3.5 w-3.5 text-slate-400" />
                    {opted_out_total} opted out of promotions
                </span>

                {pending_jobs > 0 && (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-700">
                        {pending_jobs} message{pending_jobs === 1 ? '' : 's'} still queued
                    </span>
                )}
            </div>

            {/* A campaign stuck at "queued" with work outstanding nearly always
                means no worker is covering the `bulk` queue. Saying so beats
                leaving someone to wonder why nothing arrived. */}
            {pending_jobs > 20 && (
                <div className="mb-4 flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3">
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                    <p className="text-sm text-amber-800">
                        <span className="font-semibold">{pending_jobs} messages are waiting to send.</span>{' '}
                        If this number is not falling, the queue worker is probably not covering the{' '}
                        <code className="rounded bg-amber-100 px-1 font-mono text-xs">bulk</code> queue.
                        Its command needs <code className="rounded bg-amber-100 px-1 font-mono text-xs">--queue=high,default,bulk</code>.
                    </p>
                </div>
            )}

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table className="w-full min-w-[820px] text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/80 text-left">
                            <th className="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Campaign</th>
                            <th className="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Audience</th>
                            <th className="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Recipients</th>
                            <th className="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">SMS billed</th>
                            <th className="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Sent</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {campaigns.data.length === 0 && (
                            <tr>
                                <td colSpan={5} className="py-16 text-center">
                                    <div className="flex flex-col items-center gap-2">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
                                            <MessageSquare className="h-6 w-6 text-slate-400" />
                                        </div>
                                        <p className="text-sm font-medium text-slate-500">No messages sent yet</p>
                                    </div>
                                </td>
                            </tr>
                        )}

                        {campaigns.data.map(campaign => (
                            <tr key={campaign.id} className="group transition-colors hover:bg-slate-50/50">
                                <td className="px-5 py-4">
                                    <Link href={`/admin/sms/${campaign.id}`} className="block">
                                        <div className="flex items-center gap-2">
                                            <p className="font-semibold text-slate-900">{campaign.title}</p>
                                            <span className={cn(
                                                'rounded px-1.5 py-0.5 text-2xs font-bold uppercase',
                                                campaign.kind === 'promo'
                                                    ? 'bg-orange-50 text-orange-600'
                                                    : 'bg-blue-50 text-blue-600',
                                            )}>
                                                {campaign.kind}
                                            </span>
                                        </div>
                                        <p className="mt-0.5 line-clamp-1 text-xs text-slate-500">{campaign.message}</p>
                                    </Link>
                                </td>
                                <td className="px-5 py-4 text-xs text-slate-600">{campaign.audience}</td>
                                <td className="px-5 py-4 text-right">
                                    <span className="inline-flex items-center gap-1 text-sm font-semibold tabular-nums text-slate-900">
                                        <Users className="h-3 w-3 text-slate-400" />
                                        {campaign.recipient_count}
                                    </span>
                                    {campaign.skipped_opted_out > 0 && (
                                        <p className="text-2xs text-slate-500">
                                            {campaign.skipped_opted_out} skipped
                                        </p>
                                    )}
                                </td>
                                <td className="px-5 py-4 text-right">
                                    <p className="text-sm font-bold tabular-nums text-slate-900">
                                        {campaign.total_segments}
                                    </p>
                                    <p className="text-2xs text-slate-500">
                                        {campaign.segments_per_message} each
                                    </p>
                                </td>
                                <td className="px-5 py-4">
                                    <p className="text-xs text-slate-600">{campaign.sent_ago}</p>
                                    {campaign.sent_by && (
                                        <p className="text-2xs text-slate-500">by {campaign.sent_by}</p>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                {campaigns.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                        <p className="text-xs text-slate-500">
                            Page {campaigns.current_page} of {campaigns.last_page} · {campaigns.total} campaigns
                        </p>
                        <div className="flex gap-2">
                            {campaigns.prev_page_url && (
                                <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                                    <Link href={campaigns.prev_page_url}>Prev</Link>
                                </Button>
                            )}
                            {campaigns.next_page_url && (
                                <Button asChild variant="outline" size="sm" className="h-8 text-xs">
                                    <Link href={campaigns.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
