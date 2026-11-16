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
        Schema::create('transportadoras_contas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transportadora_id')->constrained('transportadoras')->restrictOnDelete();
            $table->foreignId('conta_bancaria_id')->constrained('contas_bancarias')->restrictOnDelete();
            $table->boolean('ativo')->default(true);
            $table->unique(['transportadora_id', 'conta_bancaria_id']);
            $table->index(['transportadora_id', 'ativo']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transportadoras_contas_bancarias');
    }
};
