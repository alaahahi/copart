<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'has_wallet_tags')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_wallet_tags')->default(0);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'has_wallet_tags')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_wallet_tags');
        });
    }
};
