<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalles_publicacion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('publicacion_id')->constrained('publicaciones_sitio')->cascadeOnDelete();
            $table->foreignUuid('pagina_id')->constrained('paginas')->cascadeOnDelete();
            $table->foreignUuid('version_id')->nullable()->constrained('versiones_pagina')->nullOnDelete();
            $table->string('visibilidad_objetivo')->default('publicada'); // publicada, suspendida
            $table->string('slug_snapshot');
            $table->json('aliases_snapshot')->nullable();
            $table->text('motivo_suspension')->nullable();
            $table->boolean('archivar_local_al_confirmar')->default(false);
            $table->timestamps();

            $table->unique(['publicacion_id', 'pagina_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_publicacion');
    }
};
