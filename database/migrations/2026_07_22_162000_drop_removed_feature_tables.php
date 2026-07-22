<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('exit_car');
        Schema::dropIfExists('transactions_contract');
        Schema::dropIfExists('car_contract');
        Schema::dropIfExists('contract');
        Schema::dropIfExists('contract_img');
    }

    public function down(): void
    {
        // Intentionally empty — these features were removed.
    }
};
