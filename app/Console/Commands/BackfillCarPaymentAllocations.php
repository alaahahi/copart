<?php

namespace App\Console\Commands;

use App\Models\Car;
use App\Services\CarPaymentAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillCarPaymentAllocations extends Command
{
    protected $signature = 'cars:backfill-payment-allocations
                            {--chunk=200 : Chunk size}
                            {--dry-run : Report only, do not write}';

    protected $description = 'Backfill car.payment_allocations from car-linked payment txs + legacy paid remainder';

    public function handle(CarPaymentAllocationService $allocations): int
    {
        if (! Schema::hasColumn('car', 'payment_allocations')) {
            $this->error('Column car.payment_allocations missing — run migrations first.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $chunk = max(10, (int) $this->option('chunk'));
        $processed = 0;
        $withPaid = 0;

        Car::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($cars) use ($allocations, $dry, &$processed, &$withPaid) {
                foreach ($cars as $car) {
                    $processed++;
                    if ((float) $car->paid <= 0 && empty($car->payment_allocations)) {
                        continue;
                    }
                    $withPaid++;
                    if ($dry) {
                        continue;
                    }
                    $allocations->backfillCar($car);
                }
            });

        $this->info(($dry ? '[dry-run] ' : '')."Processed {$processed} cars, backfill candidates {$withPaid}.");

        return self::SUCCESS;
    }
}
