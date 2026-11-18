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
        Schema::create('ordens_carregamento', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('numero');
            $table->foreignId('transportadora_id')->constrained('transportadoras')->restrictOnDelete();
            $table->foreignId('filial_id')->constrained('filiais')->restrictOnDelete();
            $table->foreignId('conjunto_transporte_id')->nullable()->constrained('conjuntos_transporte')->restrictOnDelete();
            $table->foreignId('cliente_pessoa_id')->nullable()->constrained('pessoas')->restrictOnDelete();
            $table->string('situacao', 20)->default('rascunho');
            $table->string('produto')->nullable();
            $table->decimal('quantidade', 15, 3)->nullable();
            $table->string('unidade_medida', 30)->nullable();
            $table->string('origem_cidade')->nullable();
            $table->char('origem_uf', 2)->nullable();
            $table->string('local_carregamento')->nullable();
            $table->date('data_carregamento')->nullable();
            $table->string('destino_cidade')->nullable();
            $table->char('destino_uf', 2)->nullable();
            $table->string('local_descarga')->nullable();
            $table->date('data_previsao_entrega')->nullable();
            $table->decimal('valor_frete', 15, 2)->nullable();
            $table->decimal('valor_adiantamento', 15, 2)->nullable();
            $table->decimal('valor_saldo', 15, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('situacao_alterada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('situacao_alterada_em')->nullable();
            $table->string('snapshot_motorista_nome')->nullable();
            $table->string('snapshot_transportador_nome')->nullable();
            $table->string('snapshot_veiculo_placa')->nullable();
            $table->json('snapshot_implementos_placas')->nullable();
            $table->string('snapshot_cliente_nome')->nullable();
            $table->text('snapshot_conta_pagamento')->nullable();
            $table->timestamps();
            $table->unique(['transportadora_id', 'numero']);
            $table->index(['transportadora_id', 'filial_id', 'situacao']);
            $table->index(['transportadora_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordens_carregamento');
    }
};
