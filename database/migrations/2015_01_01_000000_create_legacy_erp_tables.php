<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline ERP schema for fresh installs (SQLite / empty MySQL).
 *
 * Production historically created these tables outside Laravel migrations
 * (phpMyAdmin / dump). Later migrations only ALTER them. This migration
 * creates the pre-2026 base schema when missing so `php artisan migrate`
 * works on an empty database without importing production data.
 *
 * Column set intentionally excludes fields added by later migrations
 * (e.g. car.erbil_*, car.auction_id, transactions.tag, wallets.ledger_synced_at)
 * so those ALTER migrations remain the source of truth on both drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_type')) {
            Schema::create('user_type', function (Blueprint $table) {
                $table->bigInteger('id')->primary();
                $table->string('name');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('owner')) {
            Schema::create('owner', function (Blueprint $table) {
                $table->increments('id');
                $table->string('slug')->nullable();
                $table->string('location')->nullable();
                $table->string('title')->nullable();
                $table->timestamps();
            });
        }

        // Extend Laravel default users with ERP columns (from structure dump).
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'type_id')) {
                    $table->bigInteger('type_id')->nullable();
                }
                if (!Schema::hasColumn('users', 'is_band')) {
                    $table->tinyInteger('is_band')->default(0);
                }
                if (!Schema::hasColumn('users', 'percentage')) {
                    $table->integer('percentage')->default(0)->nullable();
                }
                if (!Schema::hasColumn('users', 'morphed_id')) {
                    $table->unsignedBigInteger('morphed_id')->nullable();
                }
                if (!Schema::hasColumn('users', 'morphed_type')) {
                    $table->string('morphed_type')->nullable();
                }
                if (!Schema::hasColumn('users', 'phone')) {
                    $table->string('phone')->nullable();
                }
                if (!Schema::hasColumn('users', 'created')) {
                    $table->date('created')->nullable();
                }
                if (!Schema::hasColumn('users', 'owner_id')) {
                    $table->integer('owner_id')->nullable()->default(1);
                }
                if (!Schema::hasColumn('users', 'year_date')) {
                    $table->integer('year_date')->nullable()->default(2024);
                }
            });
        }

        if (!Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->bigInteger('balance')->default(0);
                $table->integer('card')->default(0);
                $table->timestamps();
                $table->double('balance_dinar')->nullable()->default(0);

                $table->index('user_id');
            });
        }

        if (!Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('wallet_id');
                $table->string('description');
                $table->bigInteger('amount');
                $table->timestamps();
                $table->string('type')->default('in');
                $table->integer('is_pay')->default(0);
                $table->string('morphed_type')->nullable();
                $table->integer('morphed_id')->nullable();
                $table->string('currency')->default('$');
                $table->integer('user_added')->nullable();
                $table->date('created')->nullable();
                $table->integer('discount')->nullable()->default(0);
                $table->integer('parent_id')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->longText('details')->nullable();

                $table->index('wallet_id');
            });
        }

        if (!Schema::hasTable('transactions_images')) {
            Schema::create('transactions_images', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('transactions_id');
                $table->string('name', 500);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('transfers')) {
            Schema::create('transfers', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('user_id');
                $table->integer('no')->nullable();
                $table->integer('amount')->default(0);
                $table->string('sender_note')->nullable();
                $table->string('currency')->default('$');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
                $table->date('deleted_at')->nullable();
                $table->string('stauts')->nullable();
                $table->integer('sender_id')->nullable();
                $table->integer('receiver_id')->nullable();
                $table->string('receiver_note')->nullable();
                $table->integer('fee')->nullable()->default(0);
            });
        }

        if (!Schema::hasTable('car')) {
            Schema::create('car', function (Blueprint $table) {
                $table->increments('id');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
                $table->integer('user_id')->nullable();
                $table->integer('results')->default(0);
                $table->integer('no');
                $table->integer('client_id')->nullable();
                $table->text('note')->nullable();
                $table->string('car_type')->nullable();
                $table->string('vin')->nullable()->unique();
                $table->integer('car_number')->nullable();
                $table->double('dinar')->nullable()->default(0);
                $table->double('dolar_price')->nullable()->default(0);
                $table->double('dolar_custom')->nullable()->default(0);
                $table->double('checkout')->nullable()->default(0);
                $table->double('shipping_dolar')->nullable()->default(0);
                $table->double('coc_dolar')->nullable()->default(0);
                $table->string('note1')->nullable();
                $table->double('total')->nullable()->default(0);
                $table->double('paid')->nullable()->default(0);
                $table->double('profit')->nullable()->default(0);
                $table->date('date')->nullable();
                $table->string('car_color')->nullable();
                $table->integer('year')->nullable();
                $table->double('expenses')->nullable();
                $table->double('dinar_s')->nullable()->default(0);
                $table->double('dolar_price_s')->nullable()->default(0);
                $table->double('dolar_custom_s')->nullable()->default(0);
                $table->double('checkout_s')->nullable()->default(0);
                $table->double('shipping_dolar_s')->nullable()->default(0);
                $table->double('coc_dolar_s')->nullable()->default(0);
                $table->double('total_s')->nullable()->default(0);
                $table->integer('discount')->default(0);
                $table->double('expenses_s')->nullable()->default(0);
                $table->integer('is_exit')->nullable()->default(0);
                $table->integer('contract_id')->nullable()->default(0);
                $table->integer('owner_id')->nullable();
                $table->integer('year_date')->nullable();
                $table->integer('car_have_expenses')->nullable()->default(0);
            });
        }

        if (!Schema::hasTable('car_expenses')) {
            Schema::create('car_expenses', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('car_id')->nullable();
                $table->string('note')->nullable();
                $table->timestamps();
                $table->integer('amount_dinar')->nullable();
                $table->integer('amount_dollar')->nullable();
                $table->integer('user_id')->nullable();
                $table->integer('reason_id')->nullable();
                $table->date('created')->nullable();
                $table->integer('owner_id')->nullable();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('car_images')) {
            Schema::create('car_images', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('car_id');
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('oauth_clients')) {
            Schema::create('oauth_clients', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('name');
                $table->string('secret', 100)->nullable();
                $table->string('provider')->nullable();
                $table->text('redirect');
                $table->boolean('personal_access_client');
                $table->boolean('password_client');
                $table->boolean('revoked');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('oauth_personal_access_clients')) {
            Schema::create('oauth_personal_access_clients', function (Blueprint $table) {
                $table->id();
                $table->uuid('client_id');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_personal_access_clients');
        Schema::dropIfExists('oauth_clients');
        Schema::dropIfExists('car_images');
        Schema::dropIfExists('car_expenses');
        Schema::dropIfExists('car');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('transactions_images');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('owner');
        Schema::dropIfExists('user_type');
    }
};
