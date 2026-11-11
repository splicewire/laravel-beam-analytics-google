<?php

namespace Splicewire\Beam\AnalyticsGoogle\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\DataFilters\ServiceProvider as DataFiltersServiceProvider;
use Rushing\DataNav\ServiceProvider as DataNavServiceProvider;
use Rushing\PermissionCascade\PermissionCascadeServiceProvider;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;
use Rushing\Versioning\VersioningServiceProvider;
use Schemastud\DataSchemas\LaravelDataSchemasServiceProvider;
use Schemastud\JsonNs\Laravel\JsonNsServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Splicewire\Beam\AnalyticsGoogle\BeamAnalyticsGoogleServiceProvider;
use Splicewire\Beam\BeamServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * This package's provider, then beam with the providers beam's own harness lists
     * (splicewire/laravel-beam `tests/TestCase.php`). Copied, because no packaged Beam testbench exists yet
     * (architecture-pass B1-6): when beam's list changes, this one follows.
     *
     * ⚠️ Testbench does NOT auto-discover. A provider missing here does not present as a missing provider;
     * it presents as a registry that reads back empty, with nothing erroring.
     */
    protected function getPackageProviders($app): array
    {
        return [
            BeamAnalyticsGoogleServiceProvider::class,
            BeamServiceProvider::class,
            ActivitylogServiceProvider::class,
            LaravelDataServiceProvider::class,
            VersioningServiceProvider::class,
            LaravelDataSchemasServiceProvider::class,
            PermissionCascadeServiceProvider::class,
            DataNavServiceProvider::class,
            JsonNsServiceProvider::class,
            PopcornServiceProvider::class,
            DataFiltersServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
    }
}
