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
        Schema::create('usuarios_filiais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_transportadora_id')->constrained('usuarios_transportadoras')->restrictOnDelete();
            $table->foreignId('filial_id')->constrained('filiais')->restrictOnDelete();
            $table->unique(['usuario_transportadora_id', 'filial_id']);
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION validar_usuario_filial_transportadora()
                RETURNS trigger AS $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM usuarios_transportadoras ut
                        JOIN filiais f ON f.transportadora_id = ut.transportadora_id
                        WHERE ut.id = NEW.usuario_transportadora_id
                          AND f.id = NEW.filial_id
                    ) THEN
                        RAISE EXCEPTION 'A filial não pertence à transportadora do vínculo';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            SQL);

            DB::statement('CREATE TRIGGER usuarios_filiais_transportadora_check BEFORE INSERT OR UPDATE ON usuarios_filiais FOR EACH ROW EXECUTE FUNCTION validar_usuario_filial_transportadora()');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS usuarios_filiais_transportadora_check ON usuarios_filiais');
            DB::statement('DROP FUNCTION IF EXISTS validar_usuario_filial_transportadora()');
        }

        Schema::dropIfExists('usuarios_filiais');
    }
};
