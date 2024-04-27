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

    
       
    <h5 class="pt-3">   تقرير الزبائن  </h5>
    </div>
    <div class="col-4 text-center py-3"> 
        @include('Components.logo')

    </div>
    </div>
    <div class="row p-2 text-center border-top border-bottom" style="font-size: 14px">
    <div class="col"> 
    فرع:
    @if($owner_id??'')
    @if($owner_id==2)
    {{$config['address_kik']}}
    @else
    {{$config['address_erb']}}
    @endif
    @endif

    </div>
    <div class="col" >
      <div>
    موبايل:
    @if($owner_id??'')
    @if($owner_id==2)
    {{$config['mobile_kik']}}
    @else
    {{$config['mobile_erb']}}
    @endif
    @endif
    </div>
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
  @php
    $totalBalance = 0;
    $totalcar = 0;
    $totalcontract = 0;
    foreach ($data as $item) {
        $totalBalance += $item->balance;
        $totalcar += $item->car_count;
        $totalcontract += $item->contract_count;
    }
  @endphp
  <div class="row p-2 text-center border-bottom alert-primary "  style="font-size: 14px">
    <div class="col-3"> 
    مجموع الزبائن:
    {{ count($data)}}
    </div>
    <div class="col-3">
    مجموع الدين:
    {{$totalBalance}}
    </div>
   
    @if($totalcar)
    <div class="col-3">
      عدد السيارات:
    {{$totalcar}}
    </div>
    @endif
    
    @if($totalcontract)
    <div class="col-3">
      عدد العقود:
    {{$totalcontract}}
    </div>
    @endif
  </div>
  <div class="row text-center py-2">
    <table class="table table-sm table-striped table-bordered" style="font-size: 12px">
        <thead>
          <tr>
            <th scope="col">تسلسل</th>
            <th scope="col">الاسم</th>
            <th scope="col">الهاتف</th>
            <th scope="col">السيارات</th>
            <th scope="col">غير المدفوع</th>
            <th scope="col">المدفوع</th>
            <th scope="col">العقود المنجزة</th>
            <th scope="col">العقود غير المنجزة</th>
            <th scope="col">الدين</th>
          </tr>
        </thead>
        <tbody>
            @foreach ($data as $key=>$data)
            <tr>
              <td>{{$key+1}}</td>
              <td>{{$data->name}}</td>
              <td>{{$data->phone}}</td>
              <td>{{$data->car_count}}</td>
              <td>{{$data->car_count-$data->car_count_completed}}</td>
              <td>{{$data->car_count_completed}}</td>
              <td>{{$data->contract_count}}</td>
              <td>{{$data->car_count-$data->contract_count}}</td>
              <td>{{$data->balance}} $</td>
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
