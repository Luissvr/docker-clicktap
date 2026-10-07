<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_placa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('placa_id')->constrained('placas_nfc')->cascadeOnDelete();
            $table->foreignUuid('local_id')->constrained('locales')->cascadeOnDelete();
            $table->timestamp('inicio_en');
            $table->timestamp('fin_en')->nullable();
            $table->foreignUuid('asignado_por')->constrained('users');
            $table->string('motivo_inicio');
            $table->string('motivo_fin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_placa');
    }
};
