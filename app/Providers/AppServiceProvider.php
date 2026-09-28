<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Services\SystemConfigService;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(SystemConfigService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Signed tokens only — never write personal_access_tokens (SQLite lock risk).
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
