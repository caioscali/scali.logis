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
        Schema::table('ordens_carregamento', function (Blueprint $table): void {
            $table->string('pdf_caminho')->nullable();
            $table->timestamp('pdf_gerado_em')->nullable();
            $table->foreignId('pdf_gerado_por')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordens_carregamento', function (Blueprint $table): void {
            $table->dropForeign(['pdf_gerado_por']);
            $table->dropColumn(['pdf_caminho', 'pdf_gerado_em', 'pdf_gerado_por']);
        });
    }
};
