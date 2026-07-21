<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_config')) {
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
                $table->timestamps();
            });

            return;
        }

        Schema::table('system_config', function (Blueprint $table) {
            if (!Schema::hasColumn('system_config', 'receipt_template')) {
                $table->string('receipt_template', 32)->default('default');
            }
            if (!Schema::hasColumn('system_config', 'receipt_phone')) {
                $table->string('receipt_phone', 255)->nullable();
            }
            if (!Schema::hasColumn('system_config', 'receipt_address')) {
                $table->string('receipt_address', 500)->nullable();
            }
            if (!Schema::hasColumn('system_config', 'receipt_website')) {
                $table->string('receipt_website', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_config')) {
            return;
        }

        Schema::table('system_config', function (Blueprint $table) {
            foreach (['receipt_template', 'receipt_phone', 'receipt_address', 'receipt_website'] as $col) {
                if (Schema::hasColumn('system_config', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
