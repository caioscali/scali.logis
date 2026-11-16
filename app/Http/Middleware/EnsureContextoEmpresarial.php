<?php

namespace App\Http\Middleware;

use App\Domain\Organizations\Services\ContextoEmpresarial;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContextoEmpresarial
{
    public function __construct(private ContextoEmpresarial $contextoEmpresarial) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return redirect()->route('login');
        }

        if ($this->contextoEmpresarial->atual($request, $usuario) !== null) {
            return $next($request);
        }

        $this->contextoEmpresarial->limpar($request);
        $opcoes = $this->contextoEmpresarial->opcoes($usuario);

        if ($opcoes === []) {
            return redirect()->route('contexto.sem-vinculo');
        }

        if (count($opcoes) === 1 && count($opcoes[0]['filiais']) === 1) {
            $this->contextoEmpresarial->selecionar(
                $request,
                $usuario,
                $opcoes[0]['transportadora']['id'],
                $opcoes[0]['filiais'][0]['id'],
            );

            return $next($request);
        }

        $request->session()->put('contexto.url_destino', $request->fullUrl());

        return redirect()->route('contexto.selecionar');
    }
}
