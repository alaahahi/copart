<?php

namespace App\Exports;

use App\Models\Car;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Client / trader account statement (كشف حساب الزبون) Excel export.
 * Column labels and order match Clients/Show cars table (Arabic $t keys).
 */
class ExportInfo implements FromCollection, WithHeadings
{
    protected $clientId;

    protected $showComplatedCars;

    protected $from;

    protected $to;

    public function __construct($clientId = null, $showComplatedCars = 0, $from = null, $to = null)
    {
        $this->clientId = $clientId;
        $this->showComplatedCars = $showComplatedCars;
        $this->from = $from;
        $this->to = $to;
    }

    public function collection()
    {
        $collection = new Collection();

        if (!$this->clientId) {
            return $collection;
        }

        $query = Car::where('client_id', $this->clientId)->select([
            'car_type',
            'year',
            'car_color',
            'vin',
            'car_number',
            'note',
            'shipping_dolar_s',
            'dinar_s',
            'coc_dolar_s',
            'checkout_s',
            'expenses_s',
            'erbil_clearance_s',
            'erbil_transfer_s',
            'erbil_border_repair_s',
            'erbil_customs_s',
            'commission_s',
            'total_s',
            'paid',
            'discount',
            'date',
            'results',
        ]);

        // Match Clients/Show filter_completed_cars: hide closed (results=2) cars.
        if ($this->showComplatedCars) {
            $query->where('results', '!=', 2);
        }

        if ($this->from && $this->to) {
            $query->whereBetween('date', [$this->from, $this->to]);
        }

        $seqNo = 1;
        foreach ($query->orderBy('date')->orderBy('id')->get() as $car) {
            $attrs = $car->getAttributes();
            $total = (float) ($car->total_s ?? 0);
            $paid = (float) ($car->paid ?? 0);
            $discount = (float) ($car->discount ?? 0);
            // Same as UI carRemaining(): total_s − paid − discount
            $remaining = $total - $paid - $discount;

            $collection->push([
                $seqNo,
                $car->car_type ?? '',
                $car->year ?? '',
                $car->car_color ?? '',
                $car->vin ?? '',
                $car->car_number ?? '',
                $car->note ?? '',
                (float) ($car->shipping_dolar_s ?? 0),
                (float) ($car->dinar_s ?? 0),
                (float) ($car->coc_dolar_s ?? 0),
                (float) ($car->checkout_s ?? 0),
                (float) Car::erbilTransferSubtotal($attrs, true),
                (float) ($car->commission_s ?? 0),
                $total,
                $paid,
                $remaining,
                $car->date ?? '',
            ]);

            $seqNo++;
        }

        return $collection;
    }

    public function headings(): array
    {
        // Labels aligned with resources/js/lang/ar.json keys used on Clients/Show.
        return [
            'رقم',                  // no
            'السيارة',              // car_type
            'السنة',                // year
            'اللون',                // color
            'رقم الشاصي',           // vin
            'رقم اللوت',            // car_number
            'ملاحظة',               // note
            'سعر السيارة أمريكا',   // car_price_usa
            'نقل أمريكا',           // transfer_usa
            'ريكفري',               // recovery
            'مصاريف تصليح',         // repair_expenses
            'نقل أربيل',            // transfer_erbil
            'مصاريف أربيل',         // erbil_expenses
            'الإجمالي',             // total
            'المدفوع',              // paid
            'المتبقي',              // remaining (total_s − paid − discount)
            'بتاريخ',               // date
        ];
    }
}
