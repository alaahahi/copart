<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'show_in_dashboard')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_in_dashboard')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'show_in_dashboard')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('show_in_dashboard');
        });
    }
};
