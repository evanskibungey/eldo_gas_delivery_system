<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateAccountRequest;
use App\Http\Requests\Admin\Settings\UpdateCommissionRequest;
use App\Http\Requests\Admin\Settings\UpdateDeliveryRequest;
use App\Http\Requests\Admin\Settings\UpdateGeneralRequest;
use App\Http\Requests\Admin\Settings\UpdatePointsRequest;
use App\Http\Requests\Admin\Settings\UpdateShopHoursRequest;
use App\Services\Admin\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => $this->settings->getAll(),
            'account'  => $this->settings->currentAdmin(),
        ]);
    }

    public function updateGeneral(UpdateGeneralRequest $request): RedirectResponse
    {
        $this->settings->updateGeneral($request->validated());

        return back()->with('success', 'General settings saved.');
    }

    public function updateShopHours(UpdateShopHoursRequest $request): RedirectResponse
    {
        $this->settings->updateShopHours($request->validated());

        return back()->with('success', 'Shop hours saved.');
    }

    public function updateDelivery(UpdateDeliveryRequest $request): RedirectResponse
    {
        $this->settings->updateDelivery($request->validated());

        return back()->with('success', 'Delivery settings saved.');
    }

    public function updateCommission(UpdateCommissionRequest $request): RedirectResponse
    {
        $this->settings->updateCommission($request->validated());

        return back()->with('success', 'Commission settings saved.');
    }

    public function updateAccount(UpdateAccountRequest $request): RedirectResponse
    {
        $this->settings->updateAccount($request->validated());

        return back()->with('success', 'Account updated.');
    }

    public function updatePoints(UpdatePointsRequest $request): RedirectResponse
    {
        $this->settings->updatePoints($request->validated());

        return back()->with('success', 'GasPoints settings saved.');
    }
}
