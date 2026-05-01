import { useState } from 'react';
import { router } from '@inertiajs/react';
import { ShieldAlert, X, Phone } from 'lucide-react';

export default function SosButton() {
    const [open, setOpen] = useState(false);
    const [sending, setSending] = useState(false);

    function handleConfirm() {
        setSending(true);
        router.get('/sos', {}, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                setSending(false);
                setOpen(false);
                // Trigger the emergency call client-side
                window.location.href = 'tel:999';
            },
        });
    }

    return (
        <>
            <button
                onClick={() => setOpen(true)}
                className="flex items-center justify-center rounded-full w-9 h-9 text-red-500 hover:bg-red-50 hover:text-red-600 transition-colors"
                title="Gas Emergency SOS"
                aria-label="SOS — Gas Emergency"
            >
                <ShieldAlert className="h-5 w-5" />
            </button>

            {open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                    <div className="w-full max-w-sm rounded-2xl bg-white shadow-2xl overflow-hidden">

                        {/* Red header */}
                        <div className="bg-red-600 px-5 py-4 flex items-center gap-3">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20">
                                <ShieldAlert className="h-6 w-6 text-white" />
                            </div>
                            <div>
                                <p className="font-bold text-white text-base">Gas Emergency?</p>
                                <p className="text-red-100 text-xs mt-0.5">EldoGas SOS Alert System</p>
                            </div>
                            <button onClick={() => setOpen(false)} className="ml-auto text-white/70 hover:text-white">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="px-5 py-4 space-y-3">
                            {/* Safety tips */}
                            <div className="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 space-y-1">
                                <p className="font-semibold">While you wait for help:</p>
                                <ul className="list-disc list-inside text-xs space-y-0.5 text-amber-700">
                                    <li>Do not switch on/off any lights or electrical switches</li>
                                    <li>Open all windows and doors immediately</li>
                                    <li>Leave the building — move away from the area</li>
                                    <li>Do not use your phone inside the building</li>
                                </ul>
                            </div>

                            <p className="text-sm text-slate-600">
                                Tapping <strong>Send SOS</strong> will alert the EldoGas team and call emergency services.
                            </p>
                        </div>

                        <div className="px-5 pb-5 flex gap-3">
                            <button
                                onClick={() => setOpen(false)}
                                className="flex-1 rounded-xl border border-slate-200 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleConfirm}
                                disabled={sending}
                                className="flex-1 flex items-center justify-center gap-2 rounded-xl bg-red-600 py-3 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60"
                            >
                                <Phone className="h-4 w-4" />
                                {sending ? 'Sending…' : 'Send SOS + Call 999'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
