<!DOCTYPE html>
<html>
<head>
    <title>بطاقة الهلال الأحمر</title>
    <meta http-equiv="Content-Type" content="text/html"; charset="utf-8" />
    <style type="text/css">
        .col-4{
            font-size: 16px;font-weight: 700;padding-right: 20px;
        }
        .red{
            color:rgb(255, 8, 78)
        }
</style>   
</head>
<body style=" direction:rtl;margin-top:-20px;padding: 0;margin:0 ">
<div style="background-color: black;padding:2px;margin: -5px;">
<div  style="background-color: #fff;padding:2px; padding-bottom:10px;">
<table>
<tr>
    <th style=" font-size: 16px;font-weight: 700;padding-right: 20px">
        {{$config['first_title_ar']}}
        <br>
        {{$config['second_title_ar']}}
        <br>
        {{$config['third_title_ar']}}
        <br>
    </th>
    <th  style=" font-size: 16px;font-weight: 700;padding-right: 45px">
    @include('logo')
    
       <br>بطاقة الهلال الأحمر
    </th>
    <th  style=" font-size: 16px;font-weight: 700;padding-right: 45px"> 
        {{$config['first_title_kr']}}
        <br>
        {{$config['second_title_kr']}}
        <br>
        {{$config['third_title_kr']}}
        <br>
    </th>
    </tr>
    <tr>
        <td style="padding-right:20px;font-size:13px">
        التسلسل:
        <span>{{$profile->no}}</span>
        </td>
    </tr>
    <tr>
        <td style="padding-right:20px;font-size:13px">
        بتاريخ:
        <span>{{$profile->created_at}}</span>
        </td>
    </tr>
    <tr>
        <td style="padding-right:20px;font-size:13px">
        رقم الوصل:
            <span>{{$profile->invoice_number}}</span>
        </td>
    </tr>
</table>

    <div  style="font-weight: 700;font-size:16px;color:rgb(255, 8, 78);text-align: right;padding-right: 20px">
   معلومات البطاقة

    </div>



        <div  style="font-weight: 700;font-size:13px;text-align: right;  line-height: 20px;padding-right: 20px">
            بطاقة رقم:   {{ $profile->card_number }}
            <br>
            الاسم:   {{ $profile->name }}
            <br>
            العنوان:   {{ $profile->address }}    
            <br>
            أفراد العائلة:   {{ $profile->family_name }}    
            <br>
            الهاتف:   {{ $profile->phone_number }} 
        </div>



<table style="width: 100%; margin-top: 0px;">
    <tr>
        <th  style="font-weight: 700;font-size:14px;text-align: center;padding: 0 25px;">
        توقيع المندوب  
        </th>
 
        <th   style="font-weight: 700;font-size:14px;text-align: center;padding: 0 25px;">
        </th>
        <th   style="font-weight: 700;font-size:14px;text-align: center;padding: 0 25px;">
        توقيع العميل
        </th>
        <th>
            <div style="color: #fff;text-align: left;padding-left: 20px;">
                {!! QrCode::size(100)->generate($url.'/show/'.$profile->id); !!}
            </div>
        </th>
        </tr>
</table>
</div>
</div>
</body>
</html>