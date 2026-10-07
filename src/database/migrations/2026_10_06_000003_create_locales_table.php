<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nombre_interno');
            $table->string('direccion_operativa')->nullable();
            $table->text('notas_internas')->nullable();
            $table->timestamp('archivado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locales');
    }
};
