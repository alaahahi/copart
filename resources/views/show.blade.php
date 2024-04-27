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
      size: auto; /* auto is the initial value */

      /* this affects the margin in the printer settings */
      margin: 15px;
      margin-top: 60px;
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

    
       
    <h5 class="pt-3">  كشف حساب</h5>
    </div>
    <div class="col-4 text-center py-3"> 
        @include('Components.logo')

    </div>
    </div>
    <div class="row p-2 text-center border-top border-bottom" style="font-size: 14px">
    <div class="col-3"> 
    التاجر:
    {{$clientData['client']->name}}
    </div>
    <div class="col-3">
    موبايل:
    {{$clientData['client']->phone}}
    </div>
    <div class="col-3">
    من تاريخ:
    <?= $_GET['from'] ??'' ?>
    </div>
    <div class="col-3">
        حتى تاريخ:
    <?= $_GET['to'] ??'' ?>
    </div>
  </div>
  <div class="row p-2 text-center border-bottom alert-primary "  style="font-size: 14px">
    <div class="col-3"> 
    مجموع النهائي:
    {{$clientData['cars_sum']}}
    </div>
    <div class="col-3">
    مبلغ مدفوع:
    {{$clientData['cars_sum']-$clientData['client']->wallet->balance}}
    </div>
    <div class="col-3">
     مبلغ الباقي:
     {{$clientData['client']->wallet->balance}}
    </div>
    <div class="col-3">
      عدد السيارات:
    {{$clientData['car_total']}}
    </div>
  </div>
  <div class="row text-center py-2">
    <table class="table table-sm table-striped table-bordered" style="font-size: 12px">
        <thead>
          <tr>
            <th scope="col">#</th>
            <th scope="col">السيارة</th>
            <th scope="col">تاريخ</th>
            <th scope="col">رقم شاسى</th>
            <th scope="col">رقم كاتى</th>
            <th scope="col">لون</th>
            <th scope="col">موديل</th>
            <th scope="col">كمرك</th>
            <th scope="col">تخليص</th>
            <th scope="col">شهادة</th>
            <th scope="col">نقل</th>
            <th scope="col">مصاريف</th>
            <th scope="col">مجموع</th>
            <th scope="col">ملاحطة</th>
          </tr>
        </thead>
        <tbody>
            @foreach ($clientData['data'] as $key=>$data)
            <tr>
                <th scope="row">{{$key+1}}</th>
                <td>{{$data->car_type}}</td>
                <td>{{$data->date}}</td>
                <td>{{$data->vin}}</td>
                <td>{{$data->car_number}}</td>
                <td>{{$data->car_color}}</td>
                <td>{{$data->year}}</td>
                <td>    <?php
                    $dinar_s = $data->dinar_s;
                    $dolar_price_s = $data->dolar_price_s ?? 1;
                
                    if ($dolar_price_s != 0) {
                        echo round(($dinar_s / $dolar_price_s)*100);
                    } else {
                        echo 0; // or any other appropriate message
                    }
                    ?></td>
                <td>{{$data->checkout_s}}</td>
                <td>{{$data->coc_dolar_s}}</td>
                <td>{{$data->shipping_dolar_s}}</td>
                <td>{{$data->expenses_s}}</td>
                <td>{{$data->total_s}}</td>
                <td>{{$data->note}}</td>
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
