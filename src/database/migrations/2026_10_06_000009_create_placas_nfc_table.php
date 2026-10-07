<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placas_nfc', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo_interno')->unique();
            $table->string('estado')->default('disponible'); // disponible, preparada, programada, probada, asignada, entregada, desactivada
            $table->foreignUuid('pagina_destino_id')->nullable()->constrained('paginas')->nullOnDelete();
            $table->string('url_programada')->nullable();
            $table->timestamp('preparada_en')->nullable();
            $table->timestamp('programada_en')->nullable();
            $table->timestamp('prueba_nfc_en')->nullable();
            $table->timestamp('prueba_qr_en')->nullable();
            $table->timestamp('entregada_en')->nullable();
            $table->timestamp('desactivada_en')->nullable();
            $table->text('motivo_desactivacion')->nullable();
            $table->text('notas_internas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placas_nfc');
    }
};
