import AdminLayout from '@/Layouts/AdminLayout';
import { useState } from 'react';
import { Search, ShieldAlert, ClipboardCopy, CheckCheck, Phone } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface Result {
    otp: string | null;
    message: string;
}

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

function normalizePhone(raw: string): string {
    // Strip spaces and non-digit/plus characters, then normalise prefix
    const clean = raw.replace(/[^\d+]/g, '').trim();
    if (clean.startsWith('0')) return '+254' + clean.slice(1);
    return clean;
}

export default function OtpLookup() {
    const [phone,   setPhone]   = useState('');
    const [loading, setLoading] = useState(false);
    const [result,  setResult]  = useState<Result | null>(null);
    const [copied,  setCopied]  = useState(false);

    async function handleLookup(e: React.FormEvent) {
        e.preventDefault();
        const normalized = normalizePhone(phone);
        if (!normalized) return;

        setLoading(true);
        setResult(null);

        try {
            const res = await fetch('/admin/dev/otp/lookup', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ phone: normalized }),
            });

            if (!res.ok && res.status !== 200) {
                setResult({ otp: null, message: `Server error ${res.status} — check Forge logs.` });
                return;
            }

            const data: Result = await res.json();
            setResult(data);
        } catch (err) {
            setResult({ otp: null, message: 'Request failed — ensure you are logged into the admin panel.' });
        } finally {
            setLoading(false);
        }
    }

    function copyOtp() {
        if (!result?.otp) return;
        navigator.clipboard.writeText(result.otp).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    const preview = phone.trim() ? normalizePhone(phone) : null;

    return (
        <AdminLayout title="OTP Lookup">
            <div className="max-w-lg mx-auto py-10 px-4">

                <div className="mb-8 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                    <div>
                        <p className="font-semibold">Temporary tool — no SMS configured</p>
                        <p className="mt-0.5 text-amber-700">
                            This page is only visible while{' '}
                            <code className="rounded bg-amber-100 px-1 font-mono text-xs">AT_API_KEY</code>{' '}
                            is empty. It disappears automatically once you add Africa's Talking credentials.
                        </p>
                    </div>
                </div>

                <h1 className="text-xl font-bold text-slate-800">OTP Lookup</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Enter the customer's phone number to retrieve their active verification code.
                </p>

                <form onSubmit={handleLookup} className="mt-6 space-y-4">
                    <div>
                        <Label className="text-sm font-medium text-slate-700">Phone Number</Label>
                        <div className="mt-1.5 flex overflow-hidden rounded-lg border border-slate-200 bg-slate-50 focus-within:border-orange-400 focus-within:ring-2 focus-within:ring-orange-400/20 transition-all">
                            <span className="flex items-center gap-1.5 border-r border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-500 select-none">
                                <Phone className="h-4 w-4" />
                            </span>
                            <Input
                                type="tel"
                                placeholder="0712 345 678  or  +254712345678"
                                value={phone}
                                onChange={e => { setPhone(e.target.value); setResult(null); }}
                                className="flex-1 h-10 rounded-none border-0 bg-transparent px-3 text-sm shadow-none focus-visible:ring-0"
                                autoFocus
                            />
                        </div>
                        {preview && (
                            <p className="mt-1 text-xs text-slate-400">
                                Will search for:{' '}
                                <span className="font-mono text-slate-600">{preview}</span>
                            </p>
                        )}
                    </div>

                    <Button
                        type="submit"
                        disabled={loading || !phone.trim()}
                        className="w-full bg-orange-500 hover:bg-orange-600 gap-2"
                    >
                        <Search className="h-4 w-4" />
                        {loading ? 'Searching…' : 'Look up OTP'}
                    </Button>
                </form>

                {result && (
                    <div className={`mt-6 rounded-xl border p-5 ${
                        result.otp
                            ? 'border-green-200 bg-green-50'
                            : 'border-slate-200 bg-slate-50'
                    }`}>
                        {result.otp ? (
                            <>
                                <p className="text-xs font-medium uppercase tracking-wide text-green-600">Active OTP</p>
                                <div className="mt-2 flex items-center gap-3">
                                    <span className="text-5xl font-mono font-bold tracking-[0.25em] text-green-700">
                                        {result.otp}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={copyOtp}
                                        className="ml-auto flex items-center gap-1.5 rounded-lg border border-green-200 bg-white px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50 transition-colors"
                                    >
                                        {copied
                                            ? <><CheckCheck className="h-3.5 w-3.5" /> Copied</>
                                            : <><ClipboardCopy className="h-3.5 w-3.5" /> Copy</>
                                        }
                                    </button>
                                </div>
                                <p className="mt-2 text-xs text-green-600">{result.message}</p>
                                <p className="mt-3 text-xs text-slate-500">
                                    Share this 4-digit code with the customer. Valid for 10 minutes from when they requested it.
                                </p>
                            </>
                        ) : (
                            <>
                                <p className="text-sm font-medium text-slate-700">No active OTP found</p>
                                <p className="mt-1 text-xs text-slate-500">{result.message}</p>
                            </>
                        )}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
