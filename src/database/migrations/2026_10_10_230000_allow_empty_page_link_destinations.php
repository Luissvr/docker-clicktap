<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table) {
            $table->text('url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('enlaces_pagina', function (Blueprint $table) {
            $table->text('url')->nullable(false)->change();
        });
    }
};
