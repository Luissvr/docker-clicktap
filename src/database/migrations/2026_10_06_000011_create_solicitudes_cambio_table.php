<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_cambio', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('locales')->cascadeOnDelete();
            $table->text('descripcion');
            $table->string('canal_recepcion')->nullable();
            $table->string('estado')->default('pendiente'); // pendiente, en_proceso, en_revision, completada, cancelada
            $table->string('modalidad_cobro')->default('por_definir'); // por_definir, gratuito, con_cobro
            $table->text('motivo_gratuidad')->nullable();
            $table->timestamp('recibida_en');
            $table->timestamp('iniciada_en')->nullable();
            $table->timestamp('completada_en')->nullable();
            $table->timestamp('cancelada_en')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->foreignUuid('version_resultante_id')->nullable()->constrained('versiones_pagina')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cambio');
    }
};
