<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->string('nombre_contacto')->nullable();
            $table->string('telefono_contacto')->nullable();
            $table->string('correo_contacto')->nullable();
            $table->text('notas_internas')->nullable();
            $table->timestamp('archivado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
