<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albumes_paginas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nombre', 120);
            $table->date('fecha_ruta')->nullable();
            $table->string('sector', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('album_pagina', function (Blueprint $table): void {
            $table->foreignUuid('album_id')->constrained('albumes_paginas')->cascadeOnDelete();
            $table->foreignUuid('pagina_id')->constrained('paginas')->cascadeOnDelete();
            $table->primary(['album_id', 'pagina_id']);
            $table->index(['pagina_id', 'album_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('album_pagina');
        Schema::dropIfExists('albumes_paginas');
    }
};
