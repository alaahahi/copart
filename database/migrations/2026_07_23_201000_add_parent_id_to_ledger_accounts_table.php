<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ledger_accounts')) {
            return;
        }

        if (! Schema::hasColumn('ledger_accounts', 'parent_id')) {
            Schema::table('ledger_accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('is_active');
                $table->index(['owner_id', 'parent_id']);
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('ledger_accounts')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ledger_accounts') || ! Schema::hasColumn('ledger_accounts', 'parent_id')) {
            return;
        }

        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['owner_id', 'parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
