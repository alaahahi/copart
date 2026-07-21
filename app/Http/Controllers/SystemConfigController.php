<?php

namespace App\Http\Controllers;

use App\Models\SystemConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class SystemConfigController extends Controller
{
    protected array $logoFields = [
        'receipt_logo_left_1',
        'receipt_logo_left_2',
        'receipt_logo_left_3',
        'receipt_logo_haulf',
        'receipt_logo_main',
    ];

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

        foreach ($this->logoFields as $field) {
            if ($request->hasFile($field)) {
                $request->validate([
                    $field => 'image|mimes:jpeg,jpg,png,webp,gif,svg|max:4096',
                ]);
            }
        }

        $config = SystemConfig::first();
        if (!$config) {
            $config = new SystemConfig();
        }

        $config->fill($validated);

        foreach ($this->logoFields as $field) {
            if ($request->hasFile($field)) {
                $this->deleteStoredLogo($config->{$field});
                $config->{$field} = $this->storeReceiptLogo($request->file($field), $field);
            }
        }

        $config->save();

        return Response::json([
            'message' => 'تم حفظ الإعدادات',
            'config' => $config->fresh(),
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

    protected function storeReceiptLogo($file, string $field): string
    {
        $dir = public_path('img/receipt');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = $field . '_' . time() . '.' . $ext;
        $file->move($dir, $name);

        return '/img/receipt/' . $name;
    }

    protected function deleteStoredLogo(?string $path): void
    {
        if (!$path || !str_starts_with($path, '/img/receipt/')) {
            return;
        }

        $full = public_path(ltrim($path, '/'));
        if (File::isFile($full)) {
            File::delete($full);
        }
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
