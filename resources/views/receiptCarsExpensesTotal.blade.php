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

    
       
    <h5 class="pt-3">  جميع الدفعات </h5>
    </div>
    <div class="col-4 text-center py-3"> 
        @include('Components.logo')

    </div>
    </div>
    <div class="row p-2 text-center border-top border-bottom" style="font-size: 14px">
    <div class="col"> 
    حساب:
    {{$data['client']->name}}
    </div>
    <div class="col">
    موبايل:
    {{$data['client']->phone}}
    </div>
    
  </div>
  <div class="row p-2 text-center border-bottom alert-primary "  style="font-size: 14px">
    @php
      $totalAmountDollar = 0;
      $totalAmountDinar = 0;
    @endphp
    @foreach ($data['carexpenses'] as $key => $expense)
    @php
        $totalAmountDollar += $expense->amount_dollar;
        $totalAmountDinar += $expense->amount_dinar;

    @endphp
@endforeach


    <div class="col-6"> 
     مجموع النهائي بالدولار: 
    {{$totalAmountDollar??0}}
    </div>
    <div class="col-6">
    مجموع النهائي بالدينار:
    {{$totalAmountDinar??0}}
    </div>
    
  </div>
  <div class="row text-center py-2">
    <table class="table table-sm table-striped table-bordered" style="font-size: 12px">
        <thead>
          <tr>
            <th scope="col">تاريخ</th>
            <th scope="col">ملاحظة</th>
            <th scope="col">المبلغ بالدولار</th>
            <th scope="col">المبلغ بالدينار</th>
          </tr>
        </thead>
        <tbody>
            @foreach ($data['carexpenses'] as $key=>$data)
            <tr>
                <td>{{ $data->created }}</td>
                <td>{{ $data->note }}</td>
                <td>{{ $data->amount_dollar }}</td>
                <td>{{ $data->amount_dinar  }}</td>
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
