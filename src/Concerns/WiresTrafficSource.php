<?php

namespace Splicewire\Beam\AnalyticsGoogle\Concerns;

use Rushing\Popcorn\Concerns\Chained;
use Splicewire\Beam\AnalyticsGoogle\Ga4TrafficSource;

/**
 * {@see Ga4TrafficSource} registered DOWN into laravel-beam-analytics' `beam.analytics.sources`
 * registry when the host resolves it. The registry and the class are named as strings, so registering costs nothing until a
 * reader asks, and nothing here touches the registry's class before analytics' own provider has bound it.
 *
 * If the registry was already resolved (a provider that booted first asked for it), `afterResolving` would never fire, so the
 * source is added directly then.
 */
trait WiresTrafficSource
{
    public const TRAFFIC_SOURCE_REGISTRY = 'Splicewire\\Beam\\Analytics\\Traffic\\TrafficSourceRegistry';

    public const GA4_TRAFFIC_SOURCE = 'Splicewire\\Beam\\AnalyticsGoogle\\Ga4TrafficSource';

    #[Chained('register')]
    protected function registerTrafficSource(): void
    {
        // Idempotent: the registry rejects a duplicate key, and the register chain may run more than once.
        $add = static fn ($sources) => $sources->has('google-analytics') ? $sources : $sources->add(self::GA4_TRAFFIC_SOURCE, 'splicewire/laravel-beam-analytics-google');

        $this->app->afterResolving(self::TRAFFIC_SOURCE_REGISTRY, $add);

        if ($this->app->resolved(self::TRAFFIC_SOURCE_REGISTRY)) {
            $add($this->app->make(self::TRAFFIC_SOURCE_REGISTRY));
        }
    }
}
