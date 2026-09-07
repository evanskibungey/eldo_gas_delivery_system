import AdminLayout from '@/Layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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
    status:               string;
    sent_by:              string | null;
    sent_at:              string;
    encoding:             string;
}

interface Recipient {
    id:    number;
    name:  string;
    phone: string;
}

interface Props {
    campaign:   Campaign;
    recipients: { data: Recipient[]; current_page: number; last_page: number; total: number };
}

export default function SmsShow({ campaign, recipients }: Props) {
    return (
        <AdminLayout title={campaign.title} subtitle={`Sent ${campaign.sent_at}`}>
            <Link href="/admin/sms" className="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
                <ArrowLeft className="h-4 w-4" /> Back to campaigns
            </Link>

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="mb-3 flex items-center gap-2">
                            <p className="text-sm font-semibold text-slate-800">Message sent</p>
                            <span className={cn(
                                'rounded px-1.5 py-0.5 text-2xs font-bold uppercase',
                                campaign.kind === 'promo'
                                    ? 'bg-orange-50 text-orange-600'
                                    : 'bg-blue-50 text-blue-600',
                            )}>
                                {campaign.kind}
                            </span>
                        </div>
                        <div className="max-w-sm rounded-2xl rounded-tl-sm bg-slate-100 px-3.5 py-2.5">
                            <p className="whitespace-pre-wrap break-words text-sm text-slate-800">
                                {campaign.message}
                            </p>
                        </div>
                    </div>

                    {/* Who it went to. The campaign totals alone cannot answer
                        "did this particular customer receive it". */}
                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-100 px-5 py-3">
                            <p className="text-sm font-semibold text-slate-800">
                                Recipients <span className="font-normal text-slate-500">({recipients.total})</span>
                            </p>
                        </div>
                        <ul className="divide-y divide-slate-100">
                            {recipients.data.map(recipient => (
                                <li key={recipient.id} className="flex items-center justify-between px-5 py-2.5">
                                    <Link
                                        href={`/admin/customers/${recipient.id}`}
                                        className="text-sm text-slate-700 hover:text-orange-600"
                                    >
                                        {recipient.name}
                                    </Link>
                                    <span className="font-mono text-xs text-slate-500">{recipient.phone}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Summary</p>
                    <dl className="space-y-2.5 text-sm">
                        <Stat label="Audience" value={campaign.audience} />
                        <Stat label="Recipients" value={String(campaign.recipient_count)} />
                        {campaign.skipped_opted_out > 0 && (
                            <Stat label="Skipped (opted out)" value={String(campaign.skipped_opted_out)} />
                        )}
                        <Stat label="Segments each" value={String(campaign.segments_per_message)} />
                        <Stat label="Total SMS billed" value={String(campaign.total_segments)} strong />
                        <Stat label="Encoding" value={campaign.encoding} />
                        {campaign.sent_by && <Stat label="Sent by" value={campaign.sent_by} />}
                    </dl>
                </div>
            </div>
        </AdminLayout>
    );
}

function Stat({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="text-slate-500">{label}</dt>
            <dd className={cn('text-right', strong ? 'font-bold text-slate-900' : 'text-slate-700')}>
                {value}
            </dd>
        </div>
    );
}
