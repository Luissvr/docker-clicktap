<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table): void {
            if (! Schema::hasColumn('enlaces_pagina', 'archivo_path')) {
                $table->string('archivo_path', 2048)->nullable()->after('url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table): void {
            if (Schema::hasColumn('enlaces_pagina', 'archivo_path')) {
                $table->dropColumn('archivo_path');
            }
        });
    }
};
