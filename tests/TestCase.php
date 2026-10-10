<?php

namespace Tests;

use Database\Seeders\PolicySeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Age bands and settings are needed by almost every test (PolicyResolver).
     */
    protected bool $seed = true;

    protected string $seeder = PolicySeeder::class;

    /**
     * Safety net, checked right after the app boots and before RefreshDatabase migrates:
     * never wipe the dev MySQL database (e.g. if a cached config were loaded instead of
     * phpunit.xml's env).
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Tests must run on in-memory SQLite. Run `php artisan config:clear` and check phpunit.xml.');
        }
    }
}
