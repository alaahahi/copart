<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Expenses;
use App\Models\Transactions;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
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
     * Soft-delete a payment tree (root + children), void journals, reverse car.paid.
     * Resolves to the root parent so deleting a client "out" leg also removes the
     * cash-box receipt that actually holds the money movement.
     *
     * @return Collection<int, Transactions> soft-deleted legs
     */
    public function softDeleteTransactionTree(int $transactionId, int $ownerId, ?callable $legacyReverseWallet = null): Collection
    {
        $seed = Transactions::with('TransactionsImages')->find($transactionId);
        if (!$seed) {
            throw (new ModelNotFoundException())->setModel(Transactions::class, [$transactionId]);
        }

        $root = $this->resolveTreeRoot($seed);
        $legs = $this->collectPaymentTree($root);

        $syncedFromLedger = [];
        $deleted = collect();

        DB::transaction(function () use (
            $legs,
            $ownerId,
            $legacyReverseWallet,
            &$syncedFromLedger,
            &$deleted
        ) {
            foreach ($legs as $leg) {
                $this->reverseCarPaymentAllocation($leg);
            }

            foreach ($legs as $leg) {
                $voided = $this->ledger->voidJournalForTransaction(
                    $leg,
                    'حذف حركة #' . $leg->id
                );
                $uid = Wallet::where('id', $leg->wallet_id)->value('user_id');
                if ($voided && $uid) {
                    $syncedFromLedger[] = (int) $uid;
                } elseif (!$voided && $legacyReverseWallet) {
                    $legacyReverseWallet($leg);
                }

                foreach ($leg->TransactionsImages ?? [] as $transactionsImage) {
                    $path = public_path('uploads/' . $transactionsImage->name);
                    $pathResized = public_path('uploadsResized/' . $transactionsImage->name);
                    if (is_file($path)) {
                        @unlink($path);
                    }
                    if (is_file($pathResized)) {
                        @unlink($pathResized);
                    }
                    $transactionsImage->delete();
                }

                if (!$leg->trashed()) {
                    $leg->delete();
                }
                $deleted->push($leg);
            }

            $firstChild = $legs->first(fn (Transactions $t) => (int) $t->parent_id > 0) ?? $legs->first();
            if ($firstChild) {
                Expenses::where('transaction_id', $firstChild->id)->delete();
            }

            foreach (array_unique(array_filter($syncedFromLedger)) as $uid) {
                $this->ledger->syncWalletFromLedger((int) $ownerId, (int) $uid);
            }
        });

        Log::info('Transaction tree deleted', [
            'transaction_id' => $transactionId,
            'root_id' => $root->id,
            'leg_ids' => $deleted->pluck('id')->all(),
            'by' => Auth::id(),
        ]);

        return $deleted;
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
        $seed = Transactions::onlyTrashed()
            ->with('TransactionsImages')
            ->find($transactionId);

        if (!$seed) {
            // Allow restore by child id even if caller passed an id that was the root
            // but seed must be trashed — also try withTrashed and walk to trashed root.
            $any = Transactions::withTrashed()->find($transactionId);
            if (!$any) {
                throw (new ModelNotFoundException())->setModel(Transactions::class, [$transactionId]);
            }
            $rootCandidate = $this->resolveTreeRoot($any);
            $seed = Transactions::onlyTrashed()->with('TransactionsImages')->find($rootCandidate->id);
            if (!$seed) {
                throw (new ModelNotFoundException())->setModel(Transactions::class, [$transactionId]);
            }
        } else {
            $rootCandidate = $this->resolveTreeRoot($seed);
            if ((int) $rootCandidate->id !== (int) $seed->id) {
                $rootTrashed = Transactions::onlyTrashed()
                    ->with('TransactionsImages')
                    ->find($rootCandidate->id);
                if ($rootTrashed) {
                    $seed = $rootTrashed;
                }
            }
        }

        $originalTransaction = $seed;
        $children = Transactions::onlyTrashed()
            ->where('parent_id', $originalTransaction->id)
            ->get();

        // Currency-convert pairs may be mutual parents — include sibling if trashed.
        if ((int) $originalTransaction->parent_id > 0) {
            $mutual = Transactions::onlyTrashed()->find($originalTransaction->parent_id);
            if ($mutual && !$children->contains('id', $mutual->id) && (int) $mutual->id !== (int) $originalTransaction->id) {
                $children = $children->push($mutual)->unique('id');
            }
        }

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
            'root_id' => $originalTransaction->id,
            'by' => Auth::id(),
        ]);

        return $originalTransaction->fresh();
    }

    /**
     * Walk parent_id to the payment-tree root (guards against currency-convert cycles).
     */
    public function resolveTreeRoot(Transactions $transaction): Transactions
    {
        $current = $transaction;
        $seen = [];
        $guard = 0;

        while ((int) $current->parent_id > 0 && $guard++ < 20) {
            if (isset($seen[$current->id])) {
                break;
            }
            $seen[$current->id] = true;

            $parent = Transactions::withTrashed()->find($current->parent_id);
            if (!$parent) {
                break;
            }
            // Mutual parent cycle (currency convert): treat the lower id as root.
            if (isset($seen[$parent->id])) {
                return ((int) $current->id <= (int) $parent->id) ? $current : $parent;
            }
            $current = $parent;
        }

        return $current;
    }

    /**
     * @return Collection<int, Transactions>
     */
    public function collectPaymentTree(Transactions $root): Collection
    {
        $root = $root->loadMissing('TransactionsImages');
        $children = Transactions::with('TransactionsImages')
            ->where('parent_id', $root->id)
            ->get();

        // Mutual parent (currency convert): include the other leg.
        if ((int) $root->parent_id > 0) {
            $other = Transactions::with('TransactionsImages')->find($root->parent_id);
            if ($other && (int) $other->id !== (int) $root->id && !$children->contains('id', $other->id)) {
                $children->push($other);
            }
        }

        return collect([$root])->merge($children)->unique('id')->values();
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
