<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_precios', function (Blueprint $table) {
            $table->string('id')->primary()->default('global');
            $table->integer('precio_inicial_clp')->default(12990);
            $table->integer('precio_cambio_clp')->default(2990);
            $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actualizado_en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_precios');
    }
};
