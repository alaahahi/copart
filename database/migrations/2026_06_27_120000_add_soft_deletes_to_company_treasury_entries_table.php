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
            if (!Schema::hasColumn('company_treasury_entries', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('company_treasury_entries')) {
            return;
        }

        Schema::table('company_treasury_entries', function (Blueprint $table) {
            if (Schema::hasColumn('company_treasury_entries', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
