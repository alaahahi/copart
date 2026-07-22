<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_treasury_entries')) {
            return;
        }

        Schema::table('company_treasury_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('company_treasury_entries', 'is_settled')) {
                $table->boolean('is_settled')->default(false)->after('balance');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('company_treasury_entries')) {
            return;
        }

        Schema::table('company_treasury_entries', function (Blueprint $table) {
            if (Schema::hasColumn('company_treasury_entries', 'is_settled')) {
                $table->dropColumn('is_settled');
            }
        });
    }
};
