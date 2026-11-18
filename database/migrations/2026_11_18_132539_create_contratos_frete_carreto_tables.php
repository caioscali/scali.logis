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
        Schema::create('contratos_frete_carreto', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transportadora_id')->constrained('transportadoras')->restrictOnDelete();
            $table->foreignId('filial_id')->constrained('filiais')->restrictOnDelete();
            $table->foreignId('ordem_carregamento_id')->constrained('ordens_carregamento')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->string('numero_contrato', 100);
            $table->decimal('valor_referencia', 15, 2);
            $table->decimal('valor_contrato', 15, 2);
            $table->string('situacao_conferencia', 20)->default('pendente');
            $table->foreignId('conferido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('conferido_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['transportadora_id', 'numero_contrato']);
            $table->unique(['ordem_carregamento_id', 'tipo']);
            $table->index(['transportadora_id', 'filial_id', 'situacao_conferencia']);
        });

        Schema::create('historicos_contratos_frete_carreto', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrato_frete_carreto_id')->constrained('contratos_frete_carreto')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('numero_contrato', 100);
            $table->decimal('valor_referencia', 15, 2);
            $table->decimal('valor_contrato', 15, 2);
            $table->string('situacao_conferencia', 20);
            $table->text('observacoes')->nullable();
            $table->foreignId('alterado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('alterado_em');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historicos_contratos_frete_carreto');
        Schema::dropIfExists('contratos_frete_carreto');
    }
};
