<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVaultRequest;
use App\Http\Requests\UpdateVaultRequest;
use App\Models\Vault;
use App\Services\VaultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class VaultController extends Controller
{
    /**
     * Inertia page: dedicated vaults management (قاصات النظام).
     */
    public function page()
    {
        $this->authorize('viewAny', Vault::class);

        return Inertia::render('Vaults/Index');
    }

    public function index(Request $request, VaultService $vaults)
    {
        $this->authorize('viewAny', Vault::class);

        $ownerId = (int) Auth::user()->owner_id;

        // Rich rows for Vaults Index UI (balance, can_delete, legacy wallet id).
        if ($request->boolean('for_ui')) {
            return Response::json([
                'data' => $vaults->systemQasaClientRows($ownerId)->values(),
            ], 200);
        }

        $activeOnly = ! $request->boolean('include_inactive');

        $list = $vaults->listForOwner($ownerId, $activeOnly)->map(fn (Vault $v) => $this->serialize($v));

        return Response::json(['vaults' => $list], 200);
    }

    public function store(StoreVaultRequest $request, VaultService $vaults)
    {
        $ownerId = (int) Auth::user()->owner_id;
        $data = $request->validated();

        try {
            $vault = $vaults->create($ownerId, $data);
        } catch (InvalidArgumentException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return Response::json(['message' => 'تعذر إنشاء القاصة'], 500);
        }

        return Response::json([
            'message' => 'تم إنشاء القاصة وتسجيلها في المحاسبة',
            'vault' => $this->serialize($vault),
        ], 201);
    }

    public function update(UpdateVaultRequest $request, Vault $vault, VaultService $vaults)
    {
        if ((int) $vault->owner_id !== (int) Auth::user()->owner_id) {
            abort(403, 'غير مسموح');
        }

        try {
            $updated = $vaults->update($vault, $request->validated());
        } catch (InvalidArgumentException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return Response::json(['message' => 'تعذر تحديث القاصة'], 500);
        }

        return Response::json([
            'message' => 'تم تحديث القاصة',
            'vault' => $this->serialize($updated),
        ], 200);
    }

    public function destroy(Vault $vault, VaultService $vaults)
    {
        $this->authorize('delete', $vault);

        if ((int) $vault->owner_id !== (int) Auth::user()->owner_id) {
            abort(403, 'غير مسموح');
        }

        try {
            $result = $vaults->softDelete($vault);
        } catch (InvalidArgumentException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return Response::json(['message' => 'تعذر حذف القاصة'], 500);
        }

        return Response::json([
            'message' => 'تم حذف القاصة',
            'vault_id' => $result['vault']->id,
        ], 200);
    }

    /**
     * Toggle show_in_accounting on a vault (Accounting orange shortcuts).
     */
    public function toggleAccounting(Request $request, Vault $vault, VaultService $vaults)
    {
        $this->authorize('update', $vault);

        if ((int) $vault->owner_id !== (int) Auth::user()->owner_id) {
            abort(403, 'غير مسموح');
        }

        $show = $request->has('show_in_accounting')
            ? (bool) $request->boolean('show_in_accounting')
            : ! (bool) $vault->show_in_accounting;

        $updated = $vaults->update($vault, ['show_in_accounting' => $show]);

        return Response::json([
            'message' => 'تم تحديث عرض القاصة في المحاسبة',
            'show_in_accounting' => (bool) $updated->show_in_accounting,
            'vault' => $this->serialize($updated),
        ], 200);
    }

    protected function serialize(Vault $vault): array
    {
        $vault->loadMissing(['legacyUser']);
        $vaults = app(VaultService::class);

        return [
            'id' => $vault->id,
            'vault_id' => $vault->id,
            'name' => $vault->name,
            'code' => $vault->code,
            'type' => $vault->type,
            'currency_default' => $vault->currency_default,
            'is_active' => (bool) $vault->is_active,
            'show_in_accounting' => (bool) $vault->show_in_accounting,
            'wallet_id' => null,
            'legacy_user_id' => $vault->legacy_user_id ? (int) $vault->legacy_user_id : null,
            'ledger_account_id' => $vault->ledger_account_id ? (int) $vault->ledger_account_id : null,
            'notes' => $vault->notes,
            'balance' => $vaults->cashBalance($vault, '$'),
            'balance_dinar' => $vaults->cashBalance($vault, 'IQD'),
            'is_vault' => true,
        ];
    }
}
