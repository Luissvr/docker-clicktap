<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conflicts = DB::table('ventas')->whereNotNull('pagina_id')
            ->whereIn('estado', ['confirmada', 'rechazada', 'cancelacion_pendiente'])
            ->select('pagina_id')->groupBy('pagina_id')->havingRaw('COUNT(DISTINCT estado) > 1')->first() !== null;
        if ($conflicts) {
            throw new RuntimeException('Hay ventas contradictorias para una página. Concílialas antes de migrar.');
        }

        Schema::table('paginas', function (Blueprint $table): void {
            $table->string('estado_comercial', 24)->nullable()->default('por_vender')->index();
        });
        foreach (['confirmada' => 'vendida', 'rechazada' => 'rechazada', 'cancelacion_pendiente' => null] as $saleState => $pageState) {
            DB::table('paginas')->whereIn('id', DB::table('ventas')->select('pagina_id')->where('estado', $saleState))
                ->update(['estado_comercial' => $pageState]);
        }
    }

    public function down(): void
    {
        Schema::table('paginas', function (Blueprint $table): void {
            $table->dropColumn('estado_comercial');
        });
    }
};
