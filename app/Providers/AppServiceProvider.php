<?php

namespace App\Providers;

use App\Services\Fcm\FcmTokenProvider;
use App\Services\Fcm\GoogleFcmTokenProvider;
use App\Services\Fcm\NullFcmTokenProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FcmTokenProvider::class, function (): FcmTokenProvider {
            $credentials = config('services.fcm.credentials');

            return is_string($credentials) && $credentials !== ''
                ? new GoogleFcmTokenProvider($credentials)
                : new NullFcmTokenProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
