<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car', function (Blueprint $table) {
            $table->integer('erbil_clearance')->default(0)->after('expenses');
            $table->integer('erbil_transfer')->default(0)->after('erbil_clearance');
            $table->integer('erbil_border_repair')->default(0)->after('erbil_transfer');
            $table->integer('erbil_customs')->default(0)->after('erbil_border_repair');

            $table->integer('erbil_clearance_s')->default(0)->after('expenses_s');
            $table->integer('erbil_transfer_s')->default(0)->after('erbil_clearance_s');
            $table->integer('erbil_border_repair_s')->default(0)->after('erbil_transfer_s');
            $table->integer('erbil_customs_s')->default(0)->after('erbil_border_repair_s');
        });
    }

    public function down(): void
    {
        Schema::table('car', function (Blueprint $table) {
            $table->dropColumn([
                'erbil_clearance',
                'erbil_transfer',
                'erbil_border_repair',
                'erbil_customs',
                'erbil_clearance_s',
                'erbil_transfer_s',
                'erbil_border_repair_s',
                'erbil_customs_s',
            ]);
        });
    }
};
