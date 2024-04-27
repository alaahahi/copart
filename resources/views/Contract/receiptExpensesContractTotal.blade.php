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

    
       
    <h5 class="pt-3">  جميع المصاريف </h5>
    </div>
    <div class="col-4 text-center py-3"> 
        @include('Components.logo')

    </div>
    </div>
    <div class="row p-2 text-center border-top border-bottom" style="font-size: 14px">
    <div class="col"> 
    حساب:
    صندوق عقود الشركة
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
    مجموع النهائي بالدولار:
    {{$totalDollar??0}}
    </div>
    <div class="col-6">
      مجموع النهائي بالدينار العراقي:
      {{$totalDinar??0}}
    </div>
  </div>
  <div class="row text-center py-2">
    <table class="table table-sm table-striped table-bordered" style="font-size: 12px">
        <thead>
          <tr>
            <th scope="col">تاريخ</th>
            <th scope="col">المبلغ بالدولار</th>
            <th scope="col">المبلغ بالدينار</th>
            <th scope="col">البيان</th>
          </tr>
        </thead>
        <tbody>
            @foreach ($data as $key=>$data)
            <tr>
                <td>{{ $data->created }}</td>
                <td>
                  @if($data->currency=='$')
                  {{ $data->amount  }}
                  @endif
                </td>
                <td>  @if($data->currency=='IQD')
                  {{ $data->amount  }}
                  @endif</td>
                <td>{{ $data->description  }}</td>
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
