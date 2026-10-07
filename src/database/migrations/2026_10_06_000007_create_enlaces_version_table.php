<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enlaces_version', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('version_id')->constrained('versiones_pagina')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('etiqueta');
            $table->text('url');
            $table->integer('posicion');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['version_id', 'posicion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enlaces_version');
    }
};
