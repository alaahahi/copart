<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Expenses;
use App\Models\Transactions;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Car-linked payment allocation + soft-delete restore for wallet transactions.
 */
class TransactionPaymentService
{
    public function __construct(
        protected LedgerService $ledger
    ) {
    }

    /**
     * When deleting a payment that was applied to a car, undo car.paid / discount.
     */
    public function reverseCarPaymentAllocation(Transactions $transaction): void
    {
        $parts = $this->resolveCarPaymentParts($transaction);
        if ($parts === null) {
            return;
        }

        [$car, $paidPart, $discPart] = $parts;

        if ($paidPart > 0) {
            $car->paid = max(0, (float) $car->paid - $paidPart);
        }
        if ($discPart > 0) {
            $car->discount = max(0, (float) $car->discount - $discPart);
        }

        $this->syncCarResults($car);
        $car->save();
    }

    /**
     * Re-apply car.paid / discount after restoring a soft-deleted payment.
     */
    public function applyCarPaymentAllocation(Transactions $transaction): void
    {
        $parts = $this->resolveCarPaymentParts($transaction);
        if ($parts === null) {
            return;
        }

        [$car, $paidPart, $discPart] = $parts;

        if ($paidPart > 0) {
            $car->paid = (float) $car->paid + $paidPart;
        }
        if ($discPart > 0) {
            $car->discount = (float) $car->discount + $discPart;
        }

        $this->syncCarResults($car);
        $car->save();
    }

    /**
     * Restore a soft-deleted transaction tree (parent + children), journals, and car allocation.
     *
     * @throws ModelNotFoundException
     */
    public function restoreTransaction(int $transactionId, int $ownerId): Transactions
    {
        $originalTransaction = Transactions::onlyTrashed()
            ->with('TransactionsImages')
            ->find($transactionId);

        if (!$originalTransaction) {
            throw (new ModelNotFoundException())->setModel(Transactions::class, [$transactionId]);
        }

        $children = Transactions::onlyTrashed()
            ->where('parent_id', $transactionId)
            ->get();

        DB::transaction(function () use (
            $originalTransaction,
            $children,
            $ownerId
        ) {
            $syncedFromLedger = [];

            $voidOriginalRestored = $this->ledger->restoreJournalForTransaction($originalTransaction);
            if ($voidOriginalRestored) {
                $uid = Wallet::where('id', $originalTransaction->wallet_id)->value('user_id');
                if ($uid) {
                    $syncedFromLedger[] = (int) $uid;
                }
            } else {
                $this->legacyApplyWalletMovement($originalTransaction);
            }
            $originalTransaction->restore();

            foreach ($children as $transaction) {
                $restored = $this->ledger->restoreJournalForTransaction($transaction);
                if ($restored) {
                    $uid = Wallet::where('id', $transaction->wallet_id)->value('user_id');
                    if ($uid) {
                        $syncedFromLedger[] = (int) $uid;
                    }
                } else {
                    $this->legacyApplyWalletMovement($transaction);
                }
                $transaction->restore();
            }

            $firstChild = $children->first();
            if ($firstChild) {
                Expenses::onlyTrashed()
                    ->where('transaction_id', $firstChild->id)
                    ->restore();
            }

            $paymentLegs = collect([$originalTransaction->fresh()])->merge(
                $children->map(fn (Transactions $t) => $t->fresh())
            )->filter();

            foreach ($paymentLegs as $leg) {
                $this->applyCarPaymentAllocation($leg);
            }

            foreach (array_unique(array_filter($syncedFromLedger)) as $uid) {
                $this->ledger->syncWalletFromLedger((int) $ownerId, (int) $uid);
            }
        });

        Log::info('Transaction restored', [
            'transaction_id' => $transactionId,
            'by' => Auth::id(),
        ]);

        return $originalTransaction->fresh();
    }

    /**
     * @return array{0: Car, 1: float, 2: float}|null
     */
    protected function resolveCarPaymentParts(Transactions $transaction): ?array
    {
        $isCar = $transaction->morphed_type === Car::class
            || $transaction->morphed_type === 'App\\Models\\Car';

        if (!$isCar || (int) $transaction->morphed_id <= 0) {
            return null;
        }

        // Only once per payment tree — prefer the client "out" leg (is_pay=1).
        if ($transaction->type !== 'out' || (int) $transaction->is_pay !== 1) {
            return null;
        }

        $car = Car::find($transaction->morphed_id);
        if (!$car) {
            return null;
        }

        $details = is_array($transaction->details) ? $transaction->details : [];
        $paidPart = (float) ($details['paid'] ?? 0);
        $discPart = (float) ($details['discount'] ?? $transaction->discount ?? 0);

        if ($paidPart <= 0 && $discPart <= 0) {
            $gross = abs((float) $transaction->amount);
            $discPart = (float) ($transaction->discount ?? 0);
            $paidPart = max(0, $gross - $discPart);
        }

        return [$car, $paidPart, $discPart];
    }

    protected function syncCarResults(Car $car): void
    {
        $remaining = (float) $car->total_s - (float) $car->paid - (float) $car->discount;
        if ((float) $car->paid + (float) $car->discount <= 0) {
            $car->results = 0;
        } elseif ($remaining > 0) {
            $car->results = 1;
        } else {
            $car->results = 2;
        }
    }

    /**
     * Re-apply wallet effect for transactions created before ledger linking
     * (undoes legacyReverseWalletMovement used on delete).
     */
    protected function legacyApplyWalletMovement(Transactions $transaction): void
    {
        $wallet = Wallet::find($transaction->wallet_id);
        if (!$wallet) {
            return;
        }

        if ($transaction->currency === 'IQD') {
            $wallet->increment('balance_dinar', $transaction->amount);
        } else {
            $wallet->increment('balance', $transaction->amount);
        }
    }
}
