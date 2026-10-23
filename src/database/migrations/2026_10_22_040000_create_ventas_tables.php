<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('estado')->default('borrador');
            $table->uuid('cliente_id')->nullable();
            $table->uuid('local_id')->nullable();
            $table->uuid('pagina_id')->nullable()->unique();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmada_en')->nullable();
            $table->timestamps();
            $table->index(['estado', 'created_at']);
        });

        Schema::create('venta_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->uuid('producto_id');
            $table->uuid('pedido_id')->nullable()->unique();
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('precio_unitario_clp');
            $table->timestamps();
            $table->foreign('producto_id')->references('id')->on('inventario_productos');
            $table->foreign('pedido_id')->references('id')->on('inventario_pedidos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_items');
        Schema::dropIfExists('ventas');
    }
};
