<?php

namespace Tests\Feature;

use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Registry\Models\Pessoa;
use App\Domain\Registry\Models\TransportadoraPessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PessoasTest extends TestCase
{
    use DatabaseMigrations;

    public function test_person_is_created_with_normalized_cpf_and_local_function(): void
    {
        [$user, $transportadora] = $this->contexto(PerfilAcesso::Administrador);

        $response = $this->actingAs($user)->post(route('pessoas.store'), [
            'tipo' => 'fisica',
            'nome' => 'João da Silva',
            'documento' => '529.982.247-25',
            'funcoes' => ['motorista', 'proprietario'],
        ]);

        $response->assertRedirect();
        $pessoa = Pessoa::query()->firstOrFail();
        $this->assertSame('52998224725', $pessoa->documento);
        $this->assertDatabaseCount('pessoas_funcoes', 2);
        $this->assertDatabaseHas('transportadoras_pessoas', ['transportadora_id' => $transportadora->id, 'pessoa_id' => $pessoa->id, 'ativo' => true]);
    }

    public function test_person_is_created_with_valid_cnpj(): void
    {
        [$user] = $this->contexto(PerfilAcesso::Gerente);

        $this->actingAs($user)->post(route('pessoas.store'), [
            'tipo' => 'juridica',
            'nome' => 'Empresa de Transporte Ltda.',
            'documento' => '11.222.333/0001-81',
            'funcoes' => ['transportador'],
        ])->assertRedirect();

        $this->assertDatabaseHas('pessoas', ['tipo' => 'juridica', 'documento' => '11222333000181']);
    }

    public function test_invalid_documents_are_rejected(): void
    {
        [$user] = $this->contexto(PerfilAcesso::Administrador);

        $this->actingAs($user)->post(route('pessoas.store'), [
            'tipo' => 'fisica', 'nome' => 'Inválido', 'documento' => '111.111.111-11',
        ])->assertSessionHasErrors('documento');

        $this->actingAs($user)->post(route('pessoas.store'), [
            'tipo' => 'juridica', 'nome' => 'Inválido', 'documento' => '11.222.333/0001-80',
        ])->assertSessionHasErrors('documento');
    }

    public function test_existing_person_can_be_adopted_by_another_transportadora_without_global_overwrite(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A');
        [$segunda, $filialB] = $this->criarVinculo($user, 'B');

        $this->selecionarContexto($user, $primeira, $filialA);
        $this->actingAs($user)->post(route('pessoas.store'), [
            'tipo' => 'fisica', 'nome' => 'Nome original', 'documento' => '52998224725',
        ])->assertRedirect();

        $this->selecionarContexto($user, $segunda, $filialB);
        $this->actingAs($user)->post(route('pessoas.store'), [
            'tipo' => 'fisica', 'nome' => 'Tentativa de sobrescrita', 'documento' => '52998224725',
        ])->assertRedirect();

        $this->assertSame(1, Pessoa::query()->count());
        $this->assertSame(2, TransportadoraPessoa::query()->count());
        $this->assertSame('Nome original', Pessoa::query()->firstOrFail()->nome);
    }

    public function test_duplicate_function_is_rejected_and_multiple_functions_are_allowed(): void
    {
        [$user] = $this->contexto(PerfilAcesso::Administrador);

        $this->actingAs($user)->post(route('pessoas.store'), [
            'tipo' => 'fisica', 'nome' => 'Pessoa com funções', 'documento' => '52998224725',
            'funcoes' => ['motorista', 'cliente', 'motorista'],
        ])->assertSessionHasErrors('funcoes.2');

        $this->actingAs($user)->post(route('pessoas.store'), [
            'tipo' => 'fisica', 'nome' => 'Pessoa com funções', 'documento' => '52998224725',
            'funcoes' => ['motorista', 'cliente'],
        ])->assertRedirect();

        $this->assertDatabaseCount('pessoas_funcoes', 2);
    }

    public function test_consulta_user_cannot_create_person(): void
    {
        [$user] = $this->contexto(PerfilAcesso::Consulta);

        $this->actingAs($user)->post(route('pessoas.store'), [
            'tipo' => 'fisica', 'nome' => 'Sem permissão', 'documento' => '52998224725',
        ])->assertForbidden();
    }

    public function test_authorized_user_can_edit_only_local_functions_and_status(): void
    {
        Transportadora::factory()->create();
        [$user, $transportadora] = $this->contexto(PerfilAcesso::Operador);
        $pessoa = Pessoa::factory()->create(['nome' => 'Nome compartilhado', 'documento' => '52998224725']);
        $pessoaFiller = Pessoa::factory()->create(['documento' => '11222333000181']);
        TransportadoraPessoa::factory()->for($transportadora)->for($pessoaFiller)->create();
        $vinculoPessoa = TransportadoraPessoa::factory()->for($transportadora)->for($pessoa)->create();
        $vinculoUsuario = $user->vinculosTransportadoras()->firstOrFail();

        $this->assertNotSame($vinculoUsuario->id, $vinculoPessoa->id);
        $this->assertNotSame($vinculoUsuario->id, $vinculoPessoa->transportadora_id);

        $this->actingAs($user)->put(route('pessoas.update', $vinculoPessoa), [
            'ativo' => false,
            'funcoes' => ['cliente'],
            'nome' => 'Tentativa de alterar dado global',
            'documento' => '11222333000181',
        ])->assertRedirect();

        $this->assertDatabaseHas('transportadoras_pessoas', ['id' => $vinculoPessoa->id, 'ativo' => false]);
        $this->assertDatabaseHas('pessoas', ['id' => $pessoa->id, 'nome' => 'Nome compartilhado', 'documento' => '52998224725']);
        $this->assertDatabaseHas('pessoas_funcoes', ['transportadora_pessoa_id' => $vinculoPessoa->id, 'funcao' => 'cliente']);
    }

    public function test_context_switch_is_required_before_editing_a_person(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A', PerfilAcesso::Administrador);
        [$segunda, $filialB] = $this->criarVinculo($user, 'B', PerfilAcesso::Administrador);
        $pessoa = Pessoa::factory()->create(['documento' => '52998224725']);
        $vinculoPessoa = TransportadoraPessoa::factory()->for($segunda)->for($pessoa)->create();

        $this->selecionarContexto($user, $primeira, $filialA);
        $this->actingAs($user)->put(route('pessoas.update', $vinculoPessoa), ['ativo' => false, 'funcoes' => []])->assertForbidden();
        $this->assertDatabaseHas('transportadoras_pessoas', ['id' => $vinculoPessoa->id, 'ativo' => true]);

        $this->selecionarContexto($user, $segunda, $filialB);
        $this->actingAs($user)->put(route('pessoas.update', $vinculoPessoa), ['ativo' => false, 'funcoes' => []])->assertRedirect();
        $this->assertDatabaseHas('transportadoras_pessoas', ['id' => $vinculoPessoa->id, 'ativo' => false]);
    }

    public function test_direct_edit_url_for_person_from_another_transportadora_is_forbidden(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A', PerfilAcesso::Gerente);
        [$segunda] = $this->criarVinculo($user, 'B', PerfilAcesso::Gerente);
        $pessoa = Pessoa::factory()->create(['documento' => '52998224725']);
        TransportadoraPessoa::factory()->for($segunda)->for($pessoa)->create();

        $this->selecionarContexto($user, $primeira, $filialA);
        $this->actingAs($user)->get(route('pessoas.edit', $pessoa))->assertForbidden();
        $this->actingAs($user)->get(route('pessoas.show', $pessoa))->assertForbidden();
    }

    public function test_inactive_local_link_is_not_listed_or_operationally_accessible(): void
    {
        [$user, $transportadora, $filial] = $this->contexto(PerfilAcesso::Administrador);
        $this->selecionarContexto($user, $transportadora, $filial);
        $pessoa = Pessoa::factory()->create();
        $vinculo = TransportadoraPessoa::factory()->for($transportadora)->for($pessoa)->create(['ativo' => false]);

        $this->actingAs($user)->get(route('pessoas.index'))->assertOk()->assertInertia(fn ($page) => $page->where('pessoas.data', []));
        $this->assertFalse($vinculo->ativo);
    }

    public function test_person_from_another_transportadora_is_not_exposed_or_accessible(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A');
        [$segunda, $filialB] = $this->criarVinculo($user, 'B');
        $this->selecionarContexto($user, $primeira, $filialA);
        $pessoa = Pessoa::factory()->create(['nome' => 'Cadastro privado', 'documento' => '52998224725']);
        TransportadoraPessoa::factory()->for($segunda)->for($pessoa)->create();

        $this->actingAs($user)->get(route('pessoas.pesquisar', ['tipo' => 'fisica', 'documento' => $pessoa->documento]))
            ->assertJsonPath('existe', true)
            ->assertJsonPath('pessoa.nome', null);
        $this->actingAs($user)->get(route('pessoas.show', $pessoa))->assertForbidden();
        $this->assertNotSame($filialB->id, session('contexto.filial_id'));
    }

    public function test_manipulated_link_identifier_cannot_update_another_transportadora(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A');
        [$segunda, $filialB] = $this->criarVinculo($user, 'B');
        $this->selecionarContexto($user, $primeira, $filialA);
        $pessoa = Pessoa::factory()->create();
        $vinculo = TransportadoraPessoa::factory()->for($segunda)->for($pessoa)->create();

        $this->actingAs($user)->put(route('pessoas.update', $vinculo), ['ativo' => false, 'funcoes' => []])->assertForbidden();
        $this->assertDatabaseHas('transportadoras_pessoas', ['id' => $vinculo->id, 'ativo' => true]);
        $this->assertNotSame($filialB->id, session('contexto.filial_id'));
    }

    public function test_person_with_links_cannot_be_deleted(): void
    {
        [$user, $transportadora] = $this->contexto(PerfilAcesso::Administrador);
        $pessoa = Pessoa::factory()->create();
        TransportadoraPessoa::factory()->for($transportadora)->for($pessoa)->create();

        $this->expectException(ValidationException::class);
        $pessoa->delete();
    }

    /**
     * @return array{0: User, 1: Transportadora, 2: Filial}
     */
    private function contexto(PerfilAcesso $perfil): array
    {
        $user = User::factory()->create();
        [$transportadora, $filial] = $this->criarVinculo($user, 'única', $perfil);
        $this->selecionarContexto($user, $transportadora, $filial);

        return [$user, $transportadora, $filial];
    }

    /**
     * @return array{0: Transportadora, 1: Filial}
     */
    private function criarVinculo(User $user, string $sufixo, PerfilAcesso $perfil = PerfilAcesso::Administrador): array
    {
        $transportadora = Transportadora::factory()->create(['nome_fantasia' => 'Transportadora '.$sufixo]);
        $filial = Filial::factory()->for($transportadora)->create(['ativo' => true]);
        $vinculo = UsuarioTransportadora::factory()->for($user, 'usuario')->for($transportadora)->create(['perfil' => $perfil, 'ativo' => true]);
        $vinculo->usuarioFiliais()->create(['filial_id' => $filial->id]);

        return [$transportadora, $filial];
    }

    private function selecionarContexto(User $user, Transportadora $transportadora, Filial $filial): void
    {
        $this->actingAs($user)->post(route('contexto.selecionar.store'), ['transportadora_id' => $transportadora->id, 'filial_id' => $filial->id]);
    }
}
