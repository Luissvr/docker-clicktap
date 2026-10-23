<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->string('cliente_nombre')->nullable();
            $table->string('local_nombre')->nullable();
            $table->string('forma_cobro')->nullable();
            $table->unsignedInteger('cantidad_tarjetas')->default(0);
            $table->unsignedInteger('precio_recibido_clp')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropColumn([
                'cliente_nombre',
                'local_nombre',
                'forma_cobro',
                'cantidad_tarjetas',
                'precio_recibido_clp',
            ]);
        });
    }
};
