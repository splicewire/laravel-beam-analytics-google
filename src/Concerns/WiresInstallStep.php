<?php

namespace Splicewire\Beam\AnalyticsGoogle\Concerns;

use Rushing\Popcorn\Concerns\Chained;
use Splicewire\Beam\Install\BeamInstallManifest;

/**
 * This package's step in beam's install manifest, so `splicewire:beam:install` publishes its config
 * (and, once it ships them, its migrations) with the rest of the stack.
 *
 * `bound()`-guarded on purpose: which of beam's manifests a host binds is a fact about the HOST, and an
 * extension that fatals on a host missing one optional manifest is worse than one that installs with
 * one contribution fewer (the rule splicewire/beam-extension-demo's provider states).
 *
 * `order: 900` puts this after every beam-family step. Publishing stamps a migration at publish time,
 * so step order IS the order published migrations sort in on a greenfield install, and an extension's
 * tables land after the substrate they sit beside. When this package ships migrations, list their stems
 * in publish order (`hasMigrations([...])`, never a directory glob), add the migrations tag here and set
 * `migrates: true`, in the same commit.
 */
trait WiresInstallStep
{
    #[Chained('boot')]
    protected function registerInstallStep(): void
    {
        if (! $this->app->bound(BeamInstallManifest::class)) {
            return;
        }

        $this->app->make(BeamInstallManifest::class)->register(
            package: 'splicewire/laravel-beam-analytics-google',
            publishTags: ['beam-analytics-google-config'],
            migrates: false,
            order: 900,
        );
    }
}
