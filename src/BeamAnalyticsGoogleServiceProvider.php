<?php

namespace Splicewire\Beam\AnalyticsGoogle;

use Rushing\Popcorn\Concerns\ChainsTraitMethods;
use Rushing\Popcorn\Contracts\ChainsTraitMethods as ChainsTraitMethodsContract;
use Rushing\Popcorn\Registries\ScopesRegistrant;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Splicewire\Beam\AnalyticsGoogle\Concerns\WiresDoctorAudit;
use Splicewire\Beam\AnalyticsGoogle\Concerns\WiresInstallStep;
use Splicewire\Beam\AnalyticsGoogle\Concerns\WiresTrafficSource;

/**
 * The Google Analytics child: a GA4 Data API TrafficSource for laravel-beam-analytics, install scope only.
 *
 * It contributes by registering DOWN into manifests the host's beam already binds: it is required, and
 * it appears. Nothing in the host is edited, and nothing in the host learns
 * this package's name.
 *
 * Each concern arrives as a `Wires*` trait whose methods carry `#[Chained('register')]` or
 * `#[Chained('boot')]` with a declared `order:` (the beam-ux shape). Adding a concern is `use`-ing its
 * trait; this class never hand-lists the steps. `ScopesRegistrant` brackets both phases, so every
 * registry write in them that names no `$by` records this package.
 */
class BeamAnalyticsGoogleServiceProvider extends PackageServiceProvider implements ChainsTraitMethodsContract
{
    use ChainsTraitMethods;
    use ScopesRegistrant;
    use WiresDoctorAudit;
    use WiresInstallStep;
    use WiresTrafficSource;

    protected function registrant(): string
    {
        return 'splicewire/laravel-beam-analytics-google';
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-beam-analytics-google')
            ->hasConfigFile(['beam/analytics-google']);
    }

    public function packageRegistered(): void
    {
        $this->chainTraitMethods('register');
    }

    public function packageBooted(): void
    {
        $this->chainTraitMethods('boot');
    }
}
