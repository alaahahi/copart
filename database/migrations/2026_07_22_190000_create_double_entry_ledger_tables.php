<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ledger_accounts')) {
            Schema::create('ledger_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_id')->index();
                $table->string('code', 32);
                $table->string('name', 255);
                $table->string('name_ar', 255)->nullable();
                $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
                $table->string('currency', 10)->nullable()->comment('null = multi-currency account');
                $table->string('party_type', 64)->nullable();
                $table->unsignedBigInteger('party_id')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['owner_id', 'code']);
                $table->index(['owner_id', 'type']);
                $table->index(['party_type', 'party_id']);
            });
        }

        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_id')->index();
                $table->string('voucher_no', 64)->index();
                $table->date('entry_date')->index();
                $table->string('memo', 1000)->nullable();
                $table->string('source', 64)->nullable()->comment('wallet, treasury, opening, manual');
                $table->string('reference_type', 128)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('currency', 10)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['owner_id', 'entry_date']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        if (!Schema::hasTable('journal_lines')) {
            Schema::create('journal_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('journal_entry_id')->index();
                $table->unsignedBigInteger('ledger_account_id')->index();
                $table->decimal('debit', 18, 2)->default(0);
                $table->decimal('credit', 18, 2)->default(0);
                $table->string('currency', 10)->default('$');
                $table->string('memo', 500)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('journal_entry_id')
                    ->references('id')
                    ->on('journal_entries')
                    ->cascadeOnDelete();

                $table->foreign('ledger_account_id')
                    ->references('id')
                    ->on('ledger_accounts')
                    ->restrictOnDelete();

                $table->index(['ledger_account_id', 'currency']);
            });
        }

        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'journal_entry_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('company_treasury_entries') && !Schema::hasColumn('company_treasury_entries', 'journal_entry_id')) {
            Schema::table('company_treasury_entries', function (Blueprint $table) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('wallets') && !Schema::hasColumn('wallets', 'ledger_synced_at')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->timestamp('ledger_synced_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallets') && Schema::hasColumn('wallets', 'ledger_synced_at')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->dropColumn('ledger_synced_at');
            });
        }

        if (Schema::hasTable('company_treasury_entries') && Schema::hasColumn('company_treasury_entries', 'journal_entry_id')) {
            Schema::table('company_treasury_entries', function (Blueprint $table) {
                $table->dropColumn('journal_entry_id');
            });
        }

        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'journal_entry_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('journal_entry_id');
            });
        }

        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('ledger_accounts');
    }
};
