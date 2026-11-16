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
        Schema::create('pessoas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10);
            $table->string('nome');
            $table->string('documento', 14)->unique();
            $table->string('telefone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('cidade', 120)->nullable();
            $table->char('uf', 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pessoas');
    }
};
