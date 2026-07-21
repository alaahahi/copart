<?php

namespace App\Http\Controllers;

use App\Models\SystemConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class SystemConfigController extends Controller
{
    public function index()
    {
        $config = SystemConfig::first();

        if (!$config) {
            $config = SystemConfig::create([
                'first_title_ar' => config('app.name'),
                'receipt_template' => 'default',
            ]);
        }

        return Inertia::render('Settings/Index', [
            'config' => $config,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'receipt_template' => 'required|in:default,mkl_usd',
            'receipt_phone' => 'nullable|string|max:255',
            'receipt_address' => 'nullable|string|max:500',
            'receipt_website' => 'nullable|string|max:255',
            'first_title_ar' => 'nullable|string|max:255',
            'second_title_ar' => 'nullable|string|max:255',
        ]);

        $config = SystemConfig::first();
        if (!$config) {
            $config = new SystemConfig();
        }

        $config->fill($validated);
        $config->save();

        return Response::json([
            'message' => 'تم حفظ الإعدادات',
            'config' => $config,
        ]);
    }

    public function previewReceipt(Request $request)
    {
        $config = SystemConfig::first();
        $type = $request->get('type', 'receipt') === 'payment' ? 'payment' : 'receipt';

        return view('receiptVoucherMkl', array_merge(
            $this->sampleVoucherData($type),
            ['config' => $config ? $config->toArray() : []]
        ));
    }

    protected function sampleVoucherData(string $voucherType): array
    {
        return [
            'voucherType' => $voucherType,
            'clientName' => 'اسم الزبون / Customer Name',
            'amount' => 1500,
            'currency' => '$',
            'created' => now(),
            'description' => 'معاينة القالب',
            'vin' => '1HGCM82633A123456',
            'lot' => '12345678',
            'paidUp' => '1000',
            'rest' => '500',
        ];
    }
}
