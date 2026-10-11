<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->dropSqlitePageRouteTriggers();
        }

        Schema::table('paginas', function (Blueprint $table) {
            $table->string('nombre_publico')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('direccion_publica')->nullable();
            $table->string('telefono_publico')->nullable();
            $table->string('correo_publico')->nullable();
            $table->text('logo_path')->nullable();
            $table->string('tema')->default('default');
            $table->string('color_fondo')->default('#ffffff');
            $table->string('color_texto')->default('#000000');
            $table->string('color_boton')->default('#111827');
            $table->string('color_texto_boton')->default('#ffffff');
            $table->boolean('activo')->default(true);
            $table->foreignUuid('actualizada_por')->nullable()->constrained('users')->nullOnDelete();
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->createSqlitePageRouteTriggers();
        }

        Schema::create('enlaces_pagina', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pagina_id')->constrained('paginas')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('etiqueta');
            $table->text('url');
            $table->unsignedInteger('posicion');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['pagina_id', 'posicion']);
        });

        $this->copyCurrentContent();
    }

    public function down(): void
    {
        Schema::dropIfExists('enlaces_pagina');

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->dropSqlitePageRouteTriggers();
        }

        Schema::table('paginas', function (Blueprint $table) {
            $table->dropForeign(['actualizada_por']);
            $table->dropColumn([
                'nombre_publico',
                'descripcion',
                'direccion_publica',
                'telefono_publico',
                'correo_publico',
                'logo_path',
                'tema',
                'color_fondo',
                'color_texto',
                'color_boton',
                'color_texto_boton',
                'activo',
                'actualizada_por',
            ]);
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->createSqlitePageRouteTriggers();
        }
    }

    private function copyCurrentContent(): void
    {
        foreach (DB::table('paginas')->get() as $pagina) {
            $version = $pagina->version_publicada_id
                ? DB::table('versiones_pagina')->where('id', $pagina->version_publicada_id)->first()
                : null;

            $version ??= DB::table('versiones_pagina')
                ->where('pagina_id', $pagina->id)
                ->orderByRaw("CASE WHEN estado = 'borrador' THEN 0 ELSE 1 END")
                ->orderByDesc('numero')
                ->first();

            $nombreLocal = DB::table('locales')->where('id', $pagina->local_id)->value('nombre_interno');
            $logoPath = $version?->logo_archivo_id
                ? DB::table('archivos')->where('id', $version->logo_archivo_id)->value('clave_publica')
                : null;

            DB::table('paginas')->where('id', $pagina->id)->update([
                'nombre_publico' => $version?->nombre_publico ?? $nombreLocal,
                'descripcion' => $version?->descripcion,
                'direccion_publica' => $version?->direccion_publica,
                'telefono_publico' => $version?->telefono_publico,
                'correo_publico' => $version?->correo_publico,
                'logo_path' => $logoPath,
                'tema' => $version?->tema ?? 'default',
                'color_fondo' => $version?->color_fondo ?? '#ffffff',
                'color_texto' => $version?->color_texto ?? '#000000',
                'color_boton' => $version?->color_boton ?? '#111827',
                'color_texto_boton' => $version?->color_texto_boton ?? '#ffffff',
                'activo' => $pagina->suspendida_en === null,
                'actualizada_por' => $version?->publicada_por ?? $version?->creado_por,
            ]);

            if (! $version) {
                continue;
            }

            foreach (DB::table('enlaces_version')->where('version_id', $version->id)->get() as $enlace) {
                DB::table('enlaces_pagina')->insert([
                    'id' => (string) Str::uuid(),
                    'pagina_id' => $pagina->id,
                    'tipo' => $enlace->tipo,
                    'etiqueta' => $enlace->etiqueta,
                    'url' => $enlace->url,
                    'posicion' => $enlace->posicion,
                    'activo' => $enlace->activo,
                    'created_at' => $enlace->created_at,
                    'updated_at' => $enlace->updated_at,
                ]);
            }
        }
    }

    private function dropSqlitePageRouteTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS alias_pagina_route_guard_insert;
            DROP TRIGGER IF EXISTS paginas_slug_immutable;
            DROP TRIGGER IF EXISTS paginas_route_guard_insert;
            DROP INDEX IF EXISTS paginas_slug_normalizado_unique;
            SQL);
    }

    private function createSqlitePageRouteTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE UNIQUE INDEX paginas_slug_normalizado_unique ON paginas (LOWER(slug));

            CREATE TRIGGER paginas_route_guard_insert
            BEFORE INSERT ON paginas
            BEGIN
                SELECT RAISE(ABORT, 'La ruta de la página no es válida o está reservada')
                WHERE lower(NEW.slug) IN ('404.html', '_astro', 'admin', 'api', 'build', 'favicon.ico', 'livewire', 'robots.txt', 'storage')
                    OR EXISTS (SELECT 1 FROM alias_pagina WHERE lower(ruta) = lower(NEW.slug));
            END;

            CREATE TRIGGER paginas_slug_immutable
            BEFORE UPDATE OF slug ON paginas
            WHEN NEW.slug <> OLD.slug
            BEGIN
                SELECT RAISE(ABORT, 'El slug de una página no se puede modificar');
            END;

            CREATE TRIGGER alias_pagina_route_guard_insert
            BEFORE INSERT ON alias_pagina
            BEGIN
                SELECT RAISE(ABORT, 'La ruta del alias no es válida o está reservada')
                WHERE lower(NEW.ruta) IN ('404.html', '_astro', 'admin', 'api', 'build', 'favicon.ico', 'livewire', 'robots.txt', 'storage')
                    OR EXISTS (SELECT 1 FROM paginas WHERE lower(slug) = lower(NEW.ruta));
            END;
            SQL);
    }
};
