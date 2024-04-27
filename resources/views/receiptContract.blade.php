
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

<style>
@font-face {
  font-family: 'Peshang';
  src: url('/Peshang.ttf') format('truetype');
}

body {
  font-family: 'Peshang', sans-serif; /* Use the custom font */
}
  /* Set page size to A4 (210mm x 297mm) */
  @page {
      size: A4;
      margin: 0;
    }

    /* Set content to fill the entire page
     margin: 20px;
      margin-top: 60px;
    }
    */
    html, body {
      width: 210mm;
      height: 297mm;
      margin: 0;
      padding: 0;
    }

    /* Your additional CSS styles go here */
    /* For example: */
  b{
    color:cornflowerblue
  }
  </style>
<body style="direction: rtl;"> 
  @if($config['second_title_ar']=='عين دبي')
  <img src="/img/bg1.jpg" width="100%" class="p-2 pb-0" />
  @else
  <img src="/img/bg.jpg" width="100%" class="p-3" />
  @endif
  <div class="content">
    <div class="d-flex justify-content-around py-2"  style="font-size: 13px ; font-weight: 700;background-color: #f0f8ff">
      <div class="text-center"  style="width:300px">
        <span >
          الرقم : {{$data['id'] ??''}}
        </span>

      </div>
      <div class="text-center"  style="width:300px">
        <span>
        التاريخ  : {{$data['created'] ??''}}
      </span>
      </div>
    </div>

    <div class="d-flex justify-content-around  mt-1"  style="font-size: 13px ; font-weight: 700;">
      <div>
        <div class="text-center  p-1" style="width:300px;border: 1px cornflowerblue solid;background-color: cornflowerblue ;color:#fff">
          <span >
            لایەنی یەکەم فرۆشیار
            -
            الطرف الأول  البائع
          </span>

        </div>
        <div class="  p-2 " style="width:300px;border: 1px cornflowerblue solid;">
          <div class="py-2">
            فرۆشیار / البائع : <span class="fw-bold" style="font-size:14px;">{{$data['name_seller'] ??''}}</span> 
          </div>
          <div class="py-2">
            دانیشتوی / الساکن : {{$data['address_seller'] ??''}}
          </div>
          <div class="py-2">
            رقم موبایل : {{$data['phone_seller'] ??''}}
          </div>
        </div>
      </div>
      <div>
      <div class="text-center p-1"   style="width:300px;border: 1px cornflowerblue solid;background-color: cornflowerblue ;color:#fff">
        <span>
          لایەنی دووەم کریار
          -
          الطرف الثانی المشتری

      </span>
      </div>
      <div class="  p-2 " style="width:300px;border: 1px cornflowerblue solid">
        <div class="py-2">
          کریار / المشتری : <span  class="fw-bold" style="font-size:14px;"> {{$data['name_buyer'] ??''}}
            </span>
        </div>
        <div class="py-2">
          دانیشتوی / الساکن : {{$data['address_buyer']??''}}
        </div>
        <div class="py-2">
          رقم موبایل : {{$data['phone_buyer'] ??''}}
        </div>
      </div>
      </div>
    </div>
    <div class="py-1 text-danger text-center" style="font-size: 13px">
      <div>
        رێکەوتن کرا لە نێوان هەردوو لیەن لە سەر ئەم خالنەی خوارەوه
      </div>
      <div>
        وتم الاتفاق على النقاط التالية بين الطرفين
      </div>
    </div>
    <div style="font-size: 13px;padding: 0 50px">
      <div>
        <div>
        1.
        فرۆشتنی لایەنی یەکەم بە لایەنی دووەم ئوتومبێلی ژمارە  
        (بيع سيارة الطرف الأول إلى سيارة الطرف الثاني رقم)
        :
        <b class="px-3">
          {{$data['no'] ?? ''}}
        </b>
        </div>
        <div class="pt-2">
          لە جۆری 
        (من النوع )
          :
          <b class="px-3">
            {{$data['car_name'] ?? ''}}
          </b>
          مودیل
          :
          <b class="px-3">
            {{$data['modal'] ?? ''}}
          </b>
          قبارە 
          (الحجم) 
          :
          <b class="px-3">
            {{$data['size'] ?? ''}}
          </b>
          رەنگ 
          (اللون) : 
          <b class="px-3">
            {{$data['color'] ?? ''}}
          </b>
        </div>
        <div class="pt-2">
          ژمارە لشە 
          (الشاصی) 
            :
          <b class="px-3">
            {{$data['vin'] ?? ''}}
          </b>
        </div>
        <div class="pt-2">
          لە جیاتی / بڕی پارە 
          (بمبلغ قدره)
          : 
          
          <b class="px-3 fs-6">
            {{$data['car_price'] ?? 0}} $
          </b>
          {{ $Help->numberToWords($data['car_price']??0)}}
        </div>
        <div class="pt-2">
          فرۆشیار وەری گرت بڕی پارە 
          (وقد قبض)
            :
          <b class="px-3 fs-6">
            {{$data['car_paid'] ?? 0}} $
          </b>
          {{ $Help->numberToWords($data['car_paid']??0)}}
        </div>
        <div class="pt-2">
          ئەو برەی ماوەتەوە 
          (الباقی)
            :
          <b class="px-3 fs-6">
            {{($data['car_price']??0)-($data['car_paid'] ?? 0)}} $
          </b>
          {{ $Help->numberToWords(($data['car_price']??0)-($data['car_paid'] ?? 0) ??0)}}
        </div>
      </div>
      <div>
        <div class="pt-3">
        2.
        گۆرینەوەی لایەنی یەکەم ئوتومبێلی ژمارە 
        (السيارة البديلة)
        :
        <b class="px-3">
          {{$data['no_s'] ?? ''}}
        </b>
        </div>
        <div class="pt-2">
          لە جۆری 
        (من النوع )
          :
          <b class="px-3">
            {{$data['car_name_s'] ?? ''}}
          </b>
          مودیل
          :
          <b class="px-3">
            {{$data['modal_s'] ?? ''}}
          </b>
          قبارە 
          (الحجم) 
          :
          <b class="px-3">
            {{$data['size_s'] ?? ''}}
          </b>
          رەنگ 
          (اللون) : 
          <b class="px-3">
            {{$data['color_s'] ?? ''}}
          </b>
        </div>
        <div class="pt-2">
          ژمارە لشە 
          (الشاصی) 
            :
          <b class="px-3">
            {{$data['vin_s'] ?? ''}}
          </b>
        </div>
        <div class="pt-2">
          تێبینی
          (ملاحظة)
            :
          <b class="px-3">
            {{$data['note'] ?? ''}}
          </b>
        </div>
        
      </div>
      <div class="pt-2 " style="color: brown;font-size: 11px">
        3
        .
         علی البائع و المشتری تسجیل السیارة حسب قوانین مدیریة المرور العامة مع إجراء معاملة نقل الملکیة

      </div>
      <div class="pt-2 " style="color: brown;font-size: 11px">
        4
        .
        علی المشتری فحص السیارة قبل الشراء و نحن غیر مسؤولین بعد توقیع عقد المعرض

      </div>
      <div class="pt-2 " style="color: brown;font-size: 11px">
        5
        .
        الطرف الاول مسؤول عن کافة أنواع الغرامات قبل موعد الشراء

      </div>
      <div class="pt-2 " style="color: brown;font-size: 11px">
        6
        .
        صاحب المعرض غیر مسؤول عن السیارة بعد البیع

      </div>
      <div class="pt-2 " style="color: brown;font-size: 11px">
        7
        .
        علی المشتري تسجیل السیارة خلال شهر واحد

      </div>
      <div class="pt-2 " style="color: brown;font-size: 11px">
        8
        .
         کتب هذا العقد بثالثة نسخ بتاریخ
         <b class="px-2">
          {{$data['created'] ?? ''}}
        </b>
        <span class="px-5">
          الساعة
        </span>
        <b class="px-2">
          {{ \Carbon\Carbon::parse($data['created_at'])->format('h:i:s A') }}
        </b>
      </div>
      <div class="pt-2 " style="color: brown;font-size: 11px">
        9
        .
        کل عقد غیر مختوم من المعرض یعتبر باطل
      </div>

      <div class="d-flex justify-content-between  mt-5 pt-2">
        <div>
          بەلێن و رەزامەندی لایەنی یەکەم 
          فرۆشیار
             (البائع)
        </div>
        <div>
          نووسەری پێشانگا
        </div>
        <div>
          بەلێن و رەزامەندی لایەنی دووەم 
          کریار
             (المشتری)
        </div>
      </div>
      <div class="d-flex justify-content-between  mt-4">
        <div class="text-center" style="width: 184px">
          <b>
            {{$data['name_seller'] ??''}}
          </b>
        </div>
        <div class="text-center" style="width: 184px">
          <b>
            كاتب المعرض
          </b>
        </div>
        <div class="text-center" style="width: 184px">
          <b>
            {{$data['name_buyer'] ??''}}
          </b>
        </div>
      </div>
    </div>
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
