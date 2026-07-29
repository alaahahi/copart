<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: drop wallets table after code no longer depends on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (Schema::hasTable('vaults') && Schema::hasColumn('vaults', 'wallet_id')) {
            if ($driver === 'sqlite') {
                $this->rebuildSqliteVaultsWithoutWalletId();
            } else {
                Schema::table('vaults', function (Blueprint $table) {
                    try {
                        $table->dropForeign(['wallet_id']);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                    $table->dropColumn('wallet_id');
                });
            }
        }

        Schema::dropIfExists('wallets');
    }

    public function down(): void
    {
        if (! Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->bigInteger('balance')->default(0);
                $table->integer('card')->default(0);
                $table->timestamps();
                $table->double('balance_dinar')->nullable()->default(0);
            });
        }

        if (Schema::hasTable('vaults') && ! Schema::hasColumn('vaults', 'wallet_id')) {
            Schema::table('vaults', function (Blueprint $table) {
                $table->unsignedBigInteger('wallet_id')->nullable()->index();
            });
        }
    }

    protected function rebuildSqliteVaultsWithoutWalletId(): void
    {
        Schema::disableForeignKeyConstraints();

        $indexes = DB::select("PRAGMA index_list('vaults')");
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

        $oldCols = Schema::getColumnListing('vaults');
        Schema::rename('vaults', 'vaults_old_drop_wallet');

        Schema::create('vaults', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->index();
            $table->string('name', 255);
            $table->string('code', 64);
            $table->string('type', 32)->default('cash');
            $table->string('currency_default', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_accounting')->default(true);
            $table->unsignedBigInteger('legacy_user_id')->nullable()->index();
            $table->unsignedBigInteger('ledger_account_id')->nullable()->index();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['owner_id', 'code']);
            $table->index(['owner_id', 'type']);
            $table->index(['owner_id', 'is_active']);
        });

        $newCols = Schema::getColumnListing('vaults');
        $shared = array_values(array_intersect($oldCols, $newCols));
        $colList = implode(', ', array_map(fn ($c) => '"'.$c.'"', $shared));
        DB::statement("INSERT INTO vaults ({$colList}) SELECT {$colList} FROM vaults_old_drop_wallet");

        Schema::drop('vaults_old_drop_wallet');
        Schema::enableForeignKeyConstraints();
    }
};
