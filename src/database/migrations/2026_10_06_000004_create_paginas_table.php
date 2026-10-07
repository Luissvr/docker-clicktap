<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paginas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('local_id')->unique()->constrained('locales')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->uuid('version_publicada_id')->nullable();
            $table->timestamp('suspendida_en')->nullable();
            $table->text('motivo_suspension')->nullable();
            $table->uuid('publicacion_confirmada_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paginas');
    }
};
