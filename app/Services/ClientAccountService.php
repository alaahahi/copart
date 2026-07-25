<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Provisions ledger accounts when a client (تاجر) is created or قاصة is toggled.
 *
 * Rules (see LedgerService constants):
 * - Always: AR / car payments account 1200-{clientId}
 * - If users.show_in_dashboard (قاصة): also 1210-{id} USD + 1220-{id} IQD
 * - Turning قاصة off never deletes historical accounts
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

        Log::info('Client ledger accounts provisioned', [
            'client_id' => $client->id,
            'owner_id' => $ownerId,
            'has_qasa' => $withQasa,
            'ar_code' => $accounts['ar']->code,
            'qasa_usd_code' => $accounts['qasa_usd']?->code,
            'qasa_iqd_code' => $accounts['qasa_iqd']?->code,
        ]);

        return $accounts;
    }

    /**
     * When قاصة flag is enabled on edit/toggle — create any missing قاصة accounts.
     * When disabled — leave existing accounts intact.
     *
     * @return array{ar:LedgerAccount,qasa_usd:?LedgerAccount,qasa_iqd:?LedgerAccount}
     */
    public function syncQasaFlag(User $client): array
    {
        return $this->provisionForClient($client);
    }
}
