<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('filiais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transportadora_id')->constrained('transportadoras')->restrictOnDelete();
            $table->string('nome');
            $table->string('cnpj', 14)->nullable()->unique();
            $table->string('cidade');
            $table->char('uf', 2);
            $table->boolean('matriz')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX filiais_uma_matriz_por_transportadora ON filiais (transportadora_id) WHERE matriz = true');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS filiais_uma_matriz_por_transportadora');
        Schema::dropIfExists('filiais');
    }
};
