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
        Schema::create('pessoas_funcoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transportadora_pessoa_id')->constrained('transportadoras_pessoas')->restrictOnDelete();
            $table->string('funcao', 20);
            $table->unique(['transportadora_pessoa_id', 'funcao']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pessoas_funcoes');
    }
};
