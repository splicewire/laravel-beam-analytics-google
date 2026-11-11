<?php

use Illuminate\Support\ServiceProvider;
use Rushing\Popcorn\Concerns\Chained;
use Rushing\Popcorn\Registries\Registrant;
use Splicewire\Beam\AnalyticsGoogle\BeamAnalyticsGoogleServiceProvider;
use Splicewire\Beam\Install\BeamInstallManifest;
use Splicewire\Beam\Install\InstallStep;

it('boots beside beam', function () {
    expect($this->app->getLoadedProviders())->toHaveKey(BeamAnalyticsGoogleServiceProvider::class);
});

it('merges its config under the nested beam.analytics-google root', function () {
    expect(config('beam.analytics-google'))->toBeArray()
        ->and(config()->has('beam-analytics-google'))->toBeFalse();
});

it('registers its install step into beam, after every beam-family step', function () {
    $steps = array_values(array_filter(
        app(BeamInstallManifest::class)->steps(),
        fn (InstallStep $step): bool => $step->package === 'splicewire/laravel-beam-analytics-google',
    ));

    expect($steps)->toHaveCount(1)
        ->and($steps[0]->publishTags)->toBe(['beam-analytics-google-config'])
        ->and($steps[0]->order)->toBe(900)
        ->and($steps[0]->migrates)->toBeFalse();
});

it('names a publish tag that publishes something', function () {
    // A step naming a tag nothing publishes installs as a silent no-op.
    expect(ServiceProvider::pathsToPublish(BeamAnalyticsGoogleServiceProvider::class, 'beam-analytics-google-config'))->not->toBeEmpty();
});

it('runs a chained concern in each phase inside the package registrant scope', function () {
    $provider = new class($this->app) extends BeamAnalyticsGoogleServiceProvider
    {
        /** @var array<string, string|null> */
        public array $seen = [];

        #[Chained('register')]
        protected function recordRegister(): void
        {
            $this->seen['register'] = Registrant::current();
        }

        #[Chained('boot')]
        protected function recordBoot(): void
        {
            $this->seen['boot'] = Registrant::current();
        }
    };

    $provider->register();
    $provider->boot();

    expect($provider->seen)->toBe([
        'register' => 'splicewire/laravel-beam-analytics-google',
        'boot' => 'splicewire/laravel-beam-analytics-google',
    ])->and(Registrant::current())->toBeNull();
});
