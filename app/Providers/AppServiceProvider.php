<?php

namespace App\Providers;

use App\Domain\Finance\Models\ContaBancaria;
use App\Domain\Finance\Policies\ContaBancariaPolicy;
use App\Domain\FreightContracts\Models\ContratoFreteCarreto;
use App\Domain\FreightContracts\Policies\ContratoFreteCarretoPolicy;
use App\Domain\LoadingOrders\Contracts\GoogleDriveClient;
use App\Domain\LoadingOrders\Contracts\GoogleSheetsClient;
use App\Domain\LoadingOrders\Models\OrdemCarregamento;
use App\Domain\LoadingOrders\Policies\OrdemCarregamentoPolicy;
use App\Domain\LoadingOrders\Services\GoogleApiClient;
use App\Domain\Organizations\Models\UsuarioFilial;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Registry\Models\ConjuntoTransporte;
use App\Domain\Registry\Models\Implemento;
use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Models\Veiculo;
use App\Domain\Registry\Policies\ConjuntoTransportePolicy;
use App\Domain\Registry\Policies\ImplementoPolicy;
use App\Domain\Registry\Policies\PessoaPolicy;
use App\Domain\Registry\Policies\VeiculoPolicy;
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
        $this->app->singleton(GoogleApiClient::class);
        $this->app->alias(GoogleApiClient::class, GoogleSheetsClient::class);
        $this->app->alias(GoogleApiClient::class, GoogleDriveClient::class);
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(UsuarioTransportadora::class, UsuarioTransportadoraPolicy::class);
        Gate::policy(UsuarioFilial::class, UsuarioFilialPolicy::class);
        Gate::policy(Pessoa::class, PessoaPolicy::class);
        Gate::policy(ContaBancaria::class, ContaBancariaPolicy::class);
        Gate::policy(Veiculo::class, VeiculoPolicy::class);
        Gate::policy(Implemento::class, ImplementoPolicy::class);
        Gate::policy(ConjuntoTransporte::class, ConjuntoTransportePolicy::class);
        Gate::policy(OrdemCarregamento::class, OrdemCarregamentoPolicy::class);
        Gate::policy(ContratoFreteCarreto::class, ContratoFreteCarretoPolicy::class);

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip())
            );
        });

        RateLimiter::for('pessoas-pesquisa', function (Request $request): Limit {
            return Limit::perMinute(10)->by(($request->user()?->id ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('contas-pesquisa', function (Request $request): Limit {
            return Limit::perMinute(10)->by(($request->user()?->id ?? 'guest').'|'.$request->ip());
        });
    }
}
