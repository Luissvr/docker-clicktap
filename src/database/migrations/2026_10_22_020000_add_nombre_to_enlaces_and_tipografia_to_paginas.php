<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table): void {
            if (! Schema::hasColumn('enlaces_pagina', 'nombre')) {
                $table->string('nombre')->nullable()->after('tipo');
            }
        });

        Schema::table('paginas', function (Blueprint $table): void {
            if (! Schema::hasColumn('paginas', 'tipografia')) {
                $table->string('tipografia')->default('inter')->after('tema');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table): void {
            if (Schema::hasColumn('enlaces_pagina', 'nombre')) {
                $table->dropColumn('nombre');
            }
        });

        Schema::table('paginas', function (Blueprint $table): void {
            if (Schema::hasColumn('paginas', 'tipografia')) {
                $table->dropColumn('tipografia');
            }
        });
    }
};
