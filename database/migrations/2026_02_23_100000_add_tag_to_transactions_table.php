<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactions') || Schema::hasColumn('transactions', 'tag')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('tag', 255)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('transactions') || !Schema::hasColumn('transactions', 'tag')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('tag');
        });
    }
};
