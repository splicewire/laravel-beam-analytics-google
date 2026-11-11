<?php

namespace Splicewire\Beam\AnalyticsGoogle\Ga4;

use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;

/**
 * The GA4 Data API, narrowly: this package sends it one request, {@see RunReport} (`properties/{id}:runReport`). conduit-fda's
 * connector is the precedent: a plain Saloon connector with its base URL, JSON headers and nothing else. The bearer token is
 * the service account's, fetched by {@see GoogleTokenConnector}.
 */
final class Ga4DataConnector extends Connector
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $accessToken,
    ) {}

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator($this->accessToken);
    }
}
