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
        Schema::create('conjuntos_transporte', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transportadora_id')->constrained('transportadoras')->restrictOnDelete();
            $table->foreignId('motorista_pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->foreignId('transportador_pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->foreignId('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->string('tipo_composicao', 30);
            $table->string('tipo_carroceria', 30);
            $table->foreignId('conta_bancaria_id')->constrained('contas_bancarias')->restrictOnDelete();
            $table->boolean('ativo')->default(true);
            $table->index(['transportadora_id', 'ativo']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conjuntos_transporte');
    }
};
