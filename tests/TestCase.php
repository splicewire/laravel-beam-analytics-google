<?php

namespace Splicewire\Beam\AnalyticsGoogle\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\DataFilters\ServiceProvider as DataFiltersServiceProvider;
use Rushing\DataNav\ServiceProvider as DataNavServiceProvider;
use Rushing\Doctor\ServiceProvider as DoctorServiceProvider;
use Rushing\PermissionCascade\PermissionCascadeServiceProvider;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;
use Rushing\Versioning\VersioningServiceProvider;
use Saloon\Config as SaloonConfig;
use Saloon\Http\Faking\MockClient;
use Schemastud\DataSchemas\LaravelDataSchemasServiceProvider;
use Schemastud\JsonNs\Laravel\JsonNsServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Splicewire\Beam\Analytics\BeamAnalyticsServiceProvider;
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
            // The base package this child requires, and the doctor its audit registers with (beam binds the doctor manifest).
            BeamAnalyticsServiceProvider::class,
            DoctorServiceProvider::class,
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
        $app['config']->set('queue.default', 'sync');
        // NO TEST REACHES GOOGLE: the provider URLs point at a closed local port, and Saloon refuses any request no
        // MockClient answers (setUp below). Provider keys stay blank.
        $app['config']->set('beam.analytics-google.data_url', 'http://127.0.0.1:9/v1beta');
        $app['config']->set('beam.analytics-google.token_url', 'http://127.0.0.1:9');
        $app['config']->set('beam.analytics-google.property_id', null);
        $app['config']->set('beam.analytics-google.credentials', null);
    }

    protected function setUp(): void
    {
        parent::setUp();
        SaloonConfig::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        MockClient::destroyGlobal();
        SaloonConfig::clearGlobalMiddleware();
        parent::tearDown();
    }
}
