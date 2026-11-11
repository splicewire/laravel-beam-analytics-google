<?php

namespace Splicewire\Beam\AnalyticsGoogle\Tests\Support;

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Splicewire\Beam\AnalyticsGoogle\Ga4\FetchAccessToken;
use Splicewire\Beam\AnalyticsGoogle\Ga4\RunReport;

/**
 * Test-only GA4 fixtures. The service account key is GENERATED per run (openssl), so no key file is committed and none is
 * read from any .env. The mock answers Google's token endpoint and the three runReport requests, told apart by their
 * dimension; NOTHING here reaches Google (TestCase also sets Saloon's preventStrayRequests).
 */
final class Ga4Fixture
{
    public const PROPERTY = '123456789';

    public const CLIENT_EMAIL = 'aa14-proof@example-project.iam.gserviceaccount.com';

    public const TOKEN = 'ya29.TEST-ACCESS-TOKEN';

    /** Write a fresh service account key file and configure the child with it and the numeric property. Returns the key file's text. */
    public static function configure(?string $propertyId = self::PROPERTY): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $json = json_encode(['type' => 'service_account', 'client_email' => self::CLIENT_EMAIL, 'private_key' => $pem, 'private_key_id' => 'kid-test'], JSON_UNESCAPED_SLASHES);
        $file = tempnam(sys_get_temp_dir(), 'aa14-sa-').'.json';
        file_put_contents($file, $json);

        config(['beam.analytics-google.property_id' => $propertyId, 'beam.analytics-google.credentials' => $file]);

        return (string) $json;
    }

    /** A runReport body, as GA4 answers it: rows of dimension and metric values, and the TOTAL aggregation when asked. */
    public static function report(array $rows, ?array $totals = null): array
    {
        $body = [
            'rows' => array_map(fn (array $r): array => [
                'dimensionValues' => [['value' => (string) $r[0]]],
                'metricValues' => array_map(fn ($v): array => ['value' => (string) $v], array_slice($r, 1)),
            ], $rows),
            'rowCount' => count($rows),
        ];
        if ($totals !== null) {
            $body['totals'] = [['dimensionValues' => [['value' => 'RESERVED_TOTAL']], 'metricValues' => array_map(fn ($v): array => ['value' => (string) $v], $totals)]];
        }

        return $body;
    }

    /** The mock for a whole successful read: a token, then the totals/trend, top pages and top referrers reports. */
    public static function measured(): MockClient
    {
        return MockClient::global([
            FetchAccessToken::class => MockResponse::make(['access_token' => self::TOKEN, 'expires_in' => 3599, 'token_type' => 'Bearer']),
            RunReport::class => fn (PendingRequest $pending): MockResponse => MockResponse::make(match ($pending->body()->all()['dimensions'][0]['name'] ?? null) {
                'date' => self::report([['20261109', 40, 50, 120], ['20261110', 45, 55, 130]], [70, 105, 250]),
                'pagePath' => self::report([['/pricing', 150], ['/', 100]]),
                'sessionSource' => self::report([['google', 60], ['(direct)', 45]]),
                default => ['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT']],
            }),
        ]);
    }

    /** Every report empty: a property with no traffic in the window (no rows, no totals). */
    public static function empty(): MockClient
    {
        return MockClient::global([
            FetchAccessToken::class => MockResponse::make(['access_token' => self::TOKEN]),
            RunReport::class => MockResponse::make(['rowCount' => 0, 'kind' => 'analyticsData#runReport']),
        ]);
    }

    public static function authRefused(): MockClient
    {
        return MockClient::global([
            FetchAccessToken::class => MockResponse::make(['error' => 'invalid_grant', 'error_description' => 'Invalid JWT Signature.'], 400),
        ]);
    }

    public static function quotaExhausted(): MockClient
    {
        return MockClient::global([
            FetchAccessToken::class => MockResponse::make(['access_token' => self::TOKEN]),
            RunReport::class => MockResponse::make(['error' => ['code' => 429, 'message' => 'Exhausted property tokens per day.', 'status' => 'RESOURCE_EXHAUSTED']], 429),
        ]);
    }
}
