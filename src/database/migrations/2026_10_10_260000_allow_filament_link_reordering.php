<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table) {
            // Filament reuses positions while saving a reorder, before the old value is released.
            $table->dropUnique('enlaces_pagina_pagina_id_posicion_unique');
        });
    }

    public function down(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table) {
            $table->unique(['pagina_id', 'posicion']);
        });
    }
};
