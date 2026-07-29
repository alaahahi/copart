<?php

namespace App\Http\Middleware;

use App\Models\SystemConfig;
use App\Services\Auth\SanctumTokenPairService;
use App\Services\SystemBrandingService;
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

        $branding = SystemConfig::query()
            ->select(['app_logo', 'app_cover', 'first_title_ar'])
            ->first();

        $brandingService = app(SystemBrandingService::class);

        return array_merge(parent::share($request), [
            'appName' => $branding?->first_title_ar ?: config('app.name'),
            'branding' => [
                'logo' => $brandingService->resolve($branding?->app_logo),
                'cover' => $brandingService->resolve($branding?->app_cover),
            ],
            'auth' => [
                'user' => $user,
                'accessToken' => $accessToken,
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
