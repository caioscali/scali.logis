<?php

use App\Domain\Finance\Http\Controllers\ContaBancariaController;
use App\Domain\Organizations\Http\Controllers\ContextoEmpresarialController;
use App\Domain\Organizations\Http\Controllers\FilialController;
use App\Domain\Organizations\Http\Controllers\GrupoEmpresarialController;
use App\Domain\Organizations\Http\Controllers\TransportadoraController;
use App\Domain\Organizations\Http\Controllers\UsuarioTransportadoraController;
use App\Domain\Registry\Http\Controllers\EquipamentoController;
use App\Domain\Registry\Http\Controllers\PessoaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', function (Request $request): RedirectResponse {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Link de verificação enviado.');
    })->middleware('throttle:6,1')->name('verification.send');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/contexto/selecionar', [ContextoEmpresarialController::class, 'index'])->name('contexto.selecionar');
    Route::post('/contexto/selecionar', [ContextoEmpresarialController::class, 'store'])->name('contexto.selecionar.store');
    Route::get('/contexto/sem-vinculo', [ContextoEmpresarialController::class, 'semVinculo'])->name('contexto.sem-vinculo');
    Route::get('/dashboard', DashboardController::class)->middleware('enterprise.context')->name('dashboard');

    Route::middleware('platform.admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('grupos-empresariais', [GrupoEmpresarialController::class, 'index'])->name('grupos-empresariais.index');
        Route::get('grupos-empresariais/create', [GrupoEmpresarialController::class, 'create'])->name('grupos-empresariais.create');
        Route::post('grupos-empresariais', [GrupoEmpresarialController::class, 'store'])->name('grupos-empresariais.store');
        Route::get('grupos-empresariais/{grupoEmpresarial}/edit', [GrupoEmpresarialController::class, 'edit'])->name('grupos-empresariais.edit');
        Route::put('grupos-empresariais/{grupoEmpresarial}', [GrupoEmpresarialController::class, 'update'])->name('grupos-empresariais.update');
        Route::delete('grupos-empresariais/{grupoEmpresarial}', [GrupoEmpresarialController::class, 'destroy'])->name('grupos-empresariais.destroy');

        Route::get('transportadoras', [TransportadoraController::class, 'index'])->name('transportadoras.index');
        Route::get('transportadoras/create', [TransportadoraController::class, 'create'])->name('transportadoras.create');
        Route::post('transportadoras', [TransportadoraController::class, 'store'])->name('transportadoras.store');
        Route::get('transportadoras/{transportadora}', [TransportadoraController::class, 'show'])->name('transportadoras.show');
        Route::get('transportadoras/{transportadora}/edit', [TransportadoraController::class, 'edit'])->name('transportadoras.edit');
        Route::put('transportadoras/{transportadora}', [TransportadoraController::class, 'update'])->name('transportadoras.update');
        Route::delete('transportadoras/{transportadora}', [TransportadoraController::class, 'destroy'])->name('transportadoras.destroy');

        Route::get('transportadoras/{transportadora}/filiais', [FilialController::class, 'index'])->name('transportadoras.filiais.index');
        Route::get('transportadoras/{transportadora}/filiais/create', [FilialController::class, 'create'])->name('transportadoras.filiais.create');
        Route::post('transportadoras/{transportadora}/filiais', [FilialController::class, 'store'])->name('transportadoras.filiais.store');
        Route::get('transportadoras/{transportadora}/filiais/{filial}', [FilialController::class, 'show'])->name('transportadoras.filiais.show');
        Route::get('transportadoras/{transportadora}/filiais/{filial}/edit', [FilialController::class, 'edit'])->name('transportadoras.filiais.edit');
        Route::put('transportadoras/{transportadora}/filiais/{filial}', [FilialController::class, 'update'])->name('transportadoras.filiais.update');
        Route::delete('transportadoras/{transportadora}/filiais/{filial}', [FilialController::class, 'destroy'])->name('transportadoras.filiais.destroy');
    });

    Route::middleware('enterprise.user.admin')
        ->prefix('admin/transportadoras/{transportadora}/usuarios')
        ->name('admin.transportadoras.usuarios.')
        ->group(function (): void {
            Route::get('/', [UsuarioTransportadoraController::class, 'index'])->name('index');
            Route::get('/create', [UsuarioTransportadoraController::class, 'create'])->name('create');
            Route::post('/', [UsuarioTransportadoraController::class, 'store'])->name('store');
            Route::get('/{usuarioTransportadora}', [UsuarioTransportadoraController::class, 'show'])->name('show');
            Route::get('/{usuarioTransportadora}/edit', [UsuarioTransportadoraController::class, 'edit'])->name('edit');
            Route::put('/{usuarioTransportadora}', [UsuarioTransportadoraController::class, 'update'])->name('update');
            Route::delete('/{usuarioTransportadora}', [UsuarioTransportadoraController::class, 'destroy'])->name('destroy');
        });

    Route::middleware('enterprise.context')->prefix('pessoas')->name('pessoas.')->group(function (): void {
        Route::get('/', [PessoaController::class, 'index'])->name('index');
        Route::get('/criar', [PessoaController::class, 'create'])->name('create');
        Route::post('/', [PessoaController::class, 'store'])->name('store');
        Route::get('/pesquisar', [PessoaController::class, 'pesquisar'])->middleware('throttle:pessoas-pesquisa')->name('pesquisar');
        Route::get('/{pessoa}', [PessoaController::class, 'show'])->name('show');
        Route::get('/{pessoa}/editar', [PessoaController::class, 'edit'])->name('edit');
        Route::put('/vinculos/{transportadoraPessoa}', [PessoaController::class, 'update'])->name('update');
    });

    Route::middleware('enterprise.context')->group(function (): void {
        Route::get('/veiculos', [EquipamentoController::class, 'veiculos'])->name('veiculos.index');
        Route::get('/veiculos/criar', [EquipamentoController::class, 'criarVeiculo'])->name('veiculos.create');
        Route::post('/veiculos', [EquipamentoController::class, 'armazenarVeiculo'])->name('veiculos.store');
        Route::get('/veiculos/pesquisar', [EquipamentoController::class, 'pesquisarVeiculo'])->middleware('throttle:pessoas-pesquisa')->name('veiculos.pesquisar');
        Route::get('/veiculos/{veiculo}', [EquipamentoController::class, 'mostrarVeiculo'])->name('veiculos.show');
        Route::get('/veiculos/{veiculo}/editar', [EquipamentoController::class, 'editarVeiculo'])->name('veiculos.edit');
        Route::put('/veiculos/vinculos/{transportadoraVeiculo}', [EquipamentoController::class, 'atualizarVeiculo'])->name('veiculos.update');

        Route::get('/implementos', [EquipamentoController::class, 'implementos'])->name('implementos.index');
        Route::get('/implementos/criar', [EquipamentoController::class, 'criarImplemento'])->name('implementos.create');
        Route::post('/implementos', [EquipamentoController::class, 'armazenarImplemento'])->name('implementos.store');
        Route::get('/implementos/pesquisar', [EquipamentoController::class, 'pesquisarImplemento'])->middleware('throttle:pessoas-pesquisa')->name('implementos.pesquisar');
        Route::get('/implementos/{implemento}', [EquipamentoController::class, 'mostrarImplemento'])->name('implementos.show');
        Route::get('/implementos/{implemento}/editar', [EquipamentoController::class, 'editarImplemento'])->name('implementos.edit');
        Route::put('/implementos/vinculos/{transportadoraImplemento}', [EquipamentoController::class, 'atualizarImplemento'])->name('implementos.update');

        Route::prefix('contas-bancarias')->name('contas-bancarias.')->group(function (): void {
            Route::get('/', [ContaBancariaController::class, 'index'])->name('index');
            Route::get('/criar', [ContaBancariaController::class, 'create'])->name('create');
            Route::post('/', [ContaBancariaController::class, 'store'])->name('store');
            Route::get('/pesquisar-pessoa', [ContaBancariaController::class, 'pesquisarPessoa'])->middleware('throttle:contas-pesquisa')->name('pesquisar-pessoa');
            Route::put('/vinculos/{transportadoraContaBancaria}', [ContaBancariaController::class, 'update'])->name('update');
            Route::get('/{contaBancaria}', [ContaBancariaController::class, 'show'])->name('show');
            Route::get('/{contaBancaria}/editar', [ContaBancariaController::class, 'edit'])->name('edit');
        });
    });
});
