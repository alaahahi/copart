<?php

namespace App\Support;

use App\Models\SystemConfig;
use App\Models\Transactions;
use App\Models\User;

class VoucherPrint
{
    public static function usesMklTemplate(?SystemConfig $config): bool
    {
        return ($config->receipt_template ?? 'default') === 'mkl_usd';
    }

    public static function viewFor(?SystemConfig $config, string $defaultView): string
    {
        return self::usesMklTemplate($config) ? 'receiptVoucherMkl' : $defaultView;
    }

    public static function dataFromTransaction(
        ?Transactions $transaction,
        ?User $client,
        string $voucherType
    ): array {
        $details = is_array($transaction?->details) ? $transaction->details : [];
        $currency = $transaction->currency ?? '$';
        $amount = 0;

        if ($transaction) {
            if (in_array($transaction->type, ['inUser', 'inUserAmanah'], true)) {
                $voucherType = 'receipt';
                $amount = abs((float) $transaction->amount) - (float) ($transaction->discount ?? 0);
            } elseif (in_array($transaction->type, ['outUser', 'outUserAmanah'], true)) {
                $voucherType = 'payment';
                $amount = abs((float) $transaction->amount);
            } elseif ($voucherType === 'receipt') {
                $amount = abs((float) $transaction->amount * -1) - (float) ($transaction->discount ?? 0);
            } else {
                $amount = abs((float) $transaction->amount);
            }
        }

        return [
            'voucherType' => $voucherType,
            'clientName' => $client->name ?? '',
            'amount' => $amount,
            'currency' => $currency,
            'created' => $transaction->created_at ?? now(),
            'description' => $transaction->description ?? '',
            'vin' => $details['vin'] ?? ($details['VIN'] ?? ''),
            'lot' => $details['lot'] ?? ($details['LOT'] ?? ''),
            'paidUp' => $details['paid_up'] ?? ($details['paidUp'] ?? ''),
            'rest' => $details['rest'] ?? ($details['remaining'] ?? ''),
            'transactions_id' => $transaction->id ?? null,
        ];
    }
}
