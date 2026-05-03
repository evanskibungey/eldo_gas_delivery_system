import AdminLayout from '@/Layouts/AdminLayout';
import { Link } from '@inertiajs/react';
import { Pencil, Building2, Home, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface Price {
    gas_refill_price:   number;
    new_cylinder_price: number;
    new_gas_fill_price: number;
    delivery_fee:       number;
    updated_at:         string | null;
}

interface Size {
    id: number; name: string; is_commercial: boolean; price: Price | null;
}

const fmt = (n: number) => `KES ${n.toLocaleString()}`;

function PriceCell({ value }: { value: number | undefined }) {
    if (value == null) return <span className="text-slate-300">—</span>;
    return <span className="font-semibold text-slate-800">{fmt(value)}</span>;
}

export default function PricingIndex({ sizes }: { sizes: Size[] }) {
    return (
        <AdminLayout title="Pricing Management" subtitle="Set gas refill, cylinder, and delivery fees per size">

            <div className="mb-5 flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                <p className="text-sm text-amber-700">
                    Price changes take effect immediately and are logged to the audit trail. Double-check before saving.
                </p>
            </div>

            <div className="relative rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div className="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-orange-400 via-orange-500 to-amber-500" />
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/80">
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Size</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Swap / Refill</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">New Cylinder</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Gas Fill (New)</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Delivery Fee</th>
                            <th className="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Last Updated</th>
                            <th className="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {sizes.map(s => (
                            <tr key={s.id} className="hover:bg-slate-50/50 transition-colors group">
                                <td className="px-5 py-4">
                                    <div className="flex items-center gap-2.5">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 text-white font-bold text-xs shadow-sm shadow-orange-500/20">
                                            {s.name}
                                        </div>
                                        <div>
                                            <p className="font-semibold text-slate-900">{s.name}</p>
                                            <span className="inline-flex items-center gap-1 text-[10px] text-slate-400">
                                                {s.is_commercial
                                                    ? <><Building2 className="h-3 w-3" /> Commercial</>
                                                    : <><Home className="h-3 w-3" /> Household</>
                                                }
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-5 py-4 text-right"><PriceCell value={s.price?.gas_refill_price} /></td>
                                <td className="px-5 py-4 text-right"><PriceCell value={s.price?.new_cylinder_price} /></td>
                                <td className="px-5 py-4 text-right"><PriceCell value={s.price?.new_gas_fill_price} /></td>
                                <td className="px-5 py-4 text-right"><PriceCell value={s.price?.delivery_fee} /></td>
                                <td className="px-5 py-4">
                                    {s.price?.updated_at
                                        ? <span className="text-xs text-slate-400">{s.price.updated_at}</span>
                                        : <span className="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-600">Not set</span>
                                    }
                                </td>
                                <td className="px-5 py-4 text-right">
                                    <Button asChild variant="outline" size="sm" className="h-8 gap-1.5 text-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Link href={`/admin/catalogue/pricing/${s.id}/edit`}>
                                            <Pencil className="h-3 w-3" /> Edit
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
