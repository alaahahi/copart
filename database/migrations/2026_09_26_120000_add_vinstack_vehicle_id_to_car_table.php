<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car', function (Blueprint $table) {
            if (! Schema::hasColumn('car', 'vinstack_vehicle_id')) {
                $table->unsignedBigInteger('vinstack_vehicle_id')->nullable()->after('vin')->index();
            }
            if (! Schema::hasColumn('car', 'image')) {
                $table->text('image')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('car', function (Blueprint $table) {
            if (Schema::hasColumn('car', 'vinstack_vehicle_id')) {
                $table->dropColumn('vinstack_vehicle_id');
            }
        });
    }
};
