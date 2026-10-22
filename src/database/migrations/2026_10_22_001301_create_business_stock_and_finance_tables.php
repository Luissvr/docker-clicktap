<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_productos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku')->unique();
            $table->string('nombre');
            $table->string('tipo');
            $table->boolean('vendible')->default(true);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('precio_clp')->nullable();
            $table->unsignedInteger('minimo')->default(0);
            $table->unsignedInteger('existencia')->default(0);
            $table->unsignedInteger('reservado_pedidos')->default(0);
            $table->unsignedInteger('reservado_produccion')->default(0);
            $table->timestamps();
        });

        Schema::create('inventario_compras', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('producto_id')->constrained('inventario_productos');
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('costo_total_clp')->nullable();
            $table->unsignedInteger('pagado_clp')->default(0);
            $table->string('proveedor')->nullable();
            $table->string('documento')->nullable();
            $table->date('fecha');
            $table->string('clave_operacion')->unique();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventario_lotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('producto_id')->constrained('inventario_productos');
            $table->foreignUuid('compra_id')->nullable()->constrained('inventario_compras');
            $table->unsignedInteger('cantidad_inicial');
            $table->unsignedInteger('cantidad_restante');
            $table->unsignedInteger('costo_restante_clp')->nullable();
            $table->timestamp('recibido_en');
            $table->timestamps();
            $table->index(['producto_id', 'recibido_en']);
        });

        Schema::create('inventario_pedidos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('producto_id')->constrained('inventario_productos');
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('precio_unitario_clp');
            $table->unsignedInteger('costo_vendido_clp')->nullable();
            $table->string('cliente')->nullable();
            $table->string('canal')->default('directo');
            $table->string('referencia_externa')->nullable();
            $table->string('estado')->default('reservado');
            $table->timestamp('reservado_hasta')->nullable();
            $table->timestamp('entregado_en')->nullable();
            $table->string('clave_operacion')->unique();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['estado', 'reservado_hasta']);
        });

        Schema::create('caja_movimientos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tipo');
            $table->string('categoria');
            $table->integer('monto_clp');
            $table->date('fecha');
            $table->string('descripcion');
            $table->string('medio_pago')->nullable();
            $table->string('documento')->nullable();
            $table->foreignUuid('pedido_id')->nullable()->constrained('inventario_pedidos');
            $table->foreignUuid('compra_id')->nullable()->constrained('inventario_compras');
            $table->string('clave_operacion')->unique();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['fecha', 'tipo']);
        });

        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('producto_id')->constrained('inventario_productos');
            $table->string('tipo');
            $table->integer('cambio_existencia')->default(0);
            $table->integer('cambio_reserva_pedido')->default(0);
            $table->integer('cambio_reserva_produccion')->default(0);
            $table->unsignedInteger('costo_clp')->nullable();
            $table->foreignUuid('pedido_id')->nullable()->constrained('inventario_pedidos');
            $table->foreignUuid('compra_id')->nullable()->constrained('inventario_compras');
            $table->string('motivo')->nullable();
            $table->string('clave_operacion');
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['producto_id', 'created_at']);
            $table->index('clave_operacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
        Schema::dropIfExists('caja_movimientos');
        Schema::dropIfExists('inventario_pedidos');
        Schema::dropIfExists('inventario_lotes');
        Schema::dropIfExists('inventario_compras');
        Schema::dropIfExists('inventario_productos');
    }
};
