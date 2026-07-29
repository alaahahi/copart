<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-deleted cars must not occupy the global UNIQUE on car.vin.
 *
 * Dual-driver strategy:
 * - Drop the legacy UNIQUE(vin) index (blocks re-add after DelCar / reset).
 * - SQLite: partial unique index WHERE deleted_at IS NULL (active VINs only).
 * - MySQL / MariaDB: no DB-level partial unique — app validation
 *   (StoreCarRequest) + CarService::releaseSoftDeletedVin on create.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('car')) {
            return;
        }

        $this->dropLegacyVinUnique();

        if ($this->driver() === 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS car_vin_active_unique ON car (vin) WHERE deleted_at IS NULL'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('car')) {
            return;
        }

        if ($this->driver() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS car_vin_active_unique');
        }

        // Restore global unique only when no duplicate vins remain (including soft-deleted).
        $dupes = DB::table('car')
            ->whereNotNull('vin')
            ->where('vin', '!=', '')
            ->groupBy('vin')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($dupes > 0) {
            return;
        }

        try {
            Schema::table('car', function (Blueprint $table) {
                $table->unique('vin');
            });
        } catch (\Throwable $e) {
            // Index may already exist.
        }
    }

    protected function driver(): string
    {
        return Schema::getConnection()->getDriverName();
    }

    protected function dropLegacyVinUnique(): void
    {
        $driver = $this->driver();

        if ($driver === 'sqlite') {
            // SQLite index names are DB-global; legacy Laravel name is car_vin_unique.
            foreach ($this->sqliteVinUniqueIndexNames() as $name) {
                DB::statement('DROP INDEX IF EXISTS "'.$name.'"');
            }

            return;
        }

        try {
            Schema::table('car', function (Blueprint $table) {
                $table->dropUnique(['vin']);
            });
        } catch (\Throwable $e) {
            // Try common explicit names (MySQL / MariaDB).
            foreach (['car_vin_unique', 'vin'] as $name) {
                try {
                    DB::statement('ALTER TABLE `car` DROP INDEX `'.$name.'`');
                } catch (\Throwable $inner) {
                    // continue
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    protected function sqliteVinUniqueIndexNames(): array
    {
        $names = [];
        $indexes = DB::select("PRAGMA index_list('car')");

        foreach ($indexes as $idx) {
            $name = (string) ($idx->name ?? '');
            $unique = (int) ($idx->unique ?? 0) === 1;
            if ($name === '' || ! $unique || str_starts_with($name, 'sqlite_autoindex_')) {
                continue;
            }

            $cols = DB::select('PRAGMA index_info("'.$name.'")');
            $colNames = array_map(fn ($c) => (string) ($c->name ?? ''), $cols);
            if ($colNames === ['vin']) {
                $names[] = $name;
            }
        }

        // Always try the conventional Laravel name.
        if (! in_array('car_vin_unique', $names, true)) {
            $names[] = 'car_vin_unique';
        }

        return $names;
    }
};
