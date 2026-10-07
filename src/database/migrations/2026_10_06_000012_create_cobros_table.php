<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobros', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->constrained('locales')->cascadeOnDelete();
            $table->foreignUuid('solicitud_id')->nullable()->unique()->constrained('solicitudes_cambio')->nullOnDelete();
            $table->string('concepto'); // paquete_inicial, cambio, otro
            $table->text('descripcion');
            $table->integer('monto_clp');
            $table->string('estado')->default('pendiente'); // pendiente, pagado, anulado
            $table->timestamp('emitido_en');
            $table->timestamp('pagado_en')->nullable();
            $table->string('medio_pago')->nullable();
            $table->string('referencia_pago')->nullable();
            $table->foreignUuid('pago_registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobros');
    }
};
