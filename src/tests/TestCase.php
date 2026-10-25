<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $usesPostgres = getenv('CLICKTAP_TEST_DATABASE') === 'postgres';

        $this->setEnvironment('DB_CONNECTION', $usesPostgres ? 'pgsql' : 'sqlite');
        $this->setEnvironment('DB_DATABASE', $usesPostgres ? 'clicktap_test' : ':memory:');
        $this->setEnvironment('DB_URL', '');
        $this->setEnvironment('CLICKTAP_LOCAL_CATALOG', 'false');
        $this->setEnvironment('CLICKTAP_SYNC_CACHE_STORE', 'array');
        $this->setEnvironment('CLICKTAP_PUBLICATION_STORE', 'array');
        $this->setEnvironment('CLICKTAP_MEDIA_DISK', 'public');

        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($connection === 'pgsql' && $database !== 'clicktap_test') {
            throw new RuntimeException('Las pruebas PostgreSQL solo pueden usar la base clicktap_test.');
        }

        return $app;
    }

    private function setEnvironment(string $name, string $value): void
    {
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
