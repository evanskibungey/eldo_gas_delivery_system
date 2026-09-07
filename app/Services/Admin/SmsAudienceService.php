<?php

namespace App\Services\Admin;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who a bulk SMS goes to.
 *
 * Audiences are named rather than free-form so a campaign record still means
 * something months later: "no_order_60d" is answerable, an arbitrary saved
 * query is not.
 */
class SmsAudienceService
{
    /**
     * Selectable audiences, in the order they appear in the composer.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return [
            'all_active' => 'All active customers',
            'ordered_30d' => 'Ordered in the last 30 days',
            'no_order_60d' => 'No order in 60+ days',
            'never_ordered' => 'Never ordered',
        ];
    }

    /**
     * Resolve an audience to customers who can actually be texted.
     *
     * @param  array<int, int>  $selectedIds  Used when $audience is 'selected'.
     */
    public function resolve(string $audience, array $selectedIds = [], bool $excludeOptedOut = true): Collection
    {
        $query = Customer::query()
            ->where('is_active', true)
            // A blank or malformed number costs a segment and delivers nothing.
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        if ($excludeOptedOut) {
            $query->whereNull('sms_opt_out_at');
        }

        $this->applyAudience($query, $audience, $selectedIds);

        return $query->orderBy('id')->get(['id', 'name', 'phone']);
    }

    /**
     * Everyone the audience matched who will NOT be texted because they opted
     * out. Reported to the admin before sending, so a shrinking recipient count
     * has a visible reason.
     */
    public function optedOutCount(string $audience, array $selectedIds = []): int
    {
        $query = Customer::query()
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('sms_opt_out_at');

        $this->applyAudience($query, $audience, $selectedIds);

        return $query->count();
    }

    /**
     * @param  array<int, int>  $selectedIds
     */
    private function applyAudience(Builder $query, string $audience, array $selectedIds): void
    {
        match ($audience) {
            // Hand-picked from the customer list. An empty selection must match
            // nobody rather than everybody — whereIn([]) is what guarantees it.
            'selected' => $query->whereIn('id', $selectedIds),

            'ordered_30d' => $query->whereHas(
                'orders',
                fn ($q) => $q->where('created_at', '>=', now()->subDays(30)),
            ),

            // Has ordered at some point, but not recently. Excludes customers
            // who never ordered — they are a different audience with a
            // different message.
            'no_order_60d' => $query
                ->whereHas('orders')
                ->whereDoesntHave(
                    'orders',
                    fn ($q) => $q->where('created_at', '>=', now()->subDays(60)),
                ),

            'never_ordered' => $query->whereDoesntHave('orders'),

            'all_active' => null,

            default => $query->whereRaw('1 = 0'),
        };
    }
}
