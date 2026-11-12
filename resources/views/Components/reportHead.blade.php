{{-- Shared <head> assets for ERP print reports --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $pageTitle ?? ($title ?? \App\Support\Branding::name()) }}</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
@php
    $reportCssPath = public_path('css/report-print.css');
    $reportCssVer = is_file($reportCssPath) ? filemtime($reportCssPath) : time();
@endphp
<link href="{{ \App\Helpers\Help::publicAssetUrl('css/report-print.css') }}?v={{ $reportCssVer }}" rel="stylesheet">
