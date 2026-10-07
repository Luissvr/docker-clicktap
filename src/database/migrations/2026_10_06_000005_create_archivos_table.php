<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pagina_id')->constrained('paginas')->cascadeOnDelete();
            $table->string('clave_privada')->unique();
            $table->string('clave_publica')->nullable()->unique();
            $table->string('nombre_original');
            $table->string('mime');
            $table->bigInteger('bytes');
            $table->integer('ancho')->nullable();
            $table->integer('alto')->nullable();
            $table->string('hash_contenido');
            $table->foreignUuid('creado_por')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos');
    }
};
