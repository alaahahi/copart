<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ImportInfo;
use App\Exports\ExportInfo;
use App\Models\Info;
class InfoController extends Controller
{
    public function importView(Request $request){
        return view('importFile');
    }
    public function import(Request $request){
        Excel::import(new ImportInfo, $request->file('file')->store('files'));
        return redirect()->back();
    }
    public function exportInfos(Request $request){
        return Excel::download(new ExportInfo, 'Infos.xlsx');
    }
}