<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paginas', function (Blueprint $table): void {
            if (! Schema::hasColumn('paginas', 'logo_layout')) {
                $table->string('logo_layout')->default('original')->after('logo_path');
            }

            if (! Schema::hasColumn('paginas', 'logo_width')) {
                $table->unsignedSmallInteger('logo_width')->default(160)->after('logo_layout');
            }

            if (! Schema::hasColumn('paginas', 'paleta_personalizada')) {
                $table->boolean('paleta_personalizada')->default(false)->after('logo_width');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paginas', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['logo_layout', 'logo_width', 'paleta_personalizada'],
                static fn (string $column): bool => Schema::hasColumn('paginas', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
