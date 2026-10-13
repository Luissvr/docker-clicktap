<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->preserveSqliteObjects(fn () => $this->addColumns());
    }

    private function addColumns(): void
    {
        foreach (['clientes', 'locales', 'paginas'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedBigInteger('revision')->default(1);
                $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('modificado_por')->nullable()->constrained('users')->nullOnDelete();
            });
        }
        Schema::table('paginas', function (Blueprint $table): void {
            $table->string('logo_disk')->default('public');
        });
    }

    public function down(): void
    {
        $this->preserveSqliteObjects(fn () => $this->removeColumns());
    }

    private function removeColumns(): void
    {
        Schema::table('paginas', function (Blueprint $table): void {
            $table->dropColumn('logo_disk');
        });
        foreach (['paginas', 'locales', 'clientes'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('creado_por');
                $table->dropConstrainedForeignId('modificado_por');
                $table->dropColumn('revision');
            });
        }
    }

    private function preserveSqliteObjects(Closure $operation): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $operation();

            return;
        }
        $triggers = DB::select("SELECT type, name, sql FROM sqlite_master WHERE type IN ('trigger', 'index') AND sql IS NOT NULL");
        foreach ($triggers as $trigger) {
            DB::statement('DROP '.strtoupper($trigger->type).' "'.str_replace('"', '""', $trigger->name).'"');
        }
        $operation();
        foreach ($triggers as $trigger) {
            DB::unprepared($trigger->sql);
        }
    }
};
