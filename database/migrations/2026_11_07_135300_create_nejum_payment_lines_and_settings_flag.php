<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_config') && ! Schema::hasColumn('system_config', 'nejum_payments_enabled')) {
            Schema::table('system_config', function (Blueprint $table) {
                $table->boolean('nejum_payments_enabled')->default(false);
            });
        }

        if (Schema::hasTable('nejum_payment_lines')) {
            return;
        }

        Schema::create('nejum_payment_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->index();
            $table->string('line_type', 20)->default('car')->index(); // car | shipping
            $table->unsignedBigInteger('car_id')->nullable()->index();
            $table->date('pr_date')->nullable()->index();
            $table->string('car_info')->nullable();
            $table->string('vin', 64)->nullable()->index();
            $table->string('lot', 64)->nullable()->index();
            $table->string('note')->nullable();

            $table->decimal('amount', 14, 4)->default(0);
            $table->decimal('for_nejum', 14, 4)->default(0);

            // Nejum block
            $table->decimal('nejum_shipping', 14, 4)->default(0);
            $table->decimal('nejum_payments', 14, 4)->default(0);
            $table->date('nejum_payment_date')->nullable();
            $table->string('nejum_note')->nullable();

            // Kaml block
            $table->decimal('transaction_fee', 14, 4)->default(0);
            $table->decimal('commission_3mula', 14, 4)->default(0);
            $table->decimal('kaml_must_pay', 14, 4)->default(0);
            $table->decimal('kaml_payment', 14, 4)->default(0);
            $table->date('kaml_payment_date')->nullable();

            // Customer block
            $table->decimal('us_fee', 14, 4)->default(0);
            $table->decimal('customer_commission_3mula', 14, 4)->default(0);
            $table->decimal('customer_must_pay', 14, 4)->default(0);
            $table->string('customer_name')->nullable();
            $table->date('customer_payment_date')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nejum_payment_lines');

        if (Schema::hasTable('system_config') && Schema::hasColumn('system_config', 'nejum_payments_enabled')) {
            Schema::table('system_config', function (Blueprint $table) {
                $table->dropColumn('nejum_payments_enabled');
            });
        }
    }
};
