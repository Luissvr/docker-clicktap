<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table) {
            $table->boolean('copiar_texto')->default(false);
            $table->text('texto_copia')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table) {
            $table->dropColumn(['copiar_texto', 'texto_copia']);
        });
    }
};
