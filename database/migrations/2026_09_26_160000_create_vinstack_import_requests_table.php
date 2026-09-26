<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinstack_import_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->index();
            $table->string('vin', 64)->index();
            $table->unsignedBigInteger('vinstack_vehicle_id')->nullable()->index();
            $table->string('status', 32)->default('pending')->index(); // pending|approved|rejected
            $table->json('payload');
            $table->string('dealer_phone', 40)->nullable();
            $table->string('dealer_name')->nullable();
            $table->string('dealer_company')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
            $table->unsignedBigInteger('car_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vinstack_import_requests');
    }
};
