<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Riders\StoreRiderRequest;
use App\Http\Requests\Admin\Riders\UpdateRiderRequest;
use App\Models\Rider;
use App\Services\Admin\RiderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RiderController extends Controller
{
    public function __construct(private readonly RiderService $riders) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'is_active', 'is_available']);
        $riders  = $this->riders->paginated($filters);

        return Inertia::render('Admin/Riders/Index', [
            'riders' => $riders->through(fn ($r) => [
                'id'                  => $r->id,
                'name'                => $r->name,
                'phone'               => $r->phone,
                'photo_url'           => $r->avatar_url,
                'is_active'           => $r->is_active,
                'is_available'        => $r->is_available,
                'is_safety_certified' => $r->is_safety_certified,
                'certification_valid' => $r->isCertificationValid(),
                'avg_rating'          => $r->avg_rating,
                'total_deliveries'    => $r->total_deliveries,
                'status'              => $this->riders->deriveStatus($r),
            ]),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Riders/Create');
    }

    public function store(StoreRiderRequest $request): RedirectResponse
    {
        $rider = $this->riders->create($request->validated(), $request->file('photo'));

        return redirect()->route('admin.riders.show', $rider)
            ->with('success', "Rider {$rider->name} added successfully.");
    }

    public function show(Rider $rider): Response
    {
        $stats = $this->riders->statsFor($rider);

        return Inertia::render('Admin/Riders/Show', [
            'rider' => [
                'id'                  => $rider->id,
                'name'                => $rider->name,
                'phone'               => $rider->phone,
                'national_id'         => $rider->national_id,
                'photo_url'           => $rider->avatar_url,
                'is_active'           => $rider->is_active,
                'is_available'        => $rider->is_available,
                'is_safety_certified' => $rider->is_safety_certified,
                'certification_date'  => $rider->certification_date?->toDateString(),
                'certification_valid' => $rider->isCertificationValid(),
                'avg_rating'          => $rider->avg_rating,
                'total_deliveries'    => $rider->total_deliveries,
                'status'              => $this->riders->deriveStatus($rider),
                'created_at'          => $rider->created_at->toDateString(),
            ],
            'stats' => $stats,
        ]);
    }

    public function edit(Rider $rider): Response
    {
        return Inertia::render('Admin/Riders/Edit', [
            'rider' => [
                'id'                  => $rider->id,
                'name'                => $rider->name,
                'phone'               => $rider->phone,
                'national_id'         => $rider->national_id,
                'photo_url'           => $rider->avatar_url,
                'is_active'           => $rider->is_active,
                'is_available'        => $rider->is_available,
                'is_safety_certified' => $rider->is_safety_certified,
                'certification_date'  => $rider->certification_date?->toDateString(),
            ],
        ]);
    }

    public function update(UpdateRiderRequest $request, Rider $rider): RedirectResponse
    {
        $this->riders->update($rider, $request->validated(), $request->file('photo'));

        return redirect()->route('admin.riders.show', $rider)
            ->with('success', "Rider {$rider->name} updated successfully.");
    }

    public function destroy(Rider $rider): RedirectResponse
    {
        $this->riders->deactivate($rider);

        return redirect()->route('admin.riders.index')
            ->with('success', "{$rider->name} has been deactivated.");
    }
}
