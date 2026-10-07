<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alias_pagina', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pagina_id')->constrained('paginas')->cascadeOnDelete();
            $table->string('ruta')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alias_pagina');
    }
};
