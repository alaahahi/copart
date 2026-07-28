<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetManagedUserPasswordRequest;
use App\Http\Requests\StoreManagedUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    public function __construct(
        protected UserManagementService $users
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $paginator = $this->users->paginate($request->string('q')->toString() ?: null);

        $rows = collect($paginator->items())->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'type_id' => $user->type_id,
                'type_name' => $user->userType?->name,
                'is_band' => (bool) $user->is_band,
                'is_self' => (int) $user->id === (int) Auth::id(),
                'is_system_vault' => $this->users->isProtectedSystemVault($user),
                'created_at' => optional($user->created_at)?->toDateString(),
            ];
        })->values();

        return Inertia::render('Settings/Users', [
            'users' => [
                'data' => $rows,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'links' => $paginator->linkCollection()->toArray(),
            ],
            'userTypes' => $this->users->assignableTypes(),
            'filters' => [
                'q' => $request->string('q')->toString(),
            ],
            'authUserId' => Auth::id(),
        ]);
    }

    public function store(StoreManagedUserRequest $request)
    {
        $this->authorize('create', User::class);

        $this->users->create($request->validated());

        return redirect()
            ->route('settings.users')
            ->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    public function update(UpdateManagedUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $this->users->update($user, $request->validated());

        return redirect()
            ->route('settings.users')
            ->with('success', 'تم تحديث بيانات المستخدم.');
    }

    public function resetPassword(ResetManagedUserPasswordRequest $request, User $user)
    {
        $this->authorize('resetPassword', $user);

        $this->users->resetPassword($user, $request->validated('password'));

        return redirect()
            ->route('settings.users')
            ->with('success', 'تم تغيير كلمة المرور بنجاح.');
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $this->users->softDelete(Auth::user(), $user);

        return redirect()
            ->route('settings.users')
            ->with('success', 'تم حذف المستخدم.');
    }
}
