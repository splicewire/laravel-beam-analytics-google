<?php

namespace Splicewire\Beam\AnalyticsGoogle\Ga4;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/** `POST /token` with a JWT-bearer grant: a service account's signed assertion exchanged for a short-lived access token. */
final class FetchAccessToken extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $assertion) {}

    public function resolveEndpoint(): string
    {
        return '/token';
    }

    protected function defaultBody(): array
    {
        return ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $this->assertion];
    }
}
