<?php

namespace App\Http\Controllers;

use App\Http\Requests\QueueDebtNoticeRequest;
use App\Http\Requests\UpdateSystemConfigRequest;
use App\Models\SystemConfig;
use App\Models\User;
use App\Services\SystemBrandingService;
use App\Services\WhatsAppQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    protected array $waFields = [
        'wa_enabled',
        'wa_base_host',
        'wa_tenant',
        'wa_source',
        'wa_created_by',
        'wa_notify_debt',
        'wa_notify_car_created',
        'wa_notify_payment',
    ];

    public function __construct(
        protected SystemBrandingService $branding
    ) {
    }

    public function index()
    {
        $config = SystemConfig::first();

        if (! $config) {
            $config = SystemConfig::create([
                'first_title_ar' => config('app.name'),
                'receipt_template' => 'default',
                'wa_base_host' => 'https://wa.intellij-app.com',
                'wa_source' => 'sales',
                'wa_created_by' => 'copart-erp',
            ]);
        }

        return Inertia::render('Settings/Index', [
            'config' => $config,
            'waSources' => WhatsAppQueueService::SOURCES,
        ]);
    }

    public function update(UpdateSystemConfigRequest $request)
    {
        $this->authorize('update', SystemConfig::class);

        $config = SystemConfig::first();
        if (! $config) {
            $config = new SystemConfig();
        }

        $validated = $request->safe()->only(array_merge([
            'receipt_template',
            'receipt_phone',
            'receipt_address',
            'receipt_website',
            'first_title_ar',
            'second_title_ar',
        ], $this->waFields));

        if (empty($validated['wa_base_host'])) {
            $validated['wa_base_host'] = 'https://wa.intellij-app.com';
        }
        if (empty($validated['wa_source'])) {
            $validated['wa_source'] = 'sales';
        }
        if (empty($validated['wa_created_by'])) {
            $validated['wa_created_by'] = 'copart-erp';
        }

        $config->fill($validated);

        $this->applyBrandingUploads($request, $config);

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

    /**
     * Dashboard double-click debt notice → WA Queue (when enabled).
     */
    public function queueDebtNotice(QueueDebtNoticeRequest $request, WhatsAppQueueService $wa)
    {
        $ownerId = (int) Auth::user()->owner_id;
        $client = User::query()
            ->where('id', $request->integer('client_id'))
            ->where('owner_id', $ownerId)
            ->firstOrFail();

        if (! $wa->shouldSend(WhatsAppQueueService::EVENT_DEBT)) {
            return Response::json([
                'queued' => false,
                'fallback' => true,
                'message' => 'إخطار الدين عبر الطابور غير مفعّل — استخدم واتساب المباشر.',
            ]);
        }

        $balance = $request->filled('balance')
            ? (float) $request->input('balance')
            : null;

        $queued = $wa->notifyDebt($client, $balance);

        return Response::json([
            'queued' => $queued,
            'fallback' => ! $queued,
            'message' => $queued
                ? 'تم إرسال إخطار الدين إلى طابور واتساب.'
                : 'تعذر الإرسال — تحقق من رقم الهاتف أو الإعدادات.',
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

    protected function applyBrandingUploads(UpdateSystemConfigRequest $request, SystemConfig $config): void
    {
        foreach (['app_logo', 'app_cover'] as $field) {
            $removeKey = 'remove_'.$field;

            if ($request->boolean($removeKey) && ! $request->hasFile($field)) {
                $this->branding->delete($config->{$field});
                $config->{$field} = null;
                continue;
            }

            if ($request->hasFile($field)) {
                $this->branding->delete($config->{$field});
                $config->{$field} = $this->branding->store($request->file($field), $field);
            }
        }
    }

    protected function storeReceiptLogo($file, string $field): string
    {
        $dir = public_path('img/receipt');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = $field.'_'.time().'.'.$ext;
        $file->move($dir, $name);

        return '/img/receipt/'.$name;
    }

    protected function deleteStoredLogo(?string $path): void
    {
        if (! $path || ! str_starts_with($path, '/img/receipt/')) {
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
