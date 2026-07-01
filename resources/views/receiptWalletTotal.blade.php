<!DOCTYPE html>
<html>
<head>
    <title>{{ config('app.company_name') }}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
    @page {
      size: auto;
      margin: 15px;
      margin-top: 60px;
    }
    @media print {
        body {
            margin: 0;
            padding: 0;
        }
        .no-print {
            display: none !important;
        }
    }
    </style>
</head>
<body style="direction: rtl;">
<div class="container-fluid">       
<div class="row">
    <div class="col-4 text-center py-3">
        <h5>
       {{$config['first_title_ar']}}
        </h5>
        <h5>
        {{$config['second_title_ar']}}
        </h5>
    </div>
    <div class="col-4 text-center py-3">
        @php
            // تحويل transactions إلى collection
            $transactions = is_object($data['transactions']) && method_exists($data['transactions'], 'items') 
                ? collect($data['transactions']->items()) 
                : collect($data['transactions']);
            $isAmanah = false;
            if($transactions->count() > 0) {
                $firstItem = $transactions->first();
                $firstType = is_array($firstItem) ? ($firstItem['type'] ?? '') : ($firstItem->type ?? '');
                $isAmanah = in_array($firstType, ['inUserAmanah', 'outUserAmanah']);
            }
        @endphp
        <h5 class="pt-3">
            @if($isAmanah)
                كشف حساب الأمانة
            @else
                كشف حساب الصندوق
            @endif
        </h5>
    </div>
    <div class="col-4 text-center py-3"> 
        @include('Components.logo')
    </div>
</div>
<div class="row p-2 text-center border-top border-bottom" style="font-size: 14px">
    <div class="col"> 
        حساب:
        {{$data['user']->name ?? ''}}
    </div>
    <div class="col">
        موبايل:
        {{$data['user']->phone ?? ''}}
    </div>
    <div class="col">
        تاريخ الطباعة:
        {{date('Y-m-d')}}
    </div>
</div>

@php
    // حساب الإجماليات - استخدام $transactions المعرفة سابقاً
    $totalInDollar = $transactions->filter(function($item) {
        $type = is_array($item) ? ($item['type'] ?? '') : ($item->type ?? '');
        $currency = is_array($item) ? ($item['currency'] ?? '$') : ($item->currency ?? '$');
        return $currency == '$' && in_array($type, ['inUser', 'inUserAmanah']);
    })->sum(function($item) {
        return abs(is_array($item) ? ($item['amount'] ?? 0) : ($item->amount ?? 0));
    });
    
    $totalOutDollar = $transactions->filter(function($item) {
        $type = is_array($item) ? ($item['type'] ?? '') : ($item->type ?? '');
        $currency = is_array($item) ? ($item['currency'] ?? '$') : ($item->currency ?? '$');
        return $currency == '$' && in_array($type, ['outUser', 'outUserAmanah']);
    })->sum(function($item) {
        return abs(is_array($item) ? ($item['amount'] ?? 0) : ($item->amount ?? 0));
    });
    
    $totalInDinar = $transactions->filter(function($item) {
        $type = is_array($item) ? ($item['type'] ?? '') : ($item->type ?? '');
        $currency = is_array($item) ? ($item['currency'] ?? '$') : ($item->currency ?? '$');
        return $currency == 'IQD' && in_array($type, ['inUser', 'inUserAmanah']);
    })->sum(function($item) {
        return abs(is_array($item) ? ($item['amount'] ?? 0) : ($item->amount ?? 0));
    });
    
    $totalOutDinar = $transactions->filter(function($item) {
        $type = is_array($item) ? ($item['type'] ?? '') : ($item->type ?? '');
        $currency = is_array($item) ? ($item['currency'] ?? '$') : ($item->currency ?? '$');
        return $currency == 'IQD' && in_array($type, ['outUser', 'outUserAmanah']);
    })->sum(function($item) {
        return abs(is_array($item) ? ($item['amount'] ?? 0) : ($item->amount ?? 0));
    });
    
    $balanceDollar = $totalInDollar - $totalOutDollar;
    $balanceDinar = $totalInDinar - $totalOutDinar;
@endphp

<div class="row p-2 text-center border-bottom alert-primary" style="font-size: 14px">
    <div class="col-3"> 
        إجمالي الإيداع بالدولار:
        {{number_format($totalInDollar, 2)}}
    </div>
    <div class="col-3">
        إجمالي السحب بالدولار:
        {{number_format($totalOutDollar, 2)}}
    </div>
    <div class="col-3">
        الرصيد بالدولار:
        {{number_format($balanceDollar, 2)}}
    </div>
    <div class="col-3">
        عدد المعاملات:
        {{$transactions->count()}}
    </div>
</div>

@if($totalInDinar > 0 || $totalOutDinar > 0)
<div class="row p-2 text-center border-bottom alert-info" style="font-size: 14px">
    <div class="col-3"> 
        إجمالي الإيداع بالدينار:
        {{number_format($totalInDinar, 2)}}
    </div>
    <div class="col-3">
        إجمالي السحب بالدينار:
        {{number_format($totalOutDinar, 2)}}
    </div>
    <div class="col-3">
        الرصيد بالدينار:
        {{number_format($balanceDinar, 2)}}
    </div>
    <div class="col-3"></div>
</div>
@endif

<div class="row text-center py-2">
    <table class="table table-sm table-striped table-bordered" style="font-size: 12px">
        <thead>
          <tr>
            <th scope="col">رقم الوصل</th>
            <th scope="col">التاريخ</th>
            <th scope="col">النوع</th>
            <th scope="col">الوصف</th>
            <th scope="col">الإيداع</th>
            <th scope="col">السحب</th>
            <th scope="col">العملة</th>
          </tr>
        </thead>
        <tbody>
            @foreach ($transactions as $transaction)
            @php
                $transactionObj = is_array($transaction) ? (object)$transaction : $transaction;
            @endphp
            <tr>
                <td>{{ $transactionObj->id ?? '' }}</td>
                <td>{{ isset($transactionObj->created_at) ? \Carbon\Carbon::parse($transactionObj->created_at)->format('Y-m-d') : (isset($transactionObj->created) ? $transactionObj->created : '') }}</td>
                <td>
                    @php
                        $type = $transactionObj->type ?? '';
                    @endphp
                    @if($type == 'inUser')
                        إيداع صندوق
                    @elseif($type == 'outUser')
                        سحب صندوق
                    @elseif($type == 'inUserAmanah')
                        إيداع أمانة
                    @elseif($type == 'outUserAmanah')
                        سحب أمانة
                    @else
                        {{ $type }}
                    @endif
                </td>
                <td>{{ $transactionObj->description ?? '' }}</td>
                <td>
                    @if(in_array($type, ['inUser', 'inUserAmanah']))
                        {{ number_format(abs($transactionObj->amount ?? 0), 2) }}
                    @else
                        -
                    @endif
                </td>
                <td>
                    @if(in_array($type, ['outUser', 'outUserAmanah']))
                        {{ number_format(abs($transactionObj->amount ?? 0), 2) }}
                    @else
                        -
                    @endif
                </td>
                <td>{{ $transactionObj->currency ?? '$' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>  
</div>
</div>

<script>
    $(document).ready(function() {
        // Function to open the print dialog automatically
        function openPrintDialog() {
             window.print();
        }
    
        // Call the function to open the print dialog immediately
        openPrintDialog();
    });
</script>

</body>
</html>

