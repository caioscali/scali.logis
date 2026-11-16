<?php

namespace App\Http\Middleware;

use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Services\AcessoEmpresarial;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManageTransportadoraUsers
{
    public function __construct(private AcessoEmpresarial $acessoEmpresarial) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $transportadora = $request->route('transportadora');

        abort_unless(
            $transportadora instanceof Transportadora
            && $this->acessoEmpresarial->podeAdministrarUsuarios($request->user(), $transportadora),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
