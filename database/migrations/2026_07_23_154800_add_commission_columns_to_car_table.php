<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure مصاريف اربيل columns exist on `car`.
 *
 * The Car model and sales/purchase forms already use `commission` /
 * `commission_s`, but production never received a migration for them —
 * updates fail with: Unknown column 'commission_s' in 'SET'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('car')) {
            return;
        }

        Schema::table('car', function (Blueprint $table) {
            if (!Schema::hasColumn('car', 'commission')) {
                if (Schema::hasColumn('car', 'expenses')) {
                    $table->integer('commission')->default(0)->after('expenses');
                } else {
                    $table->integer('commission')->default(0);
                }
            }

            if (!Schema::hasColumn('car', 'commission_s')) {
                if (Schema::hasColumn('car', 'expenses_s')) {
                    $table->integer('commission_s')->default(0)->after('expenses_s');
                } else {
                    $table->integer('commission_s')->default(0);
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('car')) {
            return;
        }

        Schema::table('car', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('car', 'commission')) {
                $drop[] = 'commission';
            }
            if (Schema::hasColumn('car', 'commission_s')) {
                $drop[] = 'commission_s';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
