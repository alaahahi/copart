<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_treasury_entries')) {
            return;
        }

        Schema::create('company_treasury_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('entry_date')->index();
            $table->string('description', 500)->nullable();
            $table->string('currency', 10)->default('$');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->decimal('balance', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['owner_id', 'currency', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_treasury_entries');
    }
};
