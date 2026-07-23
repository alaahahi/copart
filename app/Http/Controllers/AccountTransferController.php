<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferBetweenAccountsRequest;
use App\Services\AccountTransferService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AccountTransferController extends Controller
{
    protected function authorizeTransfer(): void
    {
        if (!Auth::check() || !in_array((int) Auth::user()->type_id, [1, 6], true)) {
            abort(403, 'غير مسموح');
        }
    }

    /**
     * List of "account" wallet users eligible as From/To for a transfer.
     */
    public function accounts(AccountTransferService $transfers)
    {
        $this->authorizeTransfer();

        $ownerId = (int) Auth::user()->owner_id;

        return Response::json([
            'accounts' => $transfers->transferableAccounts($ownerId),
        ], 200);
    }

    public function store(TransferBetweenAccountsRequest $request, AccountTransferService $transfers)
    {
        $this->authorizeTransfer();

        $ownerId = (int) Auth::user()->owner_id;
        $validated = $request->validated();

        try {
            $result = $transfers->transfer(
                $ownerId,
                (int) $validated['from_user_id'],
                (int) $validated['to_user_id'],
                (float) $validated['amount'],
                $validated['currency'],
                $validated['notes'] ?? null,
                $validated['entry_date'] ?? null
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Response::json(['message' => 'تعذر تنفيذ التحويل'], 500);
        }

        return Response::json([
            'message' => 'تم تنفيذ التحويل بنجاح',
            'journal_entry_id' => $result['journal']->id,
            'voucher_no' => $result['journal']->voucher_no,
        ], 201);
    }
}
