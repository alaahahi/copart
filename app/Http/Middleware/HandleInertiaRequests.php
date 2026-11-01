<?php

namespace App\Http\Middleware;

use App\Models\VinstackImportRequest;
use App\Services\SystemConfigService;
use App\Support\Branding;
use App\Support\VinstackIntegrationOwner;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request)
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed[]
     */
    public function share(Request $request)
    {
        $user = $request->user();

        $configService = app(SystemConfigService::class);
        $branding = $configService->branding();

        return array_merge(parent::share($request), [
            'appName' => $branding['appName'],
            'productName' => Branding::name(),
            'productTagline' => $branding['tagline'],
            'publicAssetPrefix' => \App\Helpers\Help::publicWebPrefix(),
            'branding' => [
                'logo' => $branding['logo'],
                'cover' => $branding['cover'],
            ],
            'dashboardWidgets' => $configService->dashboardWidgets(),
            'auth' => [
                'user' => $user,
                // SPA uses the browser session cookie (Sanctum stateful) — no DB PAT.
                'accessToken' => null,
            ],
            'vinstackImports' => [
                'pending_count' => $this->pendingVinstackImportCount($user),
            ],
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
            'flash' => [
                'message' => session('message'),
                'success' => session('success'),
            ],
        ]);
    }

    private function pendingVinstackImportCount($user): int
    {
        if (! $user) {
            return 0;
        }

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('vinstack_import_requests')) {
                return 0;
            }

            $ownerId = (int) ($user->owner_id ?: VinstackIntegrationOwner::resolve());

            if ($ownerId <= 0) {
                return 0;
            }

            return (int) VinstackImportRequest::query()
                ->where('owner_id', $ownerId)
                ->where('status', VinstackImportRequest::STATUS_PENDING)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

}
