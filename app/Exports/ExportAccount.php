<?php

namespace App\Exports;

use App\Models\Transactions;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;


class ExportAccount implements FromCollection, WithHeadings
{
    protected $from;
    protected $to;

    public function __construct($from,$to,$wallet_id)
    {
        $this->from = $from;
        $this->to=$to;
        $this->wallet_id=$wallet_id;
    }


    public function collection()
    {
        // Initialize an empty collection
        $collection = new Collection();
    
        // Fetch data from the database using the Transactions model
        if ($this->from && $this->to) {
            $transactions = Transactions::with('morphed')
                ->whereBetween('created', [$this->from, $this->to])
                ->where('wallet_id', $this->wallet_id)
                ->get();
        } else {
            $transactions = Transactions::with('morphed')
                ->where('wallet_id', $this->wallet_id)
                ->get();
        }
    
        // Initialize a sequence number
        $seqNo = 1;
    
        foreach ($transactions as $transaction) {
            $transactionData = [
                'seqNo' => $seqNo,
                'name' => $transaction->morphed->name ?? '',
                'amount' => $transaction->amount. ' ' .$transaction->currency,
                'description' => $transaction->description,
                'created' => $transaction->created,

            ];
    
            $collection->push($transactionData);
            $seqNo++; // Increment the sequence number
        }
    
        return $collection;
    }

    public function headings(): array
    {
        return [
            'تسلسل',
            'حساب',
            'المبلغ',
            'الوصف',
            'تاريخ',

        ];
    }

}