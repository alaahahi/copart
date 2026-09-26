<?php

namespace App\Http\Middleware;

use App\Models\VinstackImportRequest;
use App\Services\Auth\SanctumTokenPairService;
use App\Services\SystemConfigService;
use App\Support\Branding;
use App\Support\VinstackIntegrationOwner;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Sanctum\PersonalAccessToken;
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
        $accessToken = $this->resolveSessionAccessToken($request, $user);

        $branding = app(SystemConfigService::class)->branding();

        return array_merge(parent::share($request), [
            'appName' => $branding['appName'],
            'productName' => Branding::name(),
            'productTagline' => $branding['tagline'],
            'publicAssetPrefix' => \App\Helpers\Help::publicWebPrefix(),
            'branding' => [
                'logo' => $branding['logo'],
                'cover' => $branding['cover'],
            ],
            'auth' => [
                'user' => $user,
                'accessToken' => $accessToken,
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

    /**
     * Prefer the session-stored Sanctum access token; re-issue when missing/expired
     * while the web session is still authenticated (SPA silent refresh).
     */
    private function resolveSessionAccessToken(Request $request, $user): ?string
    {
        if (! $user) {
            return null;
        }

        $plain = $request->session()->get('sanctum_access_token');
        if (is_string($plain) && $plain !== '') {
            $token = PersonalAccessToken::findToken($plain);
            if ($token
                && $token->name === SanctumTokenPairService::ACCESS_TOKEN_NAME
                && (! $token->expires_at || ! $token->expires_at->isPast())
            ) {
                return $plain;
            }
        }

        $pair = app(SanctumTokenPairService::class)->issue($user);
        $request->session()->put('sanctum_access_token', $pair['access_token']);
        $request->session()->put('sanctum_refresh_token', $pair['refresh_token']);

        return $pair['access_token'];
    }
}
