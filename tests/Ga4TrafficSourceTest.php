<?php

// Ticket 14: the Google Analytics child in install scope. Every test here is offline: Google is answered by a Saloon
// MockClient, the provider URLs point at a closed port, and Saloon's preventStrayRequests is on (TestCase).

use Illuminate\Support\Facades\Exceptions;
use Saloon\Exceptions\StrayRequestException;
use Saloon\Http\Faking\MockClient;
use Splicewire\Beam\Analytics\Contracts\TrafficSource;
use Splicewire\Beam\Analytics\Scope\AnalyticsPeriod;
use Splicewire\Beam\Analytics\Scope\AnalyticsScope;
use Splicewire\Beam\Analytics\Traffic\TrafficReadings;
use Splicewire\Beam\Analytics\Traffic\TrafficSourceRegistry;
use Splicewire\Beam\Analytics\Traffic\TrafficStatus;
use Splicewire\Beam\AnalyticsGoogle\Doctor\Ga4ConfigAudit;
use Splicewire\Beam\AnalyticsGoogle\Ga4\FetchAccessToken;
use Splicewire\Beam\AnalyticsGoogle\Ga4\Ga4DataConnector;
use Splicewire\Beam\AnalyticsGoogle\Ga4\Ga4ReadFailed;
use Splicewire\Beam\AnalyticsGoogle\Ga4\RunReport;
use Splicewire\Beam\AnalyticsGoogle\Ga4TrafficSource;
use Splicewire\Beam\AnalyticsGoogle\Tests\Support\Ga4Fixture;

function ga4(): Ga4TrafficSource
{
    return app(Ga4TrafficSource::class);
}

/** Every secret a message must never carry: the key file's text and its private key, the client email, the token. */
function secretsOf(string $keyFile): array
{
    $key = json_decode($keyFile, true);

    return [$key['private_key'], substr($key['private_key'], 40, 60), Ga4Fixture::CLIENT_EMAIL, Ga4Fixture::TOKEN, $keyFile];
}

it('registers Ga4TrafficSource into the sources registry by its class name', function () {
    $sources = app(TrafficSourceRegistry::class);

    expect($sources->has('google-analytics'))->toBeTrue()
        ->and($sources->resolve('google-analytics'))->toBe(Ga4TrafficSource::class)
        ->and($sources->registrantOf('google-analytics'))->toBe('splicewire/laravel-beam-analytics-google')
        ->and(is_subclass_of(Ga4TrafficSource::class, TrafficSource::class))->toBeTrue();
});

it('is never available for a tenant scope, even with install credentials configured', function () {
    Ga4Fixture::configure();

    expect(ga4()->covers(AnalyticsScope::install()))->toBeTrue('configured: the install is covered');
    foreach (['acme', 'globex', '1', Ga4Fixture::PROPERTY] as $tenant) {
        expect(ga4()->covers(AnalyticsScope::tenant($tenant)))->toBeFalse("tenant {$tenant}: never");
    }
    expect(app(TrafficSourceRegistry::class)->sourceFor(AnalyticsScope::tenant('acme')))->toBeNull('so a tenant reads "Not connected"');
    expect(fn () => ga4()->read(AnalyticsScope::tenant('acme'), AnalyticsPeriod::overview()))->toThrow(Ga4ReadFailed::class);
});

it('takes the NUMERIC property id from its config and never a G- measurement id', function () {
    Ga4Fixture::configure('G-ABC123XYZ');
    expect(ga4()->covers(AnalyticsScope::install()))->toBeFalse('a G- id cannot address the Data API')
        ->and(app(Ga4ConfigAudit::class)->run()[0]->detail)->toContain('G- measurement id');

    // The base package's G- measurement id (what its tag renders) is never borrowed.
    config(['beam.analytics-google.property_id' => null, 'analytics.providers.ga4.id' => 'G-ABC123XYZ']);
    expect(ga4()->covers(AnalyticsScope::install()))->toBeFalse();

    config(['beam.analytics-google.property_id' => '123456789']);
    expect(ga4()->covers(AnalyticsScope::install()))->toBeTrue();
});

it('is not connected without config, and never calls out to decide', function () {
    $mock = MockClient::global([]);

    expect(ga4()->covers(AnalyticsScope::install()))->toBeFalse()
        ->and(app(TrafficSourceRegistry::class)->sourceFor(AnalyticsScope::install()))->toBeNull();
    $mock->assertNothingSent();
});

