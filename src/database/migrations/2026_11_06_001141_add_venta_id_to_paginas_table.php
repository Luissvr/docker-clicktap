<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paginas', function (Blueprint $table): void {
            $table->uuid('venta_id')->nullable()->unique();
        });
        DB::table('paginas')->update([
            'venta_id' => DB::table('ventas')->select('id')->whereColumn('ventas.pagina_id', 'paginas.id')->limit(1),
        ]);
    }

    public function down(): void
    {
        Schema::table('paginas', function (Blueprint $table): void {
            $table->dropColumn('venta_id');
        });
    }
};
