<?php

namespace Splicewire\Beam\AnalyticsGoogle\Ga4;

use RuntimeException;
use Splicewire\Beam\AnalyticsGoogle\Ga4TrafficSource;

/**
 * A GA4 read that could not complete, thrown from {@see Ga4TrafficSource::read()} inside
 * laravel-beam-analytics' refresh job, which reports it and keeps the honest state ("Couldn't be measured", or the last good
 * reading). The message names the KIND and the HTTP status only: never a response body, the token, the signed assertion or
 * anything from the service account key, so a report or a log line exposes no credential.
 */
final class Ga4ReadFailed extends RuntimeException
{
    public const MISCONFIGURED = 'misconfigured';

    public const AUTH = 'auth';

    public const QUOTA = 'quota';

    public const REQUEST = 'request';

    private function __construct(public readonly string $kind, string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }

    /** @param  list<string>  $problems  {@see Ga4Config::problems()}, which name no secret */
    public static function misconfigured(array $problems): self
    {
        return new self(self::MISCONFIGURED, 'Google Analytics is not configured for the install: '.implode('; ', $problems).'.');
    }

    public static function auth(int $status, string $step): self
    {
        return new self(self::AUTH, "Google Analytics refused the service account's credentials ({$step}, HTTP {$status}).", $status);
    }

    public static function quota(int $status): self
    {
        return new self(self::QUOTA, "Google Analytics' Data API quota is exhausted for this property (HTTP {$status}).", $status);
    }

    public static function request(int $status, string $step): self
    {
        return new self(self::REQUEST, "Google Analytics' Data API did not answer the {$step} request (HTTP {$status}).", $status);
    }
}
