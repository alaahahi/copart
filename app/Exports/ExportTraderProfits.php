<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportTraderProfits implements FromCollection, WithHeadings
{
    public function __construct(protected array $rows)
    {
    }

    public function collection(): Collection
    {
        $collection = new Collection();

        foreach ($this->rows as $row) {
            $collection->push([
                $row['trader'] ?? '',
                $row['cars_count'] ?? 0,
                $row['sales'] ?? 0,
                $row['cost'] ?? 0,
                $row['profit'] ?? 0,
                $row['margin_pct'] ?? 0,
                $row['paid'] ?? 0,
                $row['remaining'] ?? 0,
                $row['ledger_balance'] ?? 0,
            ]);
        }

        return $collection;
    }

    public function headings(): array
    {
        return [
            'التاجر',
            'عدد السيارات',
            'المبيعات',
            'التكلفة',
            'الربح',
            'الهامش %',
            'المدفوع',
            'المتبقي',
            'رصيد الذمة',
        ];
    }
}
