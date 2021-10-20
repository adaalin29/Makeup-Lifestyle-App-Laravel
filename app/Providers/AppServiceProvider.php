<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Contracts\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->isLocal()) {
          $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
          $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        $this->bootAppleSocialiteDriver();
    }

    public function bootAppleSocialiteDriver()
    {
        $socialite = $this->app->make(Factory::class);
        $socialite->extend(
            'sign-in-with-apple',
            function ($app) use ($socialite) {
                $config = $app['config']['services.sign_in_with_apple'];
                return $socialite->buildProvider(SignInWithAppleProvider::class, $config);
            }
        );
    }
}
