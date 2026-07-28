<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Car;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\Vault;
use App\Policies\CarPolicy;
use App\Policies\SystemConfigPolicy;
use App\Policies\UserPolicy;
use App\Policies\VaultPolicy;
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
        User::class => UserPolicy::class,
        Vault::class => VaultPolicy::class,
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
