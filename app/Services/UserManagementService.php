<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    /**
     * Staff / login users managed from Settings (exclude client traders).
     */
    public function paginate(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $clientTypeId = (int) (UserType::query()->where('name', 'client')->value('id') ?? 0);

        $query = User::query()
            ->with('userType:id,name')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->when($clientTypeId > 0, fn ($q) => $q->where('type_id', '!=', $clientTypeId))
            ->orderByDesc('id');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Roles assignable in Settings (exclude client traders).
     *
     * @return \Illuminate\Support\Collection<int, UserType>
     */
    public function assignableTypes()
    {
        return UserType::query()
            ->where('name', '!=', 'client')
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    /**
     * @param  array{name:string,email:string,password:string,type_id:int,phone?:string|null}  $data
     */
    public function create(array $data): User
    {
        $this->assertAssignableType((int) $data['type_id']);

        return DB::transaction(function () use ($data) {
            $ownerId = (int) (Auth::user()->owner_id ?? 1);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'type_id' => (int) $data['type_id'],
                'phone' => $data['phone'] ?? null,
                'owner_id' => $ownerId,
                'is_band' => 0,
                'created' => Carbon::now()->format('Y-m-d'),
                'year_date' => (int) Carbon::now()->format('Y'),
            ]);

            Log::info('Settings user created', [
                'user_id' => $user->id,
                'email' => $user->email,
                'type_id' => $user->type_id,
                'created_by' => Auth::id(),
            ]);

            return $user->load('userType:id,name');
        });
    }

    /**
     * @param  array{name:string,email:string,type_id:int,phone?:string|null,is_band?:bool|int}  $data
     */
    public function update(User $user, array $data): User
    {
        $this->assertAssignableType((int) $data['type_id']);
        $this->assertCanChangeType($user, (int) $data['type_id']);

        if ($this->isProtectedSystemVault($user)) {
            if ((string) $data['email'] !== (string) $user->email) {
                throw ValidationException::withMessages([
                    'email' => 'لا يمكن تغيير اسم مستخدم حسابات النظام.',
                ]);
            }
            if ((int) $data['type_id'] !== (int) $user->type_id) {
                throw ValidationException::withMessages([
                    'type_id' => 'لا يمكن تغيير صلاحية حسابات النظام.',
                ]);
            }
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'type_id' => (int) $data['type_id'],
            'phone' => $data['phone'] ?? null,
        ]);

        if (array_key_exists('is_band', $data)) {
            $user->is_band = (int) filter_var($data['is_band'], FILTER_VALIDATE_BOOLEAN);
        }

        $user->save();

        Log::info('Settings user updated', [
            'user_id' => $user->id,
            'updated_by' => Auth::id(),
        ]);

        return $user->fresh()->load('userType:id,name');
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->password = Hash::make($password);
        $user->save();

        Log::info('Settings user password reset', [
            'user_id' => $user->id,
            'reset_by' => Auth::id(),
        ]);
    }

    public function softDelete(User $actor, User $user): void
    {
        if ((int) $actor->id === (int) $user->id) {
            throw ValidationException::withMessages([
                'user' => 'لا يمكنك حذف حسابك الحالي.',
            ]);
        }

        if ($this->isProtectedSystemVault($user)) {
            throw ValidationException::withMessages([
                'user' => 'لا يمكن حذف حسابات النظام (قاصات / صناديق).',
            ]);
        }

        if ($this->isAdminType($user) && $this->activeAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'user' => 'لا يمكن حذف آخر مدير في النظام.',
            ]);
        }

        $snapshot = $user->only(['id', 'name', 'email', 'type_id', 'owner_id']);

        $user->delete();

        Log::info('Settings user soft-deleted', array_merge($snapshot, [
            'deleted_by' => $actor->id,
            'deleted_at' => now()->toDateTimeString(),
        ]));
    }

    /**
     * System cash vaults (mainBox, etc.) — email-based so staff "account" users stay manageable.
     */
    public function isProtectedSystemVault(User $user): bool
    {
        $email = (string) ($user->email ?? '');

        return $email !== '' && in_array($email, SystemWalletService::systemEmails(), true);
    }

    protected function assertAssignableType(int $typeId): void
    {
        $allowed = $this->assignableTypes()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! in_array($typeId, $allowed, true)) {
            throw ValidationException::withMessages([
                'type_id' => 'نوع المستخدم غير مسموح.',
            ]);
        }
    }

    protected function assertCanChangeType(User $user, int $newTypeId): void
    {
        if (! $this->isAdminType($user)) {
            return;
        }

        if ($newTypeId === (int) $user->type_id) {
            return;
        }

        if ($this->activeAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'type_id' => 'لا يمكن تغيير صلاحية آخر مدير في النظام.',
            ]);
        }
    }

    protected function isAdminType(User $user): bool
    {
        $adminTypeId = (int) (UserType::query()->where('name', 'admin')->value('id') ?? 1);

        return (int) $user->type_id === $adminTypeId;
    }

    protected function activeAdminCount(): int
    {
        $adminTypeId = (int) (UserType::query()->where('name', 'admin')->value('id') ?? 1);

        return User::query()->where('type_id', $adminTypeId)->count();
    }
}
