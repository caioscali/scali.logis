<?php

namespace Tests\Feature;

use App\Domain\Organizations\Models\Filial;
use App\Domain\Organizations\Models\GrupoEmpresarial;
use App\Domain\Organizations\Models\Transportadora;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizationStructureTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.platform_admin_emails' => ['admin@scali.dev']]);
    }

    public function test_platform_administrator_can_create_a_group(): void
    {
        $admin = User::factory()->create(['email' => 'admin@scali.dev', 'email_verified_at' => now()]);
        $response = $this->actingAs($admin)->post(route('admin.grupos-empresariais.store'), ['nome' => 'Grupo Scali']);

        $response->assertRedirect(route('admin.grupos-empresariais.index'));
        $this->assertDatabaseHas('grupos_empresariais', ['nome' => 'Grupo Scali', 'ativo' => true]);
    }

    public function test_non_platform_users_are_forbidden_from_administration(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get(route('admin.grupos-empresariais.index'))->assertForbidden();
    }

    public function test_transportadora_creation_creates_its_matrix_in_one_transaction(): void
    {
        $admin = User::factory()->create(['email' => 'admin@scali.dev', 'email_verified_at' => now()]);
        $grupo = GrupoEmpresarial::factory()->create();
        $response = $this->actingAs($admin)->post(route('admin.transportadoras.store'), [
            'grupo_empresarial_id' => $grupo->id,
            'razao_social' => 'Scali Logis LTDA',
            'nome_fantasia' => 'Scali Logis',
            'cnpj' => '11.222.333/0001-81',
            'filial_matriz' => [
                'nome' => 'Matriz São Paulo',
                'cnpj' => '11.222.333/0001-81',
                'cidade' => 'São Paulo',
                'uf' => 'sp',
            ],
        ]);

        $transportadora = Transportadora::firstOrFail();
        $response->assertRedirect(route('admin.transportadoras.show', $transportadora));
        $this->assertSame($grupo->id, $transportadora->grupoEmpresarial->id);
        $this->assertSame('11222333000181', $transportadora->cnpj);
        $this->assertTrue($transportadora->matriz()->exists());
        $this->assertSame('SP', $transportadora->matriz()->firstOrFail()->uf);
    }

    public function test_relationships_are_available_between_entities(): void
    {
        $grupo = GrupoEmpresarial::factory()->create();
        $transportadora = Transportadora::factory()->for($grupo)->create(['cnpj' => '11222333000181']);
        $filial = Filial::factory()->for($transportadora)->create(['matriz' => true]);

        $this->assertTrue($grupo->transportadoras->contains($transportadora));
        $this->assertTrue($transportadora->filiais->contains($filial));
        $this->assertSame($transportadora->id, $filial->transportadora->id);
    }

    public function test_duplicate_cnpj_in_the_same_entity_is_rejected(): void
    {
        $admin = User::factory()->create(['email' => 'admin@scali.dev', 'email_verified_at' => now()]);
        $grupo = GrupoEmpresarial::factory()->create();
        Transportadora::factory()->for($grupo)->create(['cnpj' => '11222333000181']);

        $response = $this->actingAs($admin)->post(route('admin.transportadoras.store'), [
            'grupo_empresarial_id' => $grupo->id,
            'razao_social' => 'Outra Razão Social',
            'nome_fantasia' => 'Outra Transportadora',
            'cnpj' => '11.222.333/0001-81',
            'filial_matriz' => [
                'nome' => 'Outra Matriz',
                'cnpj' => null,
                'cidade' => 'São Paulo',
                'uf' => 'SP',
            ],
        ]);

        $response->assertSessionHasErrors('cnpj');
        $this->assertDatabaseCount('transportadoras', 1);
    }

    public function test_two_matrices_for_one_transportadora_are_rejected_by_database(): void
    {
        $transportadora = Transportadora::factory()->create();
        Filial::factory()->for($transportadora)->create(['matriz' => true]);

        $this->expectException(ValidationException::class);
        Filial::factory()->for($transportadora)->create(['matriz' => true]);
    }

    public function test_dependencies_are_preserved_when_parent_deletion_is_attempted(): void
    {
        $admin = User::factory()->create(['email' => 'admin@scali.dev', 'email_verified_at' => now()]);
        $grupo = GrupoEmpresarial::factory()->create();
        $transportadora = Transportadora::factory()->for($grupo)->create();
        Filial::factory()->for($transportadora)->create();

        $this->actingAs($admin)->delete(route('admin.grupos-empresariais.destroy', $grupo))
            ->assertSessionHasErrors('grupo');
        $this->actingAs($admin)->delete(route('admin.transportadoras.destroy', $transportadora))
            ->assertSessionHasErrors('transportadora');
        $this->assertDatabaseHas('grupos_empresariais', ['id' => $grupo->id]);
        $this->assertDatabaseHas('transportadoras', ['id' => $transportadora->id]);
        $this->assertDatabaseHas('filiais', ['transportadora_id' => $transportadora->id]);
    }
}
