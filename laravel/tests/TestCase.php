<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard against the test suite ever running against a real database.
     *
     * `RefreshDatabase` runs `migrate:fresh`, which drops every table. The Docker
     * image runs `php artisan config:cache` on boot, and cached config ignores
     * the sqlite override in phpunit.xml — so `php artisan test` inside the
     * container would happily wipe the dev/prod Postgres DB. This check runs
     * before any trait setup (and before the first migration) and aborts hard if
     * the resolved connection is not in-memory sqlite.
     *
     * Always run the suite via `composer test` (it clears cached config first).
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']['database.default'];
        $database   = $app['config']["database.connections.{$connection}.database"];

        if ($connection !== 'sqlite' || ! in_array($database, [':memory:', 'testing'], true)) {
            fwrite(STDERR, sprintf(
                "\n\033[41;97m  ABORTING TEST RUN  \033[0m\n"
                . "Tests resolved to connection '%s' (database '%s'), not in-memory sqlite.\n"
                . "Cached config is almost certainly the cause. Run:\n"
                . "    composer test            # clears cached config, then runs the suite\n"
                . "  or  php artisan config:clear && php artisan test\n\n",
                $connection,
                $database ?: '(none)'
            ));
            exit(1);
        }

        return $app;
    }
}
