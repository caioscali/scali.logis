<?php

namespace Tests\Feature;

use App\Domain\Organizations\Enums\PerfilAcesso;
use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\Transportadora;
use App\Domain\Organizations\Models\UsuarioTransportadora;
use App\Domain\Registry\Models\Implemento;
use App\Domain\Registry\Models\TransportadoraImplemento;
use App\Domain\Registry\Models\TransportadoraVeiculo;
use App\Domain\Registry\Models\Veiculo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EquipamentosTest extends TestCase
{
    use DatabaseMigrations;

    public function test_vehicle_accepts_old_and_mercosul_plate_formats_and_normalizes_them(): void
    {
        [$user] = $this->contexto();

        $this->actingAs($user)->post(route('veiculos.store'), ['placa' => 'abc-1234', 'cidade' => 'São Paulo'])->assertRedirect();
        $this->actingAs($user)->post(route('veiculos.store'), ['placa' => 'def1g23', 'cidade' => 'Curitiba'])->assertRedirect();

        $this->assertDatabaseHas('veiculos', ['placa' => 'ABC1234', 'cidade' => 'São Paulo']);
        $this->assertDatabaseHas('veiculos', ['placa' => 'DEF1G23', 'cidade' => 'Curitiba']);
    }

    public function test_implemento_is_created_with_normalized_plate(): void
    {
        [$user] = $this->contexto();

        $this->actingAs($user)->post(route('implementos.store'), ['placa' => 'ghi-9012'])->assertRedirect();

        $this->assertDatabaseHas('implementos', ['placa' => 'GHI9012']);
    }

    public function test_invalid_plate_is_rejected(): void
    {
        [$user] = $this->contexto();

        $this->actingAs($user)->post(route('veiculos.store'), ['placa' => 'ABC123', 'cidade' => 'São Paulo'])->assertSessionHasErrors('placa');
        $this->actingAs($user)->post(route('implementos.store'), ['placa' => '1234567'])->assertSessionHasErrors('placa');
    }

    public function test_duplicate_plate_in_same_category_is_rejected(): void
    {
        [$user] = $this->contexto();
        $dados = ['placa' => 'ABC1234', 'cidade' => 'São Paulo'];

        $this->actingAs($user)->post(route('veiculos.store'), $dados)->assertRedirect();
        $this->actingAs($user)->post(route('veiculos.store'), $dados)->assertSessionHasErrors('placa');
        $this->assertDatabaseCount('veiculos', 1);
        $this->assertDatabaseCount('transportadoras_veiculos', 1);
    }

    public function test_same_plate_cannot_be_used_as_vehicle_and_implemento(): void
    {
        [$user] = $this->contexto();
        $this->actingAs($user)->post(route('veiculos.store'), ['placa' => 'ABC1234', 'cidade' => 'São Paulo'])->assertRedirect();

        $this->actingAs($user)->post(route('implementos.store'), ['placa' => 'ABC1234'])->assertSessionHasErrors('placa');
        $this->assertDatabaseCount('implementos', 0);
    }

    public function test_existing_equipment_can_be_adopted_by_two_transportadoras_without_global_overwrite(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A');
        [$segunda, $filialB] = $this->criarVinculo($user, 'B');
        $this->selecionarContexto($user, $primeira, $filialA);

        $this->actingAs($user)->post(route('veiculos.store'), ['placa' => 'ABC1234', 'cidade' => 'São Paulo'])->assertRedirect();
        $this->selecionarContexto($user, $segunda, $filialB);
        $this->actingAs($user)->post(route('veiculos.store'), ['placa' => 'ABC1234', 'cidade' => 'Outra cidade'])->assertRedirect();

        $this->assertSame(1, Veiculo::query()->count());
        $this->assertSame(2, TransportadoraVeiculo::query()->count());
        $this->assertDatabaseHas('veiculos', ['placa' => 'ABC1234', 'cidade' => 'São Paulo']);
    }

    public function test_duplicate_local_adoption_is_rejected(): void
    {
        [$user] = $this->contexto();
        $dados = ['placa' => 'GHI9012'];

        $this->actingAs($user)->post(route('implementos.store'), $dados)->assertRedirect();
        $this->actingAs($user)->post(route('implementos.store'), $dados)->assertSessionHasErrors('placa');
        $this->assertDatabaseCount('transportadoras_implementos', 1);
    }

    public function test_consulta_user_cannot_adopt_equipment(): void
    {
        [$user] = $this->contexto(PerfilAcesso::Consulta);

        $this->actingAs($user)->post(route('veiculos.store'), ['placa' => 'ABC1234', 'cidade' => 'São Paulo'])->assertForbidden();
        $this->actingAs($user)->post(route('implementos.store'), ['placa' => 'GHI9012'])->assertForbidden();
    }

    public function test_inactive_local_link_can_be_disabled_without_deleting_global_equipment(): void
    {
        [$user, $transportadora] = $this->contexto();
        $veiculo = Veiculo::factory()->create(['placa' => 'ABC1234']);
        $vinculo = TransportadoraVeiculo::factory()->for($transportadora)->for($veiculo)->create();

        $this->actingAs($user)->put(route('veiculos.update', $vinculo), ['ativo' => false])->assertRedirect();

        $this->assertDatabaseHas('veiculos', ['id' => $veiculo->id, 'placa' => 'ABC1234']);
        $this->assertDatabaseHas('transportadoras_veiculos', ['id' => $vinculo->id, 'ativo' => false]);
    }

    public function test_switching_context_blocks_editing_another_transportadora(): void
    {
        $user = User::factory()->create();
        [$primeira, $filialA] = $this->criarVinculo($user, 'A');
        [$segunda, $filialB] = $this->criarVinculo($user, 'B');
        $veiculo = Veiculo::factory()->create(['placa' => 'ABC1234']);
        $vinculo = TransportadoraVeiculo::factory()->for($segunda)->for($veiculo)->create();
        $this->selecionarContexto($user, $primeira, $filialA);

        $this->actingAs($user)->put(route('veiculos.update', $vinculo), ['ativo' => false])->assertForbidden();
        $this->actingAs($user)->get(route('veiculos.show', $veiculo))->assertForbidden();
        $this->assertDatabaseHas('transportadoras_veiculos', ['id' => $vinculo->id, 'ativo' => true]);

        $this->selecionarContexto($user, $segunda, $filialB);
        $this->actingAs($user)->put(route('veiculos.update', $vinculo), ['ativo' => false])->assertRedirect();
    }

    public function test_global_search_requires_context_and_does_not_expose_unrelated_data(): void
    {
        $semVinculo = User::factory()->create();
        $this->actingAs($semVinculo)->get(route('veiculos.pesquisar', ['placa' => 'ABC1234']))->assertRedirect(route('contexto.sem-vinculo'));

        [$user, $transportadora] = $this->contexto();
        $veiculo = Veiculo::factory()->create(['placa' => 'ABC1234', 'cidade' => 'Cidade privada']);
        $outra = Transportadora::factory()->create();
        TransportadoraVeiculo::factory()->for($outra)->for($veiculo)->create();

        $this->actingAs($user)->get(route('veiculos.pesquisar', ['placa' => 'ABC1234']))
            ->assertJsonPath('existe', true)
            ->assertJsonPath('equipamento.cidade', null)
            ->assertJsonPath('equipamento.ja_vinculado', false);
        $this->assertNotSame($transportadora->id, $outra->id);
    }

    public function test_equipment_with_dependencies_cannot_be_deleted(): void
    {
        [$user, $transportadora] = $this->contexto();
        $veiculo = Veiculo::factory()->create(['placa' => 'ABC1234']);
        $implemento = Implemento::factory()->create(['placa' => 'GHI9012']);
        TransportadoraVeiculo::factory()->for($transportadora)->for($veiculo)->create();
        TransportadoraImplemento::factory()->for($transportadora)->for($implemento)->create();

        $this->expectException(ValidationException::class);
        $veiculo->delete();
    }

    /**
     * @return array{0: User, 1: Transportadora, 2: Filial}
     */
    private function contexto(PerfilAcesso $perfil = PerfilAcesso::Administrador): array
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
