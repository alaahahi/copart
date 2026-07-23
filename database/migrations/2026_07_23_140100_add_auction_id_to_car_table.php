<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional `auction_id` (FK -> auctions.id) to `car` so a purchased
 * car can record which auction house it came from (المزاد). Nullable and
 * ON DELETE SET NULL so existing cars are never broken and deleting an
 * auction from the settings list never destroys car history.
 *
 * Guarded with hasTable/hasColumn checks because this project manages the
 * `car` table's base schema outside of migrations (see
 * add_soft_deletes_to_car_table.php for the same convention).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('car')
            && Schema::hasTable('auctions')
            && !Schema::hasColumn('car', 'auction_id')
        ) {
            Schema::table('car', function (Blueprint $table) {
                $table->unsignedBigInteger('auction_id')->nullable()->after('car_number');
                $table->index('auction_id');
                $table->foreign('auction_id')->references('id')->on('auctions')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('car') && Schema::hasColumn('car', 'auction_id')) {
            Schema::table('car', function (Blueprint $table) {
                $table->dropForeign(['auction_id']);
                $table->dropColumn('auction_id');
            });
        }
    }
};
