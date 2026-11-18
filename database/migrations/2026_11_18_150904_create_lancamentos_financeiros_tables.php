<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lancamentos_financeiros', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transportadora_id')->constrained('transportadoras')->restrictOnDelete();
            $table->foreignId('filial_id')->constrained('filiais')->restrictOnDelete();
            $table->foreignId('ordem_carregamento_id')->constrained('ordens_carregamento')->restrictOnDelete();
            $table->foreignId('contrato_frete_carreto_id')->constrained('contratos_frete_carreto')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->date('competencia');
            $table->string('numero_cfc', 100);
            $table->decimal('valor', 15, 2);
            $table->string('situacao', 20)->default('pendente');
            $table->text('observacoes')->nullable();
            $table->foreignId('aprovado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprovado_em')->nullable();
            $table->foreignId('rejeitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejeitado_em')->nullable();
            $table->text('motivo_rejeicao')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('contrato_frete_carreto_id');
            $table->index(['transportadora_id', 'filial_id', 'competencia', 'situacao']);
            $table->index(['transportadora_id', 'numero_cfc']);
        });

        Schema::create('historicos_lancamentos_financeiros', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lancamento_financeiro_id')->constrained('lancamentos_financeiros')->cascadeOnDelete();
            $table->string('acao', 40);
            $table->string('situacao_anterior', 20)->nullable();
            $table->string('situacao_nova', 20)->nullable();
            $table->decimal('valor_anterior', 15, 2)->nullable();
            $table->decimal('valor_novo', 15, 2)->nullable();
            $table->date('competencia_anterior')->nullable();
            $table->date('competencia_nova')->nullable();
            $table->text('motivo')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ocorrido_em');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historicos_lancamentos_financeiros');
        Schema::dropIfExists('lancamentos_financeiros');
    }
};