it('maps a runReport into the provider-neutral snapshot', function () {
    Ga4Fixture::configure();
    $mock = Ga4Fixture::measured();

    $snapshot = ga4()->read(AnalyticsScope::install(), new AnalyticsPeriod(28));

    expect($snapshot->users)->toBe(70)->and($snapshot->sessions)->toBe(105)->and($snapshot->views)->toBe(250)
        ->and($snapshot->trend)->toBe(['2026-11-09' => 120, '2026-11-10' => 130])
        ->and($snapshot->pages)->toBe([['label' => '/pricing', 'value' => 150], ['label' => '/', 'value' => 100]])
        ->and($snapshot->referrers)->toBe([['label' => 'google', 'value' => 60], ['label' => '(direct)', 'value' => 45]]);

    $mock->assertSentCount(4);
    $mock->assertSent(fn ($request, $response): bool => $request instanceof FetchAccessToken
        && count(explode('.', $response->getPendingRequest()->body()->all()['assertion'])) === 3);
    $mock->assertSent(fn ($request, $response): bool => $request instanceof RunReport
        && str_ends_with($response->getPendingRequest()->getUrl(), '/properties/'.Ga4Fixture::PROPERTY.':runReport')
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer '.Ga4Fixture::TOKEN
        && $response->getPendingRequest()->body()->all()['dateRanges'] === [['startDate' => '28daysAgo', 'endDate' => 'today']]);
});

it('reaches 08\'s honest states through the refresh job: measured, auth failure, quota failure and an empty report', function () {
    Exceptions::fake();
    $readings = app(TrafficReadings::class);
    $install = AnalyticsScope::install();
    $period = AnalyticsPeriod::overview();
    $keyFile = Ga4Fixture::configure();

    // Auth failure with no reading yet: "Couldn't be measured", reported as an auth failure.
    Ga4Fixture::authRefused();
    $readings->refresh($install, $period);
    expect($readings->status($install, $period)->state)->toBe(TrafficStatus::FAILED);
    Exceptions::assertReported(fn (Ga4ReadFailed $e): bool => $e->kind === Ga4ReadFailed::AUTH);

    // Measured: the totals.
    MockClient::destroyGlobal();
    Ga4Fixture::measured();
    $readings->refresh($install, $period);
    $measured = $readings->status($install, $period);
    expect($measured->state)->toBe(TrafficStatus::MEASURED)->and($measured->snapshot->users)->toBe(70)->and($measured->failedAt)->toBeNull();

    // Quota failure after a good reading: the last good numbers stay, marked failed.
    MockClient::destroyGlobal();
    Ga4Fixture::quotaExhausted();
    $readings->refresh($install, $period);
    $kept = $readings->status($install, $period);
    expect($kept->state)->toBe(TrafficStatus::MEASURED)->and($kept->snapshot->users)->toBe(70)->and($kept->failedAt)->not->toBeNull();
    Exceptions::assertReported(fn (Ga4ReadFailed $e): bool => $e->kind === Ga4ReadFailed::QUOTA && $e->status === 429);

    // An empty report: a measured ZERO with no rows, never an invented number.
    MockClient::destroyGlobal();
    Ga4Fixture::empty();
    $readings->refresh($install, $period);
    $empty = $readings->status($install, $period);
    expect($empty->state)->toBe(TrafficStatus::MEASURED)->and($empty->snapshot->users)->toBe(0)
        ->and($empty->snapshot->trend)->toBe([])->and($empty->snapshot->pages)->toBe([])->and($empty->failedAt)->toBeNull();

    foreach (Exceptions::reported() as $e) {
        foreach (secretsOf($keyFile) as $secret) {
            expect($e->getMessage())->not->toContain($secret);
        }
    }
});

it('reaches no real HTTP: a request no mock answers is refused before it leaves', function () {
    Ga4Fixture::configure();

    expect(fn () => (new Ga4DataConnector('https://analyticsdata.googleapis.com/v1beta', 't'))->send(new RunReport('1', [])))
        ->toThrow(StrayRequestException::class);
    expect(fn () => ga4()->read(AnalyticsScope::install(), AnalyticsPeriod::overview()))
        ->toThrow(StrayRequestException::class);
});

it('never puts a credential in a failure message', function () {
    $keyFile = Ga4Fixture::configure();
    Ga4Fixture::authRefused();

    try {
        ga4()->read(AnalyticsScope::install(), AnalyticsPeriod::overview());
        $this->fail('an auth refusal must throw');
    } catch (Ga4ReadFailed $e) {
        expect($e->kind)->toBe(Ga4ReadFailed::AUTH)->and($e->getMessage())->toContain('HTTP 400');
        foreach (secretsOf($keyFile) as $secret) {
            expect($e->getMessage())->not->toContain($secret);
        }
    }
});
