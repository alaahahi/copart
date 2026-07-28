<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Provisions ledger accounts when a trader (تاجر) is created or accounting visibility is toggled.
 *
 * Rules (see LedgerService constants):
 * - Always: AR / car payments account 1200-{clientId}
 * - If users.show_in_dashboard (عرض بالمحاسبة): also 1210-{id} USD + 1220-{id} IQD custody
 * - This is NOT a system vault (قاصة) — vaults live in the vaults table
 * - Turning visibility off never deletes historical accounts
 */
class ClientAccountService
{
    public function __construct(
        protected LedgerService $ledger
    ) {
    }

    /**
     * @return array{ar:LedgerAccount,qasa_usd:?LedgerAccount,qasa_iqd:?LedgerAccount}
     */
    public function provisionForClient(User $client): array
    {
        $ownerId = (int) $client->owner_id;
        $withQasa = LedgerService::clientHasQasa($client);

        $accounts = $this->ledger->ensureClientLedgerAccounts(
            $ownerId,
            (int) $client->id,
            $withQasa
        );

        Log::info('Trader ledger accounts provisioned', [
            'client_id' => $client->id,
            'owner_id' => $ownerId,
            'show_in_accounting' => $withQasa,
            'ar_code' => $accounts['ar']->code,
            'custody_usd_code' => $accounts['qasa_usd']?->code,
            'custody_iqd_code' => $accounts['qasa_iqd']?->code,
        ]);

        return $accounts;
    }

    /**
     * When عرض بالمحاسبة is enabled on edit/toggle — create any missing custody accounts.
     * When disabled — leave existing accounts intact.
     *
     * @return array{ar:LedgerAccount,qasa_usd:?LedgerAccount,qasa_iqd:?LedgerAccount}
     */
    public function syncQasaFlag(User $client): array
    {
        return $this->provisionForClient($client);
    }
}
