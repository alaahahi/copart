
@php
use App\Helpers\Help as MyHelp;
$Help = new MyHelp();
@endphp
<!DOCTYPE html>
<html>
<head>
    <title>شركة سلام جلال أيوب</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</head>
@if($transactions_id)
@foreach($clientData['transactions'] as $transaction)
    @if($transaction->id == $transactions_id)
    
        <?php  
        $currency = $transaction->currency;
        $description =$transaction->description;
        $amount= ($transaction->amount * -1)-$transaction->discount;
        $created =$transaction->created_at ;
       ?>
    @endif
@endforeach
@endif
<style>
@page {
  size: auto; /* auto is the initial value */

  /* this affects the margin in the printer settings */
  margin: 20px;
  margin-top: 60px;
}
</style>
<body style="direction: rtl;">
<div class="container-fluid   mt-2 " style="border: 2px solid">       
<div class="row" >
    <div class="col-4 text-center py-3">
        <h5 class="pt-3">
       {{$config['first_title_ar']}}
        </h5>
        <h5>
        {{$config['second_title_ar']}}
        </h5>
    </div>
    <div class="col-4 text-center py-3">

    
    <h5 class="pt-3"> وصل قبض</h5>
    <h5 class="pt-1">Cash Receipt Voucher </h5>
    </div>
    <div class="col-4 text-center py-3"> 
        @include('Components.logo')

    </div>
    </div>
    <div class="row p-2 text-center border-top border-bottom" style="font-size: 14px">
      <div class="col-3"> 
      الرقم:
      {{$transactions_id}}
    </div>
      <div class="col-3">
 
      </div>
      <div class="col-3">
    
      </div>
      <div class="col-3">
         تاريخ:
      <?= $created ??'' ?>
      </div>
    </div>

    <div class="row p-2" style="font-size: 14px">
    <div class="col-12  p-2 pe-5"> 
    شركة / Company:
    {{$clientData['client']->name}}
    </div>
    <div class="col-12  p-2  pe-5"> 
      استلمت من :
      {{$clientData['client']->name}}
      </div>

      <div class="col-12  p-2  pe-5"> 
         مبلغ قدره :
         {{ $Help->numberToWords($amount??0,$currency)}}
        </div>
        <div class="col-12  p-2  pe-5"> 
          الملاحظات:
          {{$description??''}}
         </div>
        
        
      
  </div>
  <div class="row  text-center   "  style="font-size: 14px">
    <div class="col-1">
      </div>
    <div class="col-1 alert-primary border p-2"> 
     المبلغ:
    </div>
    <div class="col-1 alert-primary border p-2">
    {{$amount??0}}
    </div>
    <div class="col-1 alert-primary border p-2">
      {{$currency}}
    </div>
    <div class="col-8 text-start ps-5">
      اسم وتوقيع المستلم
    </div>
  </div>
  <div class="row p-2  border-top border-bottom mt-3" style="font-size: 14px">
    <div class="col-6 pe-5"> 
    العنوان:
    @if($owner_id??'')
    @if($owner_id==2)
    {{$config['address_kik']}}
    @else
    {{$config['address_erb']}}
    @endif
    @endif

    </div>

    <div class="col-6 ps-5 text-start">
       Mobile:
       @if($owner_id??'')
       @if($owner_id==2)
       {{$config['mobile_kik']}}
       @else
       {{$config['mobile_erb']}}
       @endif
       @endif
    </div>
  </div>
  {{-- <div class="row text-center py-2">
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
                <td>{{$data->expenses}}</td>
                <td>{{$data->total_s}}</td>
              </tr>
            @endforeach
        </tbody>
      </table>  
  </div> --}}
</div>
<hr>
<div class="container-fluid   mt-2 " style="border: 2px solid">       
  <div class="row" >
      <div class="col-4 text-center py-3">
          <h5 class="pt-3">
         {{$config['first_title_ar']}}
          </h5>
          <h5>
          {{$config['second_title_ar']}}
          </h5>
      </div>
      <div class="col-4 text-center py-3">
  
      
         
      <h5 class="pt-3"> وصل قبض</h5>
      <h5 class="pt-1">Cash Receipt Voucher </h5>
      </div>
      <div class="col-4 text-center py-3"> 
          @include('Components.logo')
  
      </div>
      </div>
      <div class="row p-2 text-center border-top border-bottom" style="font-size: 14px">
        <div class="col-3"> 
        الرقم:
        {{$transactions_id}}
        </div>
        <div class="col-3">
   
        </div>
        <div class="col-3">
      
        </div>
        <div class="col-3">
           تاريخ:
        <?= $created ??'' ?>
        </div>
      </div>
  
      <div class="row p-2" style="font-size: 14px">
      <div class="col-12  p-2  pe-5"> 
      شركة / Company:
      {{$clientData['client']->name}}
      </div>
      <div class="col-12  p-2  pe-5"> 
        استلمت من :
        {{$clientData['client']->name}}
        </div>
  
        <div class="col-12  p-2  pe-5"> 
           مبلغ قدره :
           {{  $Help->numberToWords($amount??0,$currency)}}
          </div>
          <div class="col-12  p-2  pe-5"> 
            الملاحظات:
            {{$description??''}}
           </div>
          
          
        
    </div>
    <div class="row  text-center   "  style="font-size: 14px">
      <div class="col-1">
      </div>
      <div class="col-1 alert-primary border p-2"> 
       المبلغ:
      </div>
      <div class="col-1 alert-primary border p-2">
      {{$amount??0}}
      </div>
      <div class="col-1 alert-primary border p-2">
      {{$currency}}
      </div>
      <div class="col-8 text-start ps-5">
        اسم وتوقيع المستلم
      </div>
    </div>
    <div class="row p-2  border-top border-bottom mt-3" style="font-size: 14px">
      <div class="col-6 pe-5"> 
      العنوان:
      @if($owner_id??'')
      @if($owner_id==2)
      {{$config['address_kik']}}
      @else
      {{$config['address_erb']}}
      @endif
      @endif

      </div>

      <div class="col-6 ps-5 text-start">
         Mobile:
         @if($owner_id??'')
         @if($owner_id==2)
         {{$config['mobile_kik']}}
         @else
         {{$config['mobile_erb']}}
         @endif
         @endif
      </div>
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
