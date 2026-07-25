<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Car;
use App\Models\SystemConfig;
use App\Policies\CarPolicy;
use App\Policies\SystemConfigPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Car::class => CarPolicy::class,
        SystemConfig::class => SystemConfigPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
