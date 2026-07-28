<?php

namespace App\Http\Middleware;

use App\Models\SystemConfig;
use App\Services\SystemBrandingService;
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
        $accessToken = null;

        // Check if the user is authenticated
        $user = $request->user();
        if ($user) {
            // If using Laravel Passport, get the access token
            $accessToken = $user->createToken('Token Name')->accessToken;
        }

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
                'accessToken' => $accessToken?->token,
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
}
