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
        Schema::create('contas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->string('banco_codigo', 10)->nullable();
            $table->string('banco_nome')->nullable();
            $table->text('agencia')->nullable();
            $table->text('numero_conta')->nullable();
            $table->text('digito_conta')->nullable();
            $table->string('tipo_conta', 15)->nullable();
            $table->string('tipo_chave_pix', 15)->nullable();
            $table->text('chave_pix')->nullable();
            $table->index('pessoa_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contas_bancarias');
    }
};
