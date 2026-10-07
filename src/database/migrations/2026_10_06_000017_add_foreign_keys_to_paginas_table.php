<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paginas', function (Blueprint $table) {
            $table->foreign('version_publicada_id')->references('id')->on('versiones_pagina')->nullOnDelete();
            $table->foreign('publicacion_confirmada_id')->references('id')->on('publicaciones_sitio')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('paginas', function (Blueprint $table) {
            $table->dropForeign(['version_publicada_id']);
            $table->dropForeign(['publicacion_confirmada_id']);
        });
    }
};
