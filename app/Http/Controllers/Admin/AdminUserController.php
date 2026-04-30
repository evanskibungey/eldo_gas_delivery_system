<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreAdminUserRequest;
use App\Http\Requests\Admin\Users\UpdateAdminUserRequest;
use App\Models\Admin;
use App\Services\Admin\AdminUserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(private readonly AdminUserService $users) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'admins' => $this->users->listForIndex(),
            'roles'  => $this->users->getRoles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => $this->users->getRoles(),
        ]);
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $admin = $this->users->create($request->validated());

        return redirect()->route('admin.users.index')
            ->with('success', "Admin user {$admin->name} created successfully.");
    }

    public function show(Admin $user): Response
    {
        return $this->edit($user);
    }

    public function edit(Admin $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'admin'   => $this->users->formatForEdit($user),
            'roles'   => $this->users->getRoles(),
            'is_self' => $this->users->isSelf($user),
        ]);
    }

    public function update(UpdateAdminUserRequest $request, Admin $user): RedirectResponse
    {
        $admin = $this->users->update($user, $request->validated(), $this->users->isSelf($user));

        return redirect()->route('admin.users.index')
            ->with('success', "Admin user {$admin->name} updated successfully.");
    }

    public function destroy(Admin $user): RedirectResponse
    {
        $this->users->delete($user);

        return redirect()->route('admin.users.index')
            ->with('success', "Admin user deleted.");
    }
}
