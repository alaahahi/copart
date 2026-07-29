<?php

namespace App\Exports;

use App\Models\Car;
use App\Models\Transactions;
use App\Models\User;
use App\Services\VaultService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;


class ExportAccount implements FromCollection, WithHeadings
{
    protected $from;
    protected $to;
    protected $user_id;

    public function __construct($from, $to, $user_id)
    {
        $this->from = $from;
        $this->to = $to;
        $this->user_id = (int) $user_id;
    }


    public function collection()
    {
        $collection = new Collection();

        $user = User::find($this->user_id);
        if (! $user) {
            return $collection;
        }

        $vaultId = app(VaultService::class)->resolveVaultIdForLegacyUser((int) $user->id);
        $walletId = app(VaultService::class)->resolveWalletIdForLegacyUser((int) $user->id);
        $carIds = Car::where('client_id', $user->id)->pluck('id');

        $query = Transactions::with('morphed')
            ->where(function ($q) use ($user, $vaultId, $walletId, $carIds) {
                if ($vaultId) {
                    $q->orWhere('vault_id', $vaultId);
                }
                if ($walletId) {
                    $q->orWhere('wallet_id', $walletId);
                }
                $q->orWhere(function ($inner) use ($user) {
                    $inner->whereIn('morphed_type', [User::class, 'App\\Models\\User', 'App\Models\User'])
                        ->where('morphed_id', $user->id);
                });
                if ($carIds->isNotEmpty()) {
                    $q->orWhere(function ($inner) use ($carIds) {
                        $inner->whereIn('morphed_type', [Car::class, 'App\\Models\\Car', 'App\Models\Car'])
                            ->whereIn('morphed_id', $carIds);
                    });
                }
            });

        if ($this->from && $this->to) {
            $query->whereBetween('created', [$this->from, $this->to]);
        }

        $transactions = $query->get();

        $seqNo = 1;

        foreach ($transactions as $transaction) {
            $transactionData = [
                'seqNo' => $seqNo,
                'name' => $transaction->morphed->name ?? '',
                'amount' => $transaction->amount. ' ' .$transaction->currency,
                'description' => $transaction->description,
                'created' => $transaction->created,

            ];

            $collection->push($transactionData);
            $seqNo++;
        }

        return $collection;
    }

    public function headings(): array
    {
        return [
            'تسلسل',
            'حساب',
            'المبلغ',
            'الوصف',
            'تاريخ',

        ];
    }

}
