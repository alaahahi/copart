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
            if (!Schema::hasColumn('company_treasury_entries', 'tag')) {
                $table->string('tag', 255)->nullable();
                $table->index(['owner_id', 'tag']);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('company_treasury_entries')) {
            return;
        }

        Schema::table('company_treasury_entries', function (Blueprint $table) {
            if (Schema::hasColumn('company_treasury_entries', 'tag')) {
                $table->dropIndex(['owner_id', 'tag']);
                $table->dropColumn('tag');
            }
        });
    }
};
