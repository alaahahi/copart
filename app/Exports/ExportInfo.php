<?php

namespace App\Exports;

use App\Models\Car; // Adjust the namespace as per your application structure
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;


class ExportInfo implements FromCollection, WithHeadings
{
    protected $clientId;
    protected $showComplatedCars;
    
    public function __construct($clientId,$showComplatedCars)
    {
        $this->clientId = $clientId;
        $this->showComplatedCars=$showComplatedCars;
    }


    public function collection()
    {
        
        // Fetch data from the database using the Car model
        if($this->showComplatedCars){
            $cars = Car::where('client_id', $this->clientId)->whereIn('results', [0,1])->
            select([
                'car_type',
                'vin',
                'car_number',
                'car_color',
                'dinar_s',
                'dolar_price_s',
                'shipping_dolar_s',
                'coc_dolar_s',
                'checkout_s',
                'expenses_s',
                'total_s',
                'discount',
                'paid',
                'date',
                'note',
                'results'
            ])->get();
        }else{
            $cars = Car::where('client_id', $this->clientId)->
            select([
                'car_type',
                'vin',
                'car_number',
                'car_color',
                'dinar_s',
                'dolar_price_s',
                'shipping_dolar_s',
                'coc_dolar_s',
                'checkout_s',
                'expenses_s',
                'total_s',
                'discount',
                'paid',
                'date',
                'note',
                'results'
            ])->get();
        }
     

        // Transform the fetched data into a collection
        $collection = new Collection();
        foreach ($cars as $car) {
            $car['results'] = $this->formatResults($car['results']);
            $car['resultsdinar'] =(int)(($car['dinar_s'])/(($car['dolar_price_s'])/100));
            $collection->push($car->toArray());
        }

        return $collection;
    }

    public function headings(): array
    {
        return [
            'السيارة',
            'الشانصى',
            'رقم كاتي',
            'اللون',
            'كمرك بالدينار',
            'سعر الصرف',
            'الشحن',
            'الشهادة',
            'الخروجية',
            'المصاريف',
            'المجموع',
            'الخصم',
            'المدفوع',
            'تاريخ الدخول',
            'ملاحظة',
            'الحالة',
            'كمرك بالدولار'
           
        ];
    }

    private function formatResults($value)
    {
        switch ($value) {
            case 0:
                return 'غير مدفوع';
            case 1:
            case 2:
                return 'مدفوع';
            default:
                return '';
        }
    }
}