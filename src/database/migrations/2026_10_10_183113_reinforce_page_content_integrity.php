<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX versiones_pagina_un_borrador_unique ON versiones_pagina (pagina_id) WHERE estado = 'borrador'");
        DB::statement('CREATE UNIQUE INDEX paginas_slug_normalizado_unique ON paginas (LOWER(slug))');
        DB::statement('CREATE UNIQUE INDEX alias_pagina_ruta_normalizada_unique ON alias_pagina (LOWER(ruta))');

        match (DB::connection()->getDriverName()) {
            'pgsql' => $this->upPostgres(),
            'sqlite' => $this->upSqlite(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::connection()->getDriverName()) {
            'pgsql' => $this->downPostgres(),
            'sqlite' => $this->downSqlite(),
            default => null,
        };

        DB::statement('DROP INDEX IF EXISTS alias_pagina_ruta_normalizada_unique');
        DB::statement('DROP INDEX IF EXISTS paginas_slug_normalizado_unique');
        DB::statement('DROP INDEX IF EXISTS versiones_pagina_un_borrador_unique');
    }

    private function upPostgres(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE versiones_pagina
                ADD CONSTRAINT versiones_pagina_numero_positivo CHECK (numero > 0),
                ADD CONSTRAINT versiones_pagina_revision_positiva CHECK (revision > 0),
                ADD CONSTRAINT versiones_pagina_estado_valido CHECK (estado IN ('borrador', 'cerrada')),
                ADD CONSTRAINT versiones_pagina_cierre_coherente CHECK (
                    (estado = 'borrador' AND cerrada_en IS NULL)
                    OR (estado = 'cerrada' AND cerrada_en IS NOT NULL)
                ),
                ADD CONSTRAINT versiones_pagina_aprobacion_coherente CHECK (
                    (aprobado_revision IS NULL AND aprobado_en IS NULL AND aprobado_canal IS NULL AND aprobado_por IS NULL)
                    OR (
                        aprobado_revision = revision
                        AND aprobado_en IS NOT NULL
                        AND aprobado_canal IS NOT NULL
                        AND aprobado_por IS NOT NULL
                    )
                );

            ALTER TABLE enlaces_version
                ADD CONSTRAINT enlaces_version_posicion_no_negativa CHECK (posicion >= 0);

            CREATE OR REPLACE FUNCTION clicktap_guard_pagina_route() RETURNS trigger AS $$
            DECLARE
                normalizada text := lower(NEW.slug);
            BEGIN
                IF TG_OP = 'UPDATE' AND NEW.slug IS DISTINCT FROM OLD.slug THEN
                    RAISE EXCEPTION 'El slug de una página no se puede modificar';
                END IF;

                IF NEW.slug !~ '^[A-Za-z0-9][A-Za-z0-9_-]*$'
                    OR normalizada = ANY (ARRAY['404.html', '_astro', 'admin', 'api', 'build', 'favicon.ico', 'livewire', 'robots.txt', 'storage']) THEN
                    RAISE EXCEPTION 'La ruta de la página no es válida o está reservada';
                END IF;

                IF EXISTS (SELECT 1 FROM alias_pagina WHERE lower(ruta) = normalizada) THEN
                    RAISE EXCEPTION 'La ruta ya está reservada por un alias';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER paginas_route_guard
                BEFORE INSERT OR UPDATE OF slug ON paginas
                FOR EACH ROW EXECUTE FUNCTION clicktap_guard_pagina_route();

            CREATE OR REPLACE FUNCTION clicktap_guard_alias_route() RETURNS trigger AS $$
            DECLARE
                normalizada text := lower(NEW.ruta);
            BEGIN
                IF NEW.ruta !~ '^[A-Za-z0-9][A-Za-z0-9_-]*$'
                    OR normalizada = ANY (ARRAY['404.html', '_astro', 'admin', 'api', 'build', 'favicon.ico', 'livewire', 'robots.txt', 'storage']) THEN
                    RAISE EXCEPTION 'La ruta del alias no es válida o está reservada';
                END IF;

                IF EXISTS (SELECT 1 FROM paginas WHERE lower(slug) = normalizada) THEN
                    RAISE EXCEPTION 'La ruta ya está reservada por una página';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER alias_pagina_route_guard
                BEFORE INSERT ON alias_pagina
                FOR EACH ROW EXECUTE FUNCTION clicktap_guard_alias_route();

            CREATE OR REPLACE FUNCTION clicktap_reject_change() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'El registro histórico es inmutable';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER alias_pagina_immutable
                BEFORE UPDATE OR DELETE ON alias_pagina
                FOR EACH ROW EXECUTE FUNCTION clicktap_reject_change();

            CREATE TRIGGER eventos_auditoria_append_only
                BEFORE UPDATE OR DELETE ON eventos_auditoria
                FOR EACH ROW EXECUTE FUNCTION clicktap_reject_change();

            CREATE OR REPLACE FUNCTION clicktap_guard_version() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.estado = 'cerrada' THEN
                        RAISE EXCEPTION 'Una versión cerrada no se puede borrar';
                    END IF;
                    RETURN OLD;
                END IF;

                IF TG_OP = 'UPDATE' AND OLD.estado = 'cerrada' THEN
                    IF OLD.publicada_en IS NOT NULL
                        OR NEW.publicada_en IS NULL
                        OR (to_jsonb(NEW) - ARRAY['publicada_en', 'publicada_por', 'updated_at'])
                            IS DISTINCT FROM
                           (to_jsonb(OLD) - ARRAY['publicada_en', 'publicada_por', 'updated_at']) THEN
                        RAISE EXCEPTION 'Una versión cerrada no se puede modificar';
                    END IF;
                END IF;

                IF NEW.logo_archivo_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM archivos WHERE id = NEW.logo_archivo_id AND pagina_id = NEW.pagina_id
                ) THEN
                    RAISE EXCEPTION 'El logo debe pertenecer a la página de la versión';
                END IF;

                IF NEW.favicon_archivo_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM archivos WHERE id = NEW.favicon_archivo_id AND pagina_id = NEW.pagina_id
                ) THEN
                    RAISE EXCEPTION 'El favicon debe pertenecer a la página de la versión';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER versiones_pagina_guard
                BEFORE INSERT OR UPDATE OR DELETE ON versiones_pagina
                FOR EACH ROW EXECUTE FUNCTION clicktap_guard_version();

            CREATE OR REPLACE FUNCTION clicktap_guard_link() RETURNS trigger AS $$
            DECLARE
                target_version uuid;
            BEGIN
                target_version := CASE WHEN TG_OP = 'DELETE' THEN OLD.version_id ELSE NEW.version_id END;
                IF EXISTS (SELECT 1 FROM versiones_pagina WHERE id = target_version AND estado = 'cerrada') THEN
                    RAISE EXCEPTION 'Los enlaces de una versión cerrada no se pueden modificar';
                END IF;
                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER enlaces_version_guard
                BEFORE INSERT OR UPDATE OR DELETE ON enlaces_version
                FOR EACH ROW EXECUTE FUNCTION clicktap_guard_link();

            CREATE OR REPLACE FUNCTION clicktap_guard_file() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF OLD.clave_publica IS NOT NULL
                        OR NEW.clave_publica IS NULL
                        OR (to_jsonb(NEW) - ARRAY['clave_publica', 'updated_at'])
                            IS DISTINCT FROM
                           (to_jsonb(OLD) - ARRAY['clave_publica', 'updated_at']) THEN
                        RAISE EXCEPTION 'Los archivos registrados son inmutables';
                    END IF;
                    RETURN NEW;
                END IF;

                IF EXISTS (
                    SELECT 1 FROM versiones_pagina
                    WHERE logo_archivo_id = OLD.id OR favicon_archivo_id = OLD.id
                ) THEN
                    RAISE EXCEPTION 'No se puede borrar un archivo referenciado';
                END IF;

                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER archivos_guard
                BEFORE UPDATE OR DELETE ON archivos
                FOR EACH ROW EXECUTE FUNCTION clicktap_guard_file();

            CREATE OR REPLACE FUNCTION clicktap_guard_published_version() RETURNS trigger AS $$
            BEGIN
                IF NEW.version_publicada_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM versiones_pagina
                    WHERE id = NEW.version_publicada_id
                        AND pagina_id = NEW.id
                        AND estado = 'cerrada'
                        AND publicada_en IS NOT NULL
                ) THEN
                    RAISE EXCEPTION 'La versión publicada debe ser una versión cerrada y confirmada de la página';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER paginas_published_version_guard
                BEFORE INSERT OR UPDATE OF version_publicada_id ON paginas
                FOR EACH ROW EXECUTE FUNCTION clicktap_guard_published_version();
            SQL);
    }

    private function downPostgres(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS paginas_published_version_guard ON paginas;
            DROP TRIGGER IF EXISTS archivos_guard ON archivos;
            DROP TRIGGER IF EXISTS enlaces_version_guard ON enlaces_version;
            DROP TRIGGER IF EXISTS versiones_pagina_guard ON versiones_pagina;
            DROP TRIGGER IF EXISTS eventos_auditoria_append_only ON eventos_auditoria;
            DROP TRIGGER IF EXISTS alias_pagina_immutable ON alias_pagina;
            DROP TRIGGER IF EXISTS alias_pagina_route_guard ON alias_pagina;
            DROP TRIGGER IF EXISTS paginas_route_guard ON paginas;

            DROP FUNCTION IF EXISTS clicktap_guard_published_version();
            DROP FUNCTION IF EXISTS clicktap_guard_file();
            DROP FUNCTION IF EXISTS clicktap_guard_link();
            DROP FUNCTION IF EXISTS clicktap_guard_version();
            DROP FUNCTION IF EXISTS clicktap_reject_change();
            DROP FUNCTION IF EXISTS clicktap_guard_alias_route();
            DROP FUNCTION IF EXISTS clicktap_guard_pagina_route();

            ALTER TABLE enlaces_version DROP CONSTRAINT IF EXISTS enlaces_version_posicion_no_negativa;
            ALTER TABLE versiones_pagina
                DROP CONSTRAINT IF EXISTS versiones_pagina_aprobacion_coherente,
                DROP CONSTRAINT IF EXISTS versiones_pagina_cierre_coherente,
                DROP CONSTRAINT IF EXISTS versiones_pagina_estado_valido,
                DROP CONSTRAINT IF EXISTS versiones_pagina_revision_positiva,
                DROP CONSTRAINT IF EXISTS versiones_pagina_numero_positivo;
            SQL);
    }

    private function upSqlite(): void
    {
        DB::unprepared(<<<'SQL'
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

            CREATE TRIGGER alias_pagina_immutable_update
            BEFORE UPDATE ON alias_pagina
            BEGIN
                SELECT RAISE(ABORT, 'El alias histórico es inmutable');
            END;

            CREATE TRIGGER alias_pagina_immutable_delete
            BEFORE DELETE ON alias_pagina
            BEGIN
                SELECT RAISE(ABORT, 'El alias histórico es inmutable');
            END;

            CREATE TRIGGER eventos_auditoria_append_only_update
            BEFORE UPDATE ON eventos_auditoria
            BEGIN
                SELECT RAISE(ABORT, 'La auditoría es de solo inserción');
            END;

            CREATE TRIGGER eventos_auditoria_append_only_delete
            BEFORE DELETE ON eventos_auditoria
            BEGIN
                SELECT RAISE(ABORT, 'La auditoría es de solo inserción');
            END;

            CREATE TRIGGER versiones_pagina_file_owner_insert
            BEFORE INSERT ON versiones_pagina
            BEGIN
                SELECT RAISE(ABORT, 'El archivo debe pertenecer a la página de la versión')
                WHERE (NEW.logo_archivo_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM archivos WHERE id = NEW.logo_archivo_id AND pagina_id = NEW.pagina_id
                )) OR (NEW.favicon_archivo_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM archivos WHERE id = NEW.favicon_archivo_id AND pagina_id = NEW.pagina_id
                ));
            END;

            CREATE TRIGGER versiones_pagina_file_owner_update
            BEFORE UPDATE OF logo_archivo_id, favicon_archivo_id, pagina_id ON versiones_pagina
            BEGIN
                SELECT RAISE(ABORT, 'El archivo debe pertenecer a la página de la versión')
                WHERE (NEW.logo_archivo_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM archivos WHERE id = NEW.logo_archivo_id AND pagina_id = NEW.pagina_id
                )) OR (NEW.favicon_archivo_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM archivos WHERE id = NEW.favicon_archivo_id AND pagina_id = NEW.pagina_id
                ));
            END;

            CREATE TRIGGER archivos_referenced_delete_guard
            BEFORE DELETE ON archivos
            WHEN EXISTS (
                SELECT 1 FROM versiones_pagina
                WHERE logo_archivo_id = OLD.id OR favicon_archivo_id = OLD.id
            )
            BEGIN
                SELECT RAISE(ABORT, 'No se puede borrar un archivo referenciado');
            END;
            SQL);
    }

    private function downSqlite(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS archivos_referenced_delete_guard;
            DROP TRIGGER IF EXISTS versiones_pagina_file_owner_update;
            DROP TRIGGER IF EXISTS versiones_pagina_file_owner_insert;
            DROP TRIGGER IF EXISTS eventos_auditoria_append_only_delete;
            DROP TRIGGER IF EXISTS eventos_auditoria_append_only_update;
            DROP TRIGGER IF EXISTS alias_pagina_immutable_delete;
            DROP TRIGGER IF EXISTS alias_pagina_immutable_update;
            DROP TRIGGER IF EXISTS alias_pagina_route_guard_insert;
            DROP TRIGGER IF EXISTS paginas_slug_immutable;
            DROP TRIGGER IF EXISTS paginas_route_guard_insert;
            SQL);
    }
};
