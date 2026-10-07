<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicaciones_sitio', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operacion_id')->unique();
            $table->foreignUuid('solicitada_por')->constrained('users');
            $table->timestamp('solicitada_en');
            $table->string('estado')->default('preparando'); // preparando, enviada, construyendo, verificando, por_reconciliar, publicada, fallida
            $table->string('tipo'); // contenido, suspension, reactivacion, restauracion, importacion_inicial
            $table->uuid('intento_anterior_id')->nullable();
            $table->uuid('base_publicacion_id')->nullable();
            $table->string('formato_exportacion')->default('1.0');
            $table->string('manifiesto_hash');
            $table->json('manifiesto_privado');
            $table->string('codigo_revision');
            $table->string('commit_exportacion')->nullable();
            $table->string('proyecto_destino')->default('linktree-a-lo-weillo');
            $table->string('rama_destino')->default('main');
            $table->string('despliegue_remoto_id')->nullable();
            $table->string('url_despliegue')->nullable();
            $table->timestamp('ultimo_chequeo_en')->nullable();
            $table->timestamp('activada_en')->nullable();
            $table->timestamp('confirmada_en')->nullable();
            $table->string('error_codigo')->nullable();
            $table->text('error_resumen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicaciones_sitio');
    }
};
