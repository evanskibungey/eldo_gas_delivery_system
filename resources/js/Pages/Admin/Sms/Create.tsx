import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, ArrowLeft, MessageSquare, Search, Send, Users, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { measureSms } from '@/lib/smsSegments';

interface SelectedCustomer {
    id:        number;
    name:      string;
    phone:     string;
    opted_out: boolean;
}

interface Props {
    audiences:         Record<string, string>;
    selected:          number[];
    selectedCustomers: SelectedCustomer[];
    optOutLine:        string;
}

/** Server's answer, which is what the send is actually billed on. */
interface Preview {
    body:              string;
    encoding:          string;
    units:             number;
    segments:          number;
    recipients:        number;
    skipped_opted_out: number;
    total_segments:    number;
    offenders:         string[];
}

export default function SmsCreate({ audiences, selected, selectedCustomers, optOutLine }: Props) {
    const [title, setTitle]       = useState('');
    const [message, setMessage]   = useState('');
    const [kind, setKind]         = useState<'promo' | 'info'>('promo');
    const [preview, setPreview]   = useState<Preview | null>(null);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [sending, setSending]   = useState(false);

    // Whole records, not just ids: the panel has to show who was picked without
    // another round trip, including anyone added here rather than arriving from
    // the customer list.
    const [picked, setPicked] = useState<SelectedCustomer[]>(selectedCustomers);
    const [audience, setAudience] = useState(selected.length > 0 ? 'selected' : 'all_active');

    const [query, setQuery]     = useState('');
    const [results, setResults] = useState<SelectedCustomer[]>([]);
    const [searching, setSearching] = useState(false);

    const pickedIds = useMemo(() => picked.map(c => c.id), [picked]);

    function togglePicked(customer: SelectedCustomer) {
        setPicked(current => current.some(c => c.id === customer.id)
            ? current.filter(c => c.id !== customer.id)
            : [...current, customer]);

        // Picking somebody plainly means "send to these people".
        setAudience('selected');
    }

    // Search the directory from here, so choosing recipients does not mean
    // leaving a half-written message behind on another page.
    useEffect(() => {
        const controller = new AbortController();

        const timer = window.setTimeout(async () => {
            setSearching(true);
            try {
                const response = await fetch(
                    `/admin/sms/customers?q=${encodeURIComponent(query)}`,
                    { signal: controller.signal, headers: { Accept: 'application/json' } },
                );
                if (response.ok) setResults(await response.json());
            } catch {
                // Aborted or offline; the last results stay on screen.
            } finally {
                setSearching(false);
            }
        }, 250);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [query]);

    // Promos carry the opt-out line, which is part of what gets billed.
    const body = useMemo(
        () => (kind === 'promo' && !message.toUpperCase().includes('STOP')
            ? message.trim() + optOutLine
            : message.trim()),
        [message, kind, optOutLine],
    );

    // Instant, so the counter moves with the keystroke.
    const local = useMemo(() => measureSms(body), [body]);

    // The server owns the recipient count and is the authority on cost, so it
    // is asked too — debounced, and by fetch rather than an Inertia visit,
    // which cannot return JSON.
    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch('/admin/sms/preview', {
                    method: 'POST',
                    signal: controller.signal,
                    // Sends the session cookie. Without it the endpoint sees a
                    // guest and redirects to the login page.
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ message, kind, audience, customer_ids: pickedIds }),
                });

                if (response.ok) {
                    setPreview(await response.json());
                    setPreviewError(null);
                } else {
                    // Say what happened. Left silent, this hung the panel on
                    // "Checking recipients…" with no way to tell whether the
                    // count was slow or the request had died.
                    setPreviewError(
                        response.status === 419
                            ? 'Session expired — reload the page.'
                            : `Could not check recipients (HTTP ${response.status}).`,
                    );
                }
            } catch (error) {
                // An abort is this effect superseding itself, not a failure.
                if ((error as Error)?.name !== 'AbortError') {
                    setPreviewError('Could not reach the server to check recipients.');
                }
            }
        }, 400);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
        // pickedIds.join keeps this from re-firing on every render just because
        // the array identity changed.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [message, kind, audience, pickedIds.join(',')]);

    // Hand-picked recipients are known here without asking the server, so a
    // failed preview degrades to a local count rather than blocking the send.
    // The server re-resolves the audience at send time and refuses an empty
    // one, so nothing is trusted that should not be.
    const localRecipients = audience === 'selected' ? picked.length : 0;
    const recipients = preview?.recipients ?? localRecipients;
    const totalSegments = local.segments * recipients;

    // Why the button is off, in the order somebody fills the form in. A greyed
    // button with no explanation is a guessing game, and the campaign name is
    // easy to miss because the message is the part that feels like the work.
    const blocker =
        title.trim() === '' ? 'Give the campaign a name first'
        : message.trim() === '' ? 'Write the message first'
        // Only wait on the server when it is the only thing that knows the
        // count — an audience filter. A stuck preview must not strand a
        // hand-picked send that is already fully specified.
        : preview === null && previewError === null && audience !== 'selected' ? 'Checking recipients…'
        : recipients === 0 ? 'This audience has nobody to text'
        : null;

    const canSend = blocker === null && !sending;

    function send() {
        if (!canSend) return;

        const confirmed = window.confirm(
            `Send to ${recipients} customer${recipients === 1 ? '' : 's'}?\n\n` +
            `${local.segments} SMS segment${local.segments === 1 ? '' : 's'} each — ` +
            `${totalSegments} billed in total.\n\nThis cannot be undone.`,
        );

        if (!confirmed) return;

        setSending(true);
        router.post('/admin/sms', {
            title,
            message,
            kind,
            audience,
            customer_ids: pickedIds,
            confirmed_segments: local.segments,
        }, {
            onFinish: () => setSending(false),
        });
    }

    return (
        <AdminLayout title="New SMS" subtitle="Compose a message and send it to customers">
            <Link href="/admin/sms" className="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
                <ArrowLeft className="h-4 w-4" /> Back to campaigns
            </Link>

            <div className="grid gap-4 lg:grid-cols-3">
                {/* Composer */}
                <div className="space-y-4 lg:col-span-2">
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Campaign name <span className="text-orange-500">*</span>
                        </label>
                        <Input
                            value={title}
                            onChange={e => setTitle(e.target.value)}
                            placeholder="e.g. September refill offer"
                            className={cn('mt-1.5', title.trim() === '' && 'border-orange-300')}
                            maxLength={120}
                        />
                        <p className="mt-1 text-2xs text-slate-500">
                            For your records only — customers never see this.
                        </p>

                        <div className="mt-4">
                            <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Message type
                            </label>
                            <div className="mt-1.5 flex gap-2">
                                <TypeButton
                                    active={kind === 'promo'}
                                    onClick={() => setKind('promo')}
                                    label="Promotional"
                                    hint="Offers and marketing. Skips opted-out customers."
                                />
                                <TypeButton
                                    active={kind === 'info'}
                                    onClick={() => setKind('info')}
                                    label="Informational"
                                    hint="Price changes, closures. Goes to everyone selected."
                                />
                            </div>
                        </div>

                        <div className="mt-4">
                            <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Message
                            </label>
                            <textarea
                                value={message}
                                onChange={e => setMessage(e.target.value)}
                                rows={5}
                                placeholder="Type the message your customers will receive…"
                                className="mt-1.5 w-full resize-none rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-400/20"
                            />

                            {kind === 'promo' && (
                                <p className="mt-1.5 text-2xs text-slate-500">
                                    <span className="font-semibold">{optOutLine.trim()}</span> is added
                                    automatically and is included in the count below.
                                </p>
                            )}
                        </div>

                        {/* What it will actually cost */}
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <Meter label="Characters" value={`${local.units} / ${local.limit}`} />
                            <Meter
                                label="Segments each"
                                value={String(local.segments)}
                                tone={local.segments > 1 ? 'warn' : 'ok'}
                            />
                            <Meter
                                label="Encoding"
                                value={local.encoding}
                                tone={local.encoding === 'UCS-2' ? 'warn' : 'ok'}
                            />
                        </div>

                        {local.encoding === 'UCS-2' && (
                            <div className="mt-2 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                                <p className="text-xs text-amber-800">
                                    <span className="font-semibold">
                                        {local.offenders.map(c => `"${c}"`).join(', ')}
                                    </span>{' '}
                                    {local.offenders.length === 1 ? 'is not' : 'are not'} a standard SMS
                                    character. One of them cuts the limit from 160 to 70 and more than
                                    doubles the cost. Emoji, curly quotes and long dashes are the usual
                                    culprits — replace them with plain text.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Preview of the message as received */}
                    {body.trim() !== '' && (
                        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                As the customer receives it
                            </p>
                            <div className="max-w-sm rounded-2xl rounded-tl-sm bg-slate-100 px-3.5 py-2.5">
                                <p className="whitespace-pre-wrap break-words text-sm text-slate-800">{body}</p>
                            </div>
                        </div>
                    )}
                </div>

                {/* Audience + send */}
                <div className="space-y-4">
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Send to
                        </label>

                        {picked.length > 0 && (
                            <button
                                onClick={() => setAudience('selected')}
                                className={cn(
                                    'mt-1.5 w-full rounded-lg border px-3 py-2 text-left text-sm transition-colors',
                                    audience === 'selected'
                                        ? 'border-orange-400 bg-orange-50 font-semibold text-orange-700'
                                        : 'border-slate-200 hover:bg-slate-50',
                                )}
                            >
                                {picked.length} hand-picked customer{picked.length === 1 ? '' : 's'}
                            </button>
                        )}

                        <select
                            value={audience === 'selected' ? '' : audience}
                            onChange={e => e.target.value && setAudience(e.target.value)}
                            className="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-orange-400 focus:outline-none"
                        >
                            {picked.length > 0 && <option value="">Or choose an audience…</option>}
                            {Object.entries(audiences).map(([key, label]) => (
                                <option key={key} value={key}>{label}</option>
                            ))}
                        </select>

                        <div className="mt-4 space-y-2 border-t border-slate-100 pt-3">
                            <Row
                                icon={<Users className="h-4 w-4 text-slate-400" />}
                                label="Recipients"
                                value={preview || audience === 'selected' ? String(recipients) : '…'}
                                strong
                            />
                            {(preview?.skipped_opted_out ?? 0) > 0 && (
                                <Row
                                    icon={<X className="h-4 w-4 text-slate-400" />}
                                    label="Opted out (skipped)"
                                    value={String(preview?.skipped_opted_out)}
                                />
                            )}
                            <Row
                                icon={<MessageSquare className="h-4 w-4 text-slate-400" />}
                                label="Total SMS billed"
                                value={preview || audience === 'selected' ? String(totalSegments) : '…'}
                                strong
                            />
                        </div>

                        <Button
                            onClick={send}
                            disabled={!canSend}
                            title={blocker ?? undefined}
                            className="mt-4 w-full gap-2 bg-orange-500 hover:bg-orange-600"
                        >
                            <Send className="h-4 w-4" />
                            {sending ? 'Queueing…' : `Send to ${recipients}`}
                        </Button>

                        {previewError && (
                            <p className="mt-2 flex items-start gap-1.5 text-2xs font-medium text-amber-700">
                                <AlertTriangle className="mt-0.5 h-3 w-3 shrink-0" />
                                {previewError}
                                {audience === 'selected' && ' Using your picked list instead.'}
                            </p>
                        )}

                        {blocker ? (
                            <p className="mt-2 flex items-start gap-1.5 text-2xs font-medium text-amber-700">
                                <AlertTriangle className="mt-0.5 h-3 w-3 shrink-0" />
                                {blocker}
                            </p>
                        ) : !previewError && (
                            <p className="mt-2 text-2xs text-slate-500">
                                Messages are queued and sent in the background. A send cannot be
                                recalled once it starts.
                            </p>
                        )}
                    </div>

                    {/* Pick recipients without leaving a half-written message
                        behind on the customer list. */}
                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="mb-2 flex items-center justify-between gap-2">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Pick customers
                            </p>
                            {picked.length > 0 && (
                                <button
                                    onClick={() => setPicked([])}
                                    className="text-2xs font-medium text-slate-500 hover:text-slate-700"
                                >
                                    Clear all
                                </button>
                            )}
                        </div>

                        <div className="relative">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                            <Input
                                value={query}
                                onChange={e => setQuery(e.target.value)}
                                placeholder="Search name or phone…"
                                className="h-9 pl-8 text-sm"
                            />
                        </div>

                        {picked.length > 0 && (
                            <ul className="mt-2.5 space-y-1 border-b border-slate-100 pb-2.5">
                                {picked.map(customer => (
                                    <li key={customer.id} className="flex items-center gap-2 text-xs">
                                        <span className="truncate font-medium text-slate-800">{customer.name}</span>
                                        {customer.opted_out && kind === 'promo' && (
                                            <span
                                                title="Opted out of promotions — will be skipped"
                                                className="shrink-0 rounded bg-amber-50 px-1.5 py-0.5 text-2xs font-semibold text-amber-700"
                                            >
                                                opted out
                                            </span>
                                        )}
                                        <button
                                            onClick={() => togglePicked(customer)}
                                            aria-label={`Remove ${customer.name}`}
                                            className="ml-auto shrink-0 p-0.5 text-slate-400 hover:text-slate-700"
                                        >
                                            <X className="h-3 w-3" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <ul className="mt-2 max-h-56 space-y-0.5 overflow-y-auto">
                            {searching && results.length === 0 && (
                                <li className="px-1 py-2 text-xs text-slate-400">Searching…</li>
                            )}

                            {!searching && results.length === 0 && (
                                <li className="px-1 py-2 text-xs text-slate-400">No customers found.</li>
                            )}

                            {results.map(customer => {
                                const isPicked = pickedIds.includes(customer.id);

                                return (
                                    <li key={customer.id}>
                                        <button
                                            onClick={() => togglePicked(customer)}
                                            className={cn(
                                                'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors',
                                                isPicked ? 'bg-orange-50' : 'hover:bg-slate-50',
                                            )}
                                        >
                                            <input
                                                type="checkbox"
                                                readOnly
                                                checked={isPicked}
                                                tabIndex={-1}
                                                className="h-3.5 w-3.5 shrink-0 rounded border-slate-300 text-orange-500"
                                            />
                                            <span className="truncate text-slate-800">{customer.name}</span>
                                            {customer.opted_out && kind === 'promo' && (
                                                <span className="shrink-0 rounded bg-amber-50 px-1 py-0.5 text-2xs font-semibold text-amber-700">
                                                    opted out
                                                </span>
                                            )}
                                            <span className="ml-auto shrink-0 font-mono text-2xs text-slate-400">
                                                {customer.phone}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>

                        <p className="mt-2 text-2xs text-slate-500">
                            Showing up to 25. Search to narrow, or use an audience above to reach
                            everyone at once.
                        </p>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

function TypeButton({ active, onClick, label, hint }: {
    active: boolean; onClick: () => void; label: string; hint: string;
}) {
    return (
        <button
            onClick={onClick}
            title={hint}
            className={cn(
                'flex-1 rounded-lg border px-3 py-2 text-sm font-medium transition-colors',
                active
                    ? 'border-orange-400 bg-orange-50 text-orange-700'
                    : 'border-slate-200 text-slate-600 hover:bg-slate-50',
            )}
        >
            {label}
        </button>
    );
}

function Meter({ label, value, tone = 'ok' }: { label: string; value: string; tone?: 'ok' | 'warn' }) {
    return (
        <span className={cn(
            'inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-2xs font-semibold',
            tone === 'warn'
                ? 'border-amber-200 bg-amber-50 text-amber-700'
                : 'border-slate-200 bg-slate-50 text-slate-600',
        )}>
            <span className="font-normal text-slate-500">{label}</span>
            {value}
        </span>
    );
}

function Row({ icon, label, value, strong }: {
    icon: React.ReactNode; label: string; value: string; strong?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-2 text-sm">
            <span className="flex items-center gap-2 text-slate-500">{icon}{label}</span>
            <span className={cn('tabular-nums', strong ? 'font-bold text-slate-900' : 'text-slate-600')}>
                {value}
            </span>
        </div>
    );
}
