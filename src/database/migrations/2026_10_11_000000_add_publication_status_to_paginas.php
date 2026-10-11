<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paginas', function (Blueprint $table): void {
            $table->string('publicacion_estado')->default('sin_publicar');
            $table->string('publicacion_commit')->nullable();
            $table->text('publicacion_error')->nullable();
            $table->timestamp('publicada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('paginas', function (Blueprint $table): void {
            $table->dropColumn(['publicacion_estado', 'publicacion_commit', 'publicacion_error', 'publicada_en']);
        });
    }
};
