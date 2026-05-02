<?php

namespace App\Services\Admin;

use App\Models\Rider;
use App\Models\SystemSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RiderService
{
    public function paginated(array $filters): LengthAwarePaginator
    {
        return Rider::query()
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$v}%")->orWhere('phone', 'like', "%{$v}%"));
            })
            ->when(isset($filters['is_active']),    fn ($q) => $q->where('is_active',    (bool) $filters['is_active']))
            ->when(isset($filters['is_available']), fn ($q) => $q->where('is_available', (bool) $filters['is_available']))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);
        return str_starts_with($phone, '0') ? '+254' . substr($phone, 1) : $phone;
    }

    public function create(array $data, ?UploadedFile $photo): Rider
    {
        $photoPath = $photo ? $photo->store('riders', 'public') : null;

        return Rider::create([
            'name'                => $data['name'],
            'phone'               => $this->normalizePhone($data['phone']),
            'national_id'         => $data['national_id']       ?? null,
            'is_safety_certified' => $data['is_safety_certified'] ?? false,
            'certification_date'  => ($data['is_safety_certified'] ?? false) ? ($data['certification_date'] ?? null) : null,
            'is_active'           => $data['is_active']          ?? true,
            'photo_path'          => $photoPath,
        ]);
    }

    public function update(Rider $rider, array $data, ?UploadedFile $photo): Rider
    {
        $photoPath = $rider->photo_path;

        if ($photo) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }
            $photoPath = $photo->store('riders', 'public');
        }

        $rider->update([
            'name'                => $data['name'],
            'phone'               => $this->normalizePhone($data['phone']),
            'national_id'         => $data['national_id']        ?? null,
            'is_safety_certified' => $data['is_safety_certified'] ?? false,
            'certification_date'  => ($data['is_safety_certified'] ?? false) ? ($data['certification_date'] ?? null) : null,
            'is_active'           => $data['is_active']           ?? $rider->is_active,
            'is_available'        => $data['is_available']        ?? $rider->is_available,
            'photo_path'          => $photoPath,
        ]);

        return $rider;
    }

    public function deactivate(Rider $rider): void
    {
        if (! $rider->is_active) {
            throw ValidationException::withMessages([
                'rider' => 'This rider is already inactive.',
            ]);
        }

        $rider->update(['is_active' => false, 'is_available' => false]);
    }

    public function statsFor(Rider $rider): array
    {
        $commissionRate = (float) SystemSetting::get('commission_rate', '10.00');

        $totalRevenue = $rider->orders()
            ->where('status', 'delivered')
            ->sum('total_amount');

        $totalCommission = round($totalRevenue * $commissionRate / 100, 2);
        $totalEarnings   = round($totalRevenue - $totalCommission, 2);

        $recentOrders = $rider->orders()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'status', 'total_amount', 'created_at'])
            ->map(fn ($o) => [
                'id'            => $o->id,
                'status'        => $o->status,
                'total_amount'  => $o->total_amount,
                'commission'    => round($o->total_amount * $commissionRate / 100, 2),
                'rider_earning' => round($o->total_amount * (1 - $commissionRate / 100), 2),
                'created_at'    => $o->created_at->toDateTimeString(),
            ]);

        $recentRatings = $rider->ratings()
            ->with('customer:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'order_id', 'customer_id', 'stars', 'review', 'created_at'])
            ->map(fn ($r) => [
                'id'            => $r->id,
                'order_id'      => $r->order_id,
                'customer_name' => $r->customer?->name ?? 'Unknown',
                'stars'         => $r->stars,
                'review'        => $r->review,
                'created_at'    => $r->created_at?->toDateTimeString(),
            ]);

        return compact('commissionRate', 'totalRevenue', 'totalCommission', 'totalEarnings', 'recentOrders', 'recentRatings');
    }

    public function deriveStatus(Rider $rider): string
    {
        if (! $rider->is_active)  return 'offline';
        if ($rider->is_available) return 'available';
        return 'on_delivery';
    }
}
