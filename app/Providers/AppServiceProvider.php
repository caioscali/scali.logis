<?php

namespace App\Providers;

use App\Domain\Organizations\Models\UsuarioFilial;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Policies\UsuarioFilialPolicy;
use App\Policies\UsuarioTransportadoraPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(UsuarioTransportadora::class, UsuarioTransportadoraPolicy::class);
        Gate::policy(UsuarioFilial::class, UsuarioFilialPolicy::class);

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip())
            );
        });
    }
}
