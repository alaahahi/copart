<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 prep: link transactions to vaults; make wallet_id nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transactions') && ! Schema::hasColumn('transactions', 'vault_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('vault_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'wallet_id')) {
            $this->makeWalletIdNullable();
        }

        // Backfill vault_id from vaults.wallet_id match
        if (
            Schema::hasTable('transactions')
            && Schema::hasTable('vaults')
            && Schema::hasColumn('transactions', 'vault_id')
            && Schema::hasColumn('vaults', 'wallet_id')
        ) {
            $rows = DB::table('vaults')
                ->whereNotNull('wallet_id')
                ->whereNull('deleted_at')
                ->get(['id', 'wallet_id']);

            foreach ($rows as $vault) {
                DB::table('transactions')
                    ->where('wallet_id', $vault->wallet_id)
                    ->whereNull('vault_id')
                    ->update(['vault_id' => $vault->id]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'vault_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('vault_id');
            });
        }
    }

    protected function driver(): string
    {
        return Schema::getConnection()->getDriverName();
    }

    protected function makeWalletIdNullable(): void
    {
        $driver = $this->driver();

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTransactionsNullableWallet();

            return;
        }

        try {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('wallet_id')->nullable()->change();
            });
        } catch (\Throwable $e) {
            DB::statement('ALTER TABLE transactions MODIFY wallet_id BIGINT UNSIGNED NULL');
        }
    }

    /**
     * SQLite cannot ALTER COLUMN nullability — rebuild the table.
     * Index names are DB-global on SQLite, so drop indexes on the old table first.
     */
    protected function rebuildSqliteTransactionsNullableWallet(): void
    {
        $info = DB::select('PRAGMA table_info(transactions)');
        foreach ($info as $col) {
            if ($col->name === 'wallet_id' && (int) $col->notnull === 0) {
                return;
            }
        }

        Schema::disableForeignKeyConstraints();

        // Drop indexes that would collide on recreate (SQLite index names are global).
        $indexes = DB::select("PRAGMA index_list('transactions')");
        foreach ($indexes as $idx) {
            $name = (string) ($idx->name ?? '');
            if ($name !== '' && ! str_starts_with($name, 'sqlite_autoindex_')) {
                try {
                    DB::statement('DROP INDEX IF EXISTS "'.$name.'"');
                } catch (\Throwable $e) {
                    // continue
                }
            }
        }

        $oldCols = Schema::getColumnListing('transactions');

        Schema::rename('transactions', 'transactions_old_wallet_mig');

        Schema::create('transactions', function (Blueprint $table) use ($oldCols) {
            $table->id();
            $table->unsignedBigInteger('wallet_id')->nullable();
            if (in_array('vault_id', $oldCols, true)) {
                $table->unsignedBigInteger('vault_id')->nullable();
            }
            $table->string('description');
            $table->bigInteger('amount');
            $table->timestamps();
            $table->string('type')->default('in');
            $table->integer('is_pay')->default(0);
            $table->string('morphed_type')->nullable();
            $table->integer('morphed_id')->nullable();
            $table->string('currency')->default('$');
            $table->integer('user_added')->nullable();
            $table->date('created')->nullable();
            $table->integer('discount')->nullable()->default(0);
            $table->integer('parent_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->longText('details')->nullable();
            if (in_array('tag', $oldCols, true)) {
                $table->string('tag')->nullable();
            }
            if (in_array('journal_entry_id', $oldCols, true)) {
                $table->unsignedBigInteger('journal_entry_id')->nullable();
            }
        });

        $newCols = Schema::getColumnListing('transactions');
        $shared = array_values(array_intersect($oldCols, $newCols));
        $colList = implode(', ', array_map(fn ($c) => '"'.$c.'"', $shared));
        DB::statement("INSERT INTO transactions ({$colList}) SELECT {$colList} FROM transactions_old_wallet_mig");

        Schema::drop('transactions_old_wallet_mig');

        // Recreate useful indexes with fresh names
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('wallet_id', 'transactions_wallet_id_idx');
            if (Schema::hasColumn('transactions', 'vault_id')) {
                $table->index('vault_id', 'transactions_vault_id_idx');
            }
            if (Schema::hasColumn('transactions', 'journal_entry_id')) {
                $table->index('journal_entry_id', 'transactions_journal_entry_id_idx');
            }
        });

        Schema::enableForeignKeyConstraints();
    }
};
