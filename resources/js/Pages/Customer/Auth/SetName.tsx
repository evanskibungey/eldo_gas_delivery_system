import GuestLayout from '@/Layouts/GuestLayout';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { User, Loader2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';

export default function SetName() {
    const { errors } = usePage().props as any;
    const [name,    setName]    = useState('');
    const [loading, setLoading] = useState(false);

    function onSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (! name.trim()) return;
        setLoading(true);
        router.post('/onboarding/name', { name }, {
            onFinish: () => setLoading(false),
        });
    }

    return (
        <GuestLayout>
            <div className="mb-5 flex flex-col items-center gap-2 text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100">
                    <User className="h-6 w-6 text-orange-500" />
                </div>
                <h2 className="text-lg font-semibold text-slate-800">Welcome to EldoGas!</h2>
                <p className="text-sm text-muted-foreground">What should we call you?</p>
            </div>

            <form onSubmit={onSubmit} className="space-y-4">
                <div>
                    <Label className="text-sm font-medium text-slate-700">Your Name</Label>
                    <Input
                        value={name}
                        onChange={e => setName(e.target.value)}
                        placeholder="e.g. Jane Wanjiku"
                        className={cn(
                            'mt-1.5 h-10 border-slate-200 bg-slate-50 text-sm focus:border-orange-400 focus:ring-orange-400/20 focus:bg-white transition-all',
                            errors?.name && 'border-red-400 bg-red-50',
                        )}
                        autoFocus
                        maxLength={100}
                    />
                    {errors?.name && (
                        <p className="mt-1.5 text-xs text-red-500">{errors.name}</p>
                    )}
                </div>

                <Button
                    type="submit"
                    disabled={loading || name.trim().length < 2}
                    className="w-full bg-orange-500 hover:bg-orange-600 shadow-sm shadow-orange-500/20 gap-2"
                >
                    {loading ? (
                        <><Loader2 className="h-4 w-4 animate-spin" /> Savingâ€¦</>
                    ) : 'Continue'}
                </Button>
            </form>
        </GuestLayout>
    );
}
