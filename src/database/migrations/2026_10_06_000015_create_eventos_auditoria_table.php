<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_auditoria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('administrador_id')->constrained('users');
            $table->string('entidad_tipo');
            $table->uuid('entidad_id');
            $table->string('accion');
            $table->timestamp('ocurrido_en');
            $table->string('resumen');
            $table->text('motivo')->nullable();
            $table->json('cambios')->nullable();
            $table->uuid('operacion_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_auditoria');
    }
};
