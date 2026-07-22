<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures company_treasury_entries exists with all columns.
 * Safe for DBs that missed earlier treasury migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_treasury_entries')) {
            Schema::create('company_treasury_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->date('entry_date')->index();
                $table->string('description', 500)->nullable();
                $table->string('tag', 255)->nullable();
                $table->string('currency', 10)->default('$');
                $table->decimal('debit', 18, 2)->default(0);
                $table->decimal('credit', 18, 2)->default(0);
                $table->decimal('balance', 18, 2)->default(0);
                $table->boolean('is_settled')->default(false);
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['owner_id', 'currency', 'entry_date']);
                $table->index(['owner_id', 'tag']);
            });

            return;
        }

        Schema::table('company_treasury_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('company_treasury_entries', 'tag')) {
                $table->string('tag', 255)->nullable()->after('description');
            }
            if (!Schema::hasColumn('company_treasury_entries', 'is_settled')) {
                $table->boolean('is_settled')->default(false)->after('balance');
            }
            if (!Schema::hasColumn('company_treasury_entries', 'journal_entry_id')) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index()->after('is_settled');
            }
            if (!Schema::hasColumn('company_treasury_entries', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        // Do not drop — may contain live data on other environments.
    }
};
