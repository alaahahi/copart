<?php

namespace App\Support;

use App\Models\SystemConfig;
use App\Models\Transactions;
use App\Models\User;
use App\Services\SystemConfigService;

class VoucherPrint
{
    /** Defaults aligned with Settings «بيانات التذليل (قالب MKL)» placeholders. */
    public const DEFAULT_PHONE = '+964 750 468 0510 / 750 705 3555';

    public const DEFAULT_ADDRESS = '100 M road near Hanouf motel';

    public const DEFAULT_WEBSITE = 'Mklmersin.com';

    public static function usesMklTemplate(?SystemConfig $config): bool
    {
        return ($config->receipt_template ?? 'default') === 'mkl_usd';
    }

    public static function viewFor(?SystemConfig $config, string $defaultView): string
    {
        return self::usesMklTemplate($config) ? 'receiptVoucherMkl' : $defaultView;
    }

    /**
     * Resolve voucher footer contact from live system_config (prefer saved Settings values).
     *
     * @param  SystemConfig|array<string, mixed>|null  $config
     * @return array{phone: string, address: string, website: string}
     */
    public static function footerContact(SystemConfig|array|null $config = null): array
    {
        $fresh = [];
        try {
            $fresh = app(SystemConfigService::class)->current()->toArray();
        } catch (\Throwable) {
            $fresh = [];
        }

        $passed = [];
        if ($config instanceof SystemConfig) {
            $passed = $config->toArray();
        } elseif (is_array($config)) {
            $passed = $config;
        }

        // Prefer the live system_config row; passed print payload is only a fallback.
        $cfg = array_merge($passed, $fresh);

        $phone = self::nonEmpty(
            $cfg['receipt_phone'] ?? null,
            $cfg['mobile_erb'] ?? null,
            $cfg['mobile_kik'] ?? null,
            self::DEFAULT_PHONE
        );
        $address = self::nonEmpty(
            $cfg['receipt_address'] ?? null,
            $cfg['address_erb'] ?? null,
            $cfg['address_kik'] ?? null,
            self::DEFAULT_ADDRESS
        );
        $website = self::nonEmpty(
            $cfg['receipt_website'] ?? null,
            self::DEFAULT_WEBSITE
        );

        return [
            'phone' => $phone,
            'address' => $address,
            'website' => $website,
        ];
    }

    private static function nonEmpty(mixed ...$candidates): string
    {
        foreach ($candidates as $value) {
            $trimmed = trim((string) ($value ?? ''));
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return '';
    }

    /**
     * حدد إن كانت الحركة المالية دفعة مرتبطة بسيارة محددة (دفعة على السيارة)
     * أو دفعة عامة للزبون غير مرتبطة بسيارة (اضافة دفعة). يُستخدم لإظهار/إخفاء
     * حقلي "رقم اللوت" و"المتبقي" في وصل القبض.
     */
    public static function isCarPayment(?Transactions $transaction): bool
    {
        if (!$transaction) {
            return false;
        }

        if ($transaction->morphed_type === \App\Models\Car::class) {
            return true;
        }

        $details = is_array($transaction->details) ? $transaction->details : [];

        return !empty($details['car_id']);
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

        $isCarPayment = self::isCarPayment($transaction);

        return [
            'voucherType' => $voucherType,
            'clientName' => $client->name ?? '',
            'amount' => $amount,
            'currency' => $currency,
            'created' => $transaction->created_at ?? now(),
            'description' => $transaction->description ?? '',
            // اللوت والمتبقي يُعرضان فقط لدفعة سيارة محددة، وليس لدفعة عامة للزبون (اضافة دفعة)
            'isCarPayment' => $isCarPayment,
            'vin' => $isCarPayment ? ($details['vin'] ?? ($details['VIN'] ?? '')) : '',
            'lot' => $isCarPayment ? ($details['lot'] ?? ($details['LOT'] ?? '')) : '',
            'paidUp' => $isCarPayment ? ($details['paid_up'] ?? ($details['paidUp'] ?? '')) : '',
            'rest' => $isCarPayment ? ($details['rest'] ?? ($details['remaining'] ?? '')) : '',
            'transactions_id' => $transaction->id ?? null,
        ];
    }
}
