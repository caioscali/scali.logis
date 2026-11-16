<?php

use App\Domain\Organizations\Http\Controllers\ContextoEmpresarialController;
use App\Domain\Organizations\Http\Controllers\FilialController;
use App\Domain\Organizations\Http\Controllers\GrupoEmpresarialController;
use App\Domain\Organizations\Http\Controllers\TransportadoraController;
use App\Domain\Organizations\Http\Controllers\UsuarioTransportadoraController;
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
});
