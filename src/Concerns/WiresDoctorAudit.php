<?php

namespace Splicewire\Beam\AnalyticsGoogle\Concerns;

use Rushing\Popcorn\Concerns\Chained;
use Splicewire\Beam\AnalyticsGoogle\Doctor\Ga4ConfigAudit;

/**
 * {@see Ga4ConfigAudit} registered DOWN into beam-core's doctor manifest, ADVISORY (`gate: false`), so one
 * `splicewire:beam:doctor` run reports a misconfigured child with the rest of the family. `bound()`-guarded, by class name
 * string, as laravel-beam-analytics registers its own audit.
 */
trait WiresDoctorAudit
{
    #[Chained('boot')]
    protected function registerDoctorAudit(): void
    {
        if (! $this->app->bound('Splicewire\\Beam\\Doctor\\BeamDoctorManifest')) {
            return;
        }

        $this->app->make('Splicewire\\Beam\\Doctor\\BeamDoctorManifest')->register(
            package: 'splicewire/laravel-beam-analytics-google',
            audit: Ga4ConfigAudit::class,
            gate: false,
        );
    }
}
