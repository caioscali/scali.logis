<?php

namespace App\Domain\Organizations\Http\Controllers;

use App\Domain\Organizations\Http\Requests\SelecionarContextoRequest;
use App\Domain\Organizations\Services\ContextoEmpresarial;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContextoEmpresarialController extends Controller
{
    public function __construct(private ContextoEmpresarial $contextoEmpresarial) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Context/Select', [
            'opcoes' => $this->contextoEmpresarial->opcoes($request->user()),
        ]);
    }

    public function store(SelecionarContextoRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $this->contextoEmpresarial->selecionar(
            $request,
            $request->user(),
            (int) $dados['transportadora_id'],
            (int) $dados['filial_id'],
        );

        $destino = $request->session()->pull('contexto.url_destino', route('dashboard'));

        return redirect()->to($this->destinoSeguro($destino));
    }

    public function semVinculo(): Response
    {
        return Inertia::render('Context/NoAccess');
    }

    private function destinoSeguro(mixed $destino): string
    {
        if (! is_string($destino) || $destino === '' || ! Str::startsWith($destino, '/') || Str::startsWith($destino, '//')) {
            return route('dashboard');
        }

        if (preg_match('~[\x00-\x1F\x7F\\\\]~', $destino) === 1) {
            return route('dashboard');
        }

        $partes = parse_url($destino);

        if ($partes === false || isset($partes['scheme'], $partes['host'], $partes['user'], $partes['pass'], $partes['port'])) {
            return route('dashboard');
        }

        return $destino;
    }
}
