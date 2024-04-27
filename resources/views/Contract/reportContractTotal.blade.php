<!DOCTYPE html>
<html>
<head>
    <title>شركة سلام جلال أيوب</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
    @page {
      size: landscape; /* Set page orientation to landscape */

      /* this affects the margin in the printer settings */
      margin: 15px;
      margin-top: 60px;
    }
    </style>
</head>
@php
    $totalPaid = 0;
    $totalPaidDinar = 0;
    $totalDibt = 0;
    $totalDinarDibt = 0;
    $count=0

@endphp

@foreach ($data as $transaction)
    @php
        $count = $count+1;
        $totalPaid += $transaction->tex_seller_paid + $transaction->tex_buyer_paid;
        $totalPaidDinar += $transaction->tex_seller_dinar_paid + $transaction->tex_buyer_dinar_paid;
        $totalDibt += ($transaction->tex_seller + $transaction->tex_buyer) - ($transaction->tex_seller_paid + $transaction->tex_buyer_paid);
        $totalDinarDibt += ($transaction->tex_seller_dinar + $transaction->tex_buyer_dinar) - ($transaction->tex_seller_dinar_paid + $transaction->tex_buyer_dinar_paid);

    @endphp
@endforeach

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

    
       
    <h5 class="pt-3">  جميع عقود البيع </h5>
    </div>
    <div class="col-4 text-center py-3"> 
        @include('Components.logo')

    </div>
    </div>
    <div class="row p-2 text-center border-top border-bottom" style="font-size: 14px">
    <div class="col"> 
    العدد:
    {{$count}}
    </div>
    <div class="col">

    </div>
    @if($_GET['from'] ??'')
    <div class="col">
    من تاريخ:
    <?= $_GET['from'] ??'' ?>
    </div>
    @endif
    @if($_GET['to'] ??'')
    <div class="col">
        حتى تاريخ:
    <?= $_GET['to'] ??'' ?>
    </div>
    @endif
  </div>
  <div class="row p-2 text-center border-bottom alert-primary "  style="font-size: 14px">
    <div class="col-6"> 
    مجموع المدفوع بالدولار:
    {{$totalPaid}}
    </div>
    @if($totalPaidDinar ??'')
    <div class="col-6">
      مجموع المدفوع بالدينار العراقي:
      {{$totalPaidDinar??0}}
    </div>
    @endif
    <div class="col-6"> 
    مجموع الدين بالدولار:
    {{$totalDibt??0}}
    </div>
    @if($totalDinarDibt??0)
    <div class="col-6">
      مجموع الدين بالدينار العراقي:
      {{$totalDinarDibt??0}}
    </div>
    @endif
  </div>
  <div class="row text-center py-2">
    <table class="table table-sm table-striped table-bordered" style="font-size: 12px">
        <thead>
          <tr>
            <th>رقم</th>
            <th scope="col">البائع</th>
            <th scope="col">المشتري</th>
            <th scope="col">السيارة</th>
            <th scope="col">السعر</th>
            <th scope="col">موديل</th>

            <th scope="col">المدفوع بالدولار</th>
            @if($totalPaidDinar ??'')
            <th scope="col">المدفوع بالدينار</th>
            @endif
            <th scope="col">الدين بالدولار</th>
            @if($totalDinarDibt??0)
            <th scope="col">الدين بالدينار</th>
            @endif
            <th scope="col">ملاحظة ستاف</th>
            <th scope="col">تاريخ</th>

          </tr>
        </thead>
        <tbody>
            @foreach ($data as $key=>$data)
            <tr>
                <td>{{$key+1}}</td>
                <td>{{$data->name_seller}}</td>
                <td>{{$data->name_buyer}}</td>
                <td>{{$data->car_name}}</td>
                <td>{{$data->car_price}}</td>
                <td>{{$data->modal}}</td>

                
                <td>
                  {{ $data->tex_seller_paid + $data->tex_buyer_paid }}
                </td>
                @if($totalPaidDinar ??'')
                <td>  
                  {{ $data->tex_seller_dinar_paid + $data->tex_buyer_dinar_paid }}
                </td>
                @endif
                <td>{{  ($data->tex_seller + $data->tex_buyer)- ($data->tex_seller_paid + $data->tex_buyer_paid) }}</td>
                @if($totalDinarDibt??0)
                <td>{{  ($data->tex_seller_dinar + $data->tex_buyer_dinar)  -  ($data->tex_seller_dinar_paid + $data->tex_buyer_dinar_paid) }}</td>
                @endif
                <td>{{ $data->system_note }}</td>
                <td>{{ $data->created }}</td>

            </tr>
            @endforeach
        </tbody>
      </table>  
  </div>
</div>


<script>
    $(document).ready(function() {
        // Function to open the print dialog
        function openPrintDialog() {
             window.print();
        }
    
        // Call the function to open the print dialog
        openPrintDialog();
    });
    </script>

</body>
</html>
