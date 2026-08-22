<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تعويض ضرر — operational deduction on sales remaining (like discount):
 * remaining = total_s − paid − discount − damage_compensation
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('car')) {
            return;
        }

        Schema::table('car', function (Blueprint $table) {
            if (! Schema::hasColumn('car', 'damage_compensation')) {
                $table->integer('damage_compensation')->default(0)->after('discount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('car')) {
            return;
        }

        Schema::table('car', function (Blueprint $table) {
            if (Schema::hasColumn('car', 'damage_compensation')) {
                $table->dropColumn('damage_compensation');
            }
        });
    }
};
