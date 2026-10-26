<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table): void {
            if (! Schema::hasColumn('enlaces_pagina', 'banco_transferencia')) {
                $table->string('banco_transferencia')->nullable()->after('tipo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table): void {
            if (Schema::hasColumn('enlaces_pagina', 'banco_transferencia')) {
                $table->dropColumn('banco_transferencia');
            }
        });
    }
};
