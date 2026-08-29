<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net for legacy/partial databases where the earlier system_config
 * migrations never ran (aborted migration batch), leaving the table absent
 * while later "add column" migrations skipped themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_config')) {
            Schema::create('system_config', function (Blueprint $table) {
                $table->id();
                $table->string('first_title_ar')->nullable();
                $table->string('first_title_kr')->nullable();
                $table->string('second_title_ar')->nullable();
                $table->string('second_title_kr')->nullable();
                $table->string('third_title_ar')->nullable();
                $table->string('third_title_kr')->nullable();
                $table->string('receipt_template', 32)->default('default');
                $table->string('receipt_phone', 255)->nullable();
                $table->string('receipt_address', 500)->nullable();
                $table->string('receipt_website', 255)->nullable();
                $table->string('receipt_logo_left_1', 500)->nullable();
                $table->string('receipt_logo_left_2', 500)->nullable();
                $table->string('receipt_logo_left_3', 500)->nullable();
                $table->string('receipt_logo_haulf', 500)->nullable();
                $table->string('receipt_logo_main', 500)->nullable();
                $table->string('app_logo', 500)->nullable();
                $table->string('app_cover', 500)->nullable();
                $table->boolean('wa_enabled')->default(false);
                $table->string('wa_base_host', 255)->default('https://wa.intellij-app.com');
                $table->string('wa_tenant', 100)->nullable();
                $table->string('wa_source', 32)->default('sales');
                $table->string('wa_created_by', 100)->default('copart-erp');
                $table->boolean('wa_notify_debt')->default(false);
                $table->boolean('wa_notify_car_created')->default(false);
                $table->boolean('wa_notify_payment')->default(false);
                $table->unsignedBigInteger('default_receipts_vault_id')->nullable()->index();
                $table->unsignedBigInteger('default_purchases_vault_id')->nullable()->index();
                $table->timestamps();
            });

            return;
        }

        Schema::table('system_config', function (Blueprint $table) {
            foreach ([
                'first_title_ar' => fn () => $table->string('first_title_ar')->nullable(),
                'first_title_kr' => fn () => $table->string('first_title_kr')->nullable(),
                'second_title_ar' => fn () => $table->string('second_title_ar')->nullable(),
                'second_title_kr' => fn () => $table->string('second_title_kr')->nullable(),
                'third_title_ar' => fn () => $table->string('third_title_ar')->nullable(),
                'third_title_kr' => fn () => $table->string('third_title_kr')->nullable(),
                'receipt_template' => fn () => $table->string('receipt_template', 32)->default('default'),
                'receipt_phone' => fn () => $table->string('receipt_phone', 255)->nullable(),
                'receipt_address' => fn () => $table->string('receipt_address', 500)->nullable(),
                'receipt_website' => fn () => $table->string('receipt_website', 255)->nullable(),
                'receipt_logo_left_1' => fn () => $table->string('receipt_logo_left_1', 500)->nullable(),
                'receipt_logo_left_2' => fn () => $table->string('receipt_logo_left_2', 500)->nullable(),
                'receipt_logo_left_3' => fn () => $table->string('receipt_logo_left_3', 500)->nullable(),
                'receipt_logo_haulf' => fn () => $table->string('receipt_logo_haulf', 500)->nullable(),
                'receipt_logo_main' => fn () => $table->string('receipt_logo_main', 500)->nullable(),
                'app_logo' => fn () => $table->string('app_logo', 500)->nullable(),
                'app_cover' => fn () => $table->string('app_cover', 500)->nullable(),
                'wa_enabled' => fn () => $table->boolean('wa_enabled')->default(false),
                'wa_base_host' => fn () => $table->string('wa_base_host', 255)->default('https://wa.intellij-app.com'),
                'wa_tenant' => fn () => $table->string('wa_tenant', 100)->nullable(),
                'wa_source' => fn () => $table->string('wa_source', 32)->default('sales'),
                'wa_created_by' => fn () => $table->string('wa_created_by', 100)->default('copart-erp'),
                'wa_notify_debt' => fn () => $table->boolean('wa_notify_debt')->default(false),
                'wa_notify_car_created' => fn () => $table->boolean('wa_notify_car_created')->default(false),
                'wa_notify_payment' => fn () => $table->boolean('wa_notify_payment')->default(false),
                'default_receipts_vault_id' => fn () => $table->unsignedBigInteger('default_receipts_vault_id')->nullable(),
                'default_purchases_vault_id' => fn () => $table->unsignedBigInteger('default_purchases_vault_id')->nullable(),
            ] as $column => $definition) {
                if (! Schema::hasColumn('system_config', $column)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        // Non-destructive: the table may predate migrations (legacy SQL dump).
    }
};
