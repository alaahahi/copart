<?php

namespace App\Console\Commands;

use App\Services\LedgerService;
use Illuminate\Console\Command;

/**
 * Historical «تعديل مصاريف» posts credited full sales to 4100.
 * Moves excess (sales − profit) onto مشتريات سيارات — AR/cash untouched.
 */
class RepairCarSaleRevenueCommand extends Command
{
    protected $signature = 'ledger:repair-car-sale-revenue
                            {--owner= : Limit to one owner_id}
                            {--dry-run : Preview without posting (default)}
                            {--execute : Post correcting journals}';

    protected $description = 'Reclassify overstated shipping revenue (4100) from car sales pricing to car-purchases expense recovery';

    public function handle(LedgerService $ledger): int
    {
        $owner = $this->option('owner');
        $ownerId = $owner !== null && $owner !== '' ? (int) $owner : null;
        $dryRun = ! $this->option('execute');

        if ($dryRun) {
            $this->warn('Dry-run mode (pass --execute to post). AR and cash are never touched.');
        }

        $result = $ledger->repairOverstatedCarSaleRevenue($ownerId, $dryRun);

        $this->table(
            ['car_id', 'vin', 'posted_revenue', 'expected_profit', 'excess', 'moved'],
            collect($result['details'])->map(fn ($d) => [
                $d['car_id'],
                $d['vin'],
                $d['posted_revenue'],
                $d['expected_profit'],
                $d['excess'],
                $d['moved'],
            ])->all()
        );

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $this->info(($dryRun ? 'Would repair' : 'Repaired').": {$result['repaired']} | skipped: {$result['skipped']}");

        return self::SUCCESS;
    }
}
