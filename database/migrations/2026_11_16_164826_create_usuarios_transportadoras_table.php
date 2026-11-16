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
        Schema::create('usuarios_transportadoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('transportadora_id')->constrained('transportadoras')->restrictOnDelete();
            $table->string('perfil', 20);
            $table->boolean('ativo')->default(true);
            $table->unique(['user_id', 'transportadora_id']);
            $table->index(['transportadora_id', 'ativo']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios_transportadoras');
    }
};
