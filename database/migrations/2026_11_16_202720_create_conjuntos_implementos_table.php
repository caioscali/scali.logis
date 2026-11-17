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
        Schema::create('conjuntos_implementos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conjunto_transporte_id')->constrained('conjuntos_transporte')->restrictOnDelete();
            $table->foreignId('implemento_id')->constrained('implementos')->restrictOnDelete();
            $table->unsignedSmallInteger('posicao');
            $table->unique(['conjunto_transporte_id', 'implemento_id']);
            $table->unique(['conjunto_transporte_id', 'posicao']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conjuntos_implementos');
    }
};
