<?php

namespace Splicewire\Beam\AnalyticsGoogle\Ga4;

use Saloon\Http\Connector;

/** Google's OAuth 2.0 token endpoint, for one request: a service account's signed assertion for an access token ({@see FetchAccessToken}). */
final class GoogleTokenConnector extends Connector
{
    public function __construct(private readonly string $baseUrl) {}

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }
}
