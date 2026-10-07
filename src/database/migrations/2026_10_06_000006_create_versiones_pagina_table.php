<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versiones_pagina', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pagina_id')->constrained('paginas')->cascadeOnDelete();
            $table->integer('numero');
            $table->string('estado')->default('borrador'); // 'borrador', 'cerrada'
            $table->string('nombre_publico');
            $table->text('descripcion')->nullable();
            $table->string('direccion_publica')->nullable();
            $table->string('telefono_publico')->nullable();
            $table->string('correo_publico')->nullable();
            $table->foreignUuid('logo_archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->foreignUuid('favicon_archivo_id')->nullable()->constrained('archivos')->nullOnDelete();
            $table->string('tema')->default('default');
            $table->string('color_fondo')->default('#ffffff');
            $table->string('color_texto')->default('#000000');
            $table->string('color_boton')->default('#111827');
            $table->string('color_texto_boton')->default('#ffffff');
            $table->integer('revision')->default(1);
            $table->integer('aprobado_revision')->nullable();
            $table->timestamp('aprobado_en')->nullable();
            $table->string('aprobado_canal')->nullable();
            $table->text('aprobado_nota')->nullable();
            $table->foreignUuid('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('creado_por')->constrained('users');
            $table->timestamp('cerrada_en')->nullable();
            $table->timestamp('publicada_en')->nullable();
            $table->foreignUuid('publicada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pagina_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versiones_pagina');
    }
};
