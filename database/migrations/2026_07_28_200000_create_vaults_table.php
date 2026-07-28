<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: dedicated vaults (قاصات) table — source of truth for system cash boxes.
 * Legacy User+Wallet rows stay for balance/history continuity via wallet_id + legacy_user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vaults')) {
            Schema::create('vaults', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_id')->index();
                $table->string('name', 255);
                $table->string('code', 64);
                $table->string('type', 32)->default('system')
                    ->comment('cash|system|commission|company|expense|supplier|contracts');
                $table->string('currency_default', 10)->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('show_in_accounting')->default(true);
                $table->unsignedBigInteger('wallet_id')->nullable()->index();
                $table->unsignedBigInteger('legacy_user_id')->nullable()->index();
                $table->unsignedBigInteger('ledger_account_id')->nullable()->index();
                $table->string('notes', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['owner_id', 'code']);
                $table->index(['owner_id', 'type']);
                $table->index(['owner_id', 'is_active']);
            });
        }

        $this->backfillFromLegacyUsers();
    }

    public function down(): void
    {
        Schema::dropIfExists('vaults');
    }

    /**
     * Map existing system / commission vault users into vaults rows (idempotent).
     */
    protected function backfillFromLegacyUsers(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('vaults')) {
            return;
        }

        $systemEmails = [
            'mainBox@account.com',
            'main@account.com',
            'in@account.com',
            'out@account.com',
            'debt@account.com',
            'transfers@account.com',
            'supplier-out',
            'supplier-debt',
            'howler',
            'shipping-coc',
            'border',
            'iran',
            'dubai',
            'online-contracts',
            'online-contracts-dinar',
            'online-contracts-debt',
            'online-contracts-debit-dinar',
        ];

        $legacyNames = [
            'مصاريف الشركة',
            'Company Expenses',
            'عمولة امريكا',
            'عمولة كندا',
        ];

        $accountTypeId = DB::table('user_type')->where('name', 'account')->value('id');
        $clientTypeId = DB::table('user_type')->where('name', 'client')->value('id');

        $query = DB::table('users')->whereNull('deleted_at');

        $query->where(function ($outer) use ($accountTypeId, $clientTypeId, $systemEmails, $legacyNames) {
            if ($accountTypeId) {
                $outer->where('type_id', $accountTypeId);
            }
            $outer->orWhere(function ($legacy) use ($clientTypeId, $systemEmails, $legacyNames) {
                if ($clientTypeId) {
                    $legacy->where('type_id', $clientTypeId);
                }
                $legacy->where(function ($inner) use ($systemEmails, $legacyNames) {
                    $inner->whereIn('email', $systemEmails)
                        ->orWhereIn('name', $legacyNames)
                        ->orWhere('name', 'like', 'عمولة%');
                });
            });
        });

        $users = $query->get();
        $now = now();

        foreach ($users as $user) {
            $ownerId = (int) ($user->owner_id ?? 0);
            if ($ownerId <= 0) {
                continue;
            }

            $code = $this->resolveCode($user);
            $exists = DB::table('vaults')
                ->where('owner_id', $ownerId)
                ->where('code', $code)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue;
            }

            $walletId = null;
            if (Schema::hasTable('wallets')) {
                $walletId = DB::table('wallets')->where('user_id', $user->id)->value('id');
            }

            DB::table('vaults')->insert([
                'owner_id' => $ownerId,
                'name' => $user->name ?: $code,
                'code' => $code,
                'type' => $this->resolveType($user, $systemEmails),
                'currency_default' => null,
                'is_active' => true,
                'show_in_accounting' => true,
                'wallet_id' => $walletId,
                'legacy_user_id' => (int) $user->id,
                'ledger_account_id' => null,
                'notes' => 'Migrated from legacy vault user',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }
    }

    protected function resolveCode(object $user): string
    {
        $email = trim((string) ($user->email ?? ''));
        if ($email !== '') {
            $at = strpos($email, '@');
            $base = $at === false ? $email : substr($email, 0, $at);

            return substr(preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $base) ?: 'vault', 0, 64);
        }

        $name = trim((string) ($user->name ?? 'vault'));
        $slug = preg_replace('/\s+/u', '-', $name) ?: 'vault-'.$user->id;

        return substr('u'.$user->id.'-'.preg_replace('/[^\p{L}\p{N}_\-]+/u', '', $slug), 0, 64);
    }

    /**
     * @param  list<string>  $systemEmails
     */
    protected function resolveType(object $user, array $systemEmails): string
    {
        $email = (string) ($user->email ?? '');
        $name = trim((string) ($user->name ?? ''));

        if (strcasecmp($email, 'mainBox@account.com') === 0) {
            return 'cash';
        }

        if (in_array($name, ['مصاريف الشركة', 'Company Expenses'], true)) {
            return 'company';
        }

        if ($name !== '' && (str_starts_with($name, 'عمولة') || in_array($name, ['عمولة امريكا', 'عمولة كندا'], true))) {
            return 'commission';
        }

        $expenseEmails = [
            'howler', 'shipping-coc', 'border', 'iran', 'dubai', 'out@account.com',
        ];
        if (in_array($email, $expenseEmails, true)) {
            return 'expense';
        }

        if (str_starts_with($email, 'supplier-')) {
            return 'supplier';
        }

        if (str_starts_with($email, 'online-contracts')) {
            return 'contracts';
        }

        if (in_array($email, $systemEmails, true)) {
            return 'system';
        }

        return 'system';
    }
};
