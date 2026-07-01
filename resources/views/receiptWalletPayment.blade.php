
@php
use App\Helpers\Help as MyHelp;
$Help = new MyHelp();
@endphp
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
    </style>
</head>
@if($transaction)
  <?php  
  $currency = $transaction->currency;
  $description = $transaction->description;
  $amount = abs($transaction->amount) - ($transaction->discount ?? 0);
  $created = $transaction->created_at;
  ?>
@endif
<body style="direction: rtl;">
<div class="container-fluid mt-2" style="border: 2px solid">       
<div class="row">
    <div class="col-4 text-center py-3">
        <h5 class="pt-3">
       {{$config['first_title_ar']}}
        </h5>
        <h5>
        {{$config['second_title_ar']}}
        </h5>
    </div>
    <div class="col-4 text-center py-3">
    <h5 class="pt-3"> وصل دفع</h5>
    <h5 class="pt-1">Payment Voucher </h5>
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
      <div class="col-3"></div>
      <div class="col-3"></div>
      <div class="col-3">
         تاريخ:
      {{$created ?? ''}}
      </div>
</div>

<div class="row p-2" style="font-size: 14px">
    <div class="col-12 p-2 pe-5"> 
    شركة / Company:
    {{$clientData['client']->name ?? ''}}
    </div>
    <div class="col-12 p-2 pe-5"> 
    دفع مبلغ ل:
      {{$clientData['client']->name ?? ''}}
      </div>

      <div class="col-12 p-2 pe-5"> 
         مبلغ قدره :
         {{ $Help->numberToWords($amount ?? 0, ($currency ?? '$')) }}
        </div>
        <div class="col-12 p-2 pe-5"> 
          الملاحظات:
          {{$description ?? ''}}
         </div>
</div>
<div class="row text-center" style="font-size: 14px">
    <div class="col-1"></div>
    <div class="col-1 alert-primary border p-2"> 
     المبلغ:
    </div>
    <div class="col-1 alert-primary border p-2">
    {{$amount ?? 0}}
    </div>
    <div class="col-1 alert-primary border p-2">
      {{$currency ?? '$'}}
    </div>
    <div class="col-8 text-start ps-5">
      اسم وتوقيع المستلم
    </div>
</div>
@include('Components.receiptFooterContact')
</div>
<hr>
<div class="container-fluid mt-2" style="border: 2px solid">       
  <div class="row">
      <div class="col-4 text-center py-3">
          <h5 class="pt-3">
         {{$config['first_title_ar']}}
          </h5>
          <h5>
          {{$config['second_title_ar']}}
          </h5>
      </div>
      <div class="col-4 text-center py-3">
      <h5 class="pt-3"> وصل دفع</h5>
      <h5 class="pt-1">Payment Voucher </h5>
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
        <div class="col-3"></div>
        <div class="col-3"></div>
        <div class="col-3">
           تاريخ:
        {{$created ?? ''}}
        </div>
  </div>

  <div class="row p-2" style="font-size: 14px">
      <div class="col-12 p-2 pe-5"> 
      شركة / Company:
      {{$clientData['client']->name ?? ''}}
      </div>
      <div class="col-12 p-2 pe-5"> 
    دفع مبلغ ل:
      {{$clientData['client']->name ?? ''}}
        </div>

        <div class="col-12 p-2 pe-5"> 
           مبلغ قدره :
           {{ $Help->numberToWords($amount ?? 0, ($currency ?? '$')) }}
          </div>
          <div class="col-12 p-2 pe-5"> 
            الملاحظات:
            {{$description ?? ''}}
           </div>
  </div>
  <div class="row text-center" style="font-size: 14px">
      <div class="col-1"></div>
      <div class="col-1 alert-primary border p-2"> 
       المبلغ:
      </div>
      <div class="col-1 alert-primary border p-2">
      {{$amount ?? 0}}
      </div>
      <div class="col-1 alert-primary border p-2">
        {{$currency ?? '$'}}
      </div>
      <div class="col-8 text-start ps-5">
        اسم وتوقيع المستلم
      </div>
  </div>
  @include('Components.receiptFooterContact')
</div>
<script>
    $(document).ready(function() {
        function openPrintDialog() {
             window.print();
        }
        openPrintDialog();
    });
</script>
</body>
</html>

