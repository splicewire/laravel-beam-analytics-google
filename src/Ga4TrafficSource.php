<?php

namespace Splicewire\Beam\AnalyticsGoogle;

use Saloon\Http\Response;
use Splicewire\Beam\Analytics\Contracts\TrafficSource;
use Splicewire\Beam\Analytics\Scope\AnalyticsPeriod;
use Splicewire\Beam\Analytics\Scope\AnalyticsScope;
use Splicewire\Beam\Analytics\Traffic\TrafficSnapshot;
use Splicewire\Beam\AnalyticsGoogle\Ga4\FetchAccessToken;
use Splicewire\Beam\AnalyticsGoogle\Ga4\Ga4Config;
use Splicewire\Beam\AnalyticsGoogle\Ga4\Ga4DataConnector;
use Splicewire\Beam\AnalyticsGoogle\Ga4\Ga4ReadFailed;
use Splicewire\Beam\AnalyticsGoogle\Ga4\GoogleTokenConnector;
use Splicewire\Beam\AnalyticsGoogle\Ga4\RunReport;
use Splicewire\Beam\AnalyticsGoogle\Ga4\ServiceAccountAssertion;

/**
 * The install's traffic from its Google Analytics 4 property, through the GA4 Data API: laravel-beam-analytics'
 * {@see TrafficSource} port, registered into `beam.analytics.sources` by this package's provider.
 *
 * - **Install scope only.** {@see covers()} is false for EVERY tenant scope, whatever is configured: the install's property
 *   measures the install's site, and a tenant reads its own honest "Not connected", never the install's numbers. (Ticket 14's
 *   `availableFor` is this port's `covers()`.)
 * - **Configuration only on a request.** {@see covers()} reads config and checks the key file locally; it never calls out.
 * - **Read in the refresh job.** {@see read()} signs a token request with the service account, then runs three reports on
 *   `properties/{id}:runReport`: the totals with the daily trend, the top pages and the top referrers. A refused credential, an
 *   exhausted quota or any other failure throws {@see Ga4ReadFailed} (a message with no secret in it), which the job reports
 *   while the reader keeps 08's honest state. An empty report is a measured zero, never an invented number.
 */
final class Ga4TrafficSource implements TrafficSource
{
    public const KEY = 'google-analytics';

    /** How many top pages and referrers a report returns. */
    public const TOP = 10;

    public function __construct(private readonly Ga4Config $config) {}

    public static function key(): string
    {
        return self::KEY;
    }

    public function covers(AnalyticsScope $scope): bool
    {
        return ! $scope->isTenant() && $this->config->isConfigured();
    }

    public function read(AnalyticsScope $scope, AnalyticsPeriod $period): TrafficSnapshot
    {
        if ($scope->isTenant() || ! $this->config->isConfigured()) {
            throw Ga4ReadFailed::misconfigured($scope->isTenant() ? ['a tenant scope is never read from the install property'] : $this->config->problems());
        }

        $data = new Ga4DataConnector($this->config->dataUrl(), $this->accessToken());
        $property = (string) $this->config->propertyId();
        $range = [self::window($period)];

        $totals = $this->report($data, $property, [
            'dateRanges' => $range,
            'dimensions' => [['name' => 'date']],
            'metrics' => [['name' => 'totalUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews']],
            'metricAggregations' => ['TOTAL'],
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
        ], 'totals');
        $pages = $this->report($data, $property, self::top($range, 'pagePath', 'screenPageViews'), 'top pages');
        $referrers = $this->report($data, $property, self::top($range, 'sessionSource', 'sessions'), 'top referrers');

        $total = $totals['totals'][0]['metricValues'] ?? [];
        $trend = [];
        foreach ($totals['rows'] ?? [] as $row) {
            $day = (string) ($row['dimensionValues'][0]['value'] ?? '');
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $day, $m) === 1) {
                $trend["{$m[1]}-{$m[2]}-{$m[3]}"] = self::count($row['metricValues'][2]['value'] ?? 0);
            }
        }

        return new TrafficSnapshot(
            users: self::count($total[0]['value'] ?? 0),
            sessions: self::count($total[1]['value'] ?? 0),
            views: self::count($total[2]['value'] ?? 0),
            trend: $trend,
            pages: self::ranked($pages),
            referrers: self::ranked($referrers),
        );
    }

    /** A token for the service account, from Google's token endpoint. A refusal is an auth failure, with no secret in it. */
    private function accessToken(): string
    {
        $account = $this->config->serviceAccount();
        $token = new GoogleTokenConnector($this->config->tokenUrl());
        $response = $token->send(new FetchAccessToken(ServiceAccountAssertion::sign((array) $account, $this->config->tokenUrl().'/token', time())));

        if ($response->failed()) {
            throw in_array($response->status(), [400, 401, 403], true)
                ? Ga4ReadFailed::auth($response->status(), 'token')
                : Ga4ReadFailed::request($response->status(), 'token');
        }

        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw Ga4ReadFailed::auth($response->status(), 'token');
        }

        return $accessToken;
    }

    /** @return array<string, mixed> one runReport response */
    private function report(Ga4DataConnector $data, string $property, array $report, string $step): array
    {
        $response = $data->send(new RunReport($property, $report));

        if ($response->failed()) {
            throw self::failure($response, $step);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private static function failure(Response $response, string $step): Ga4ReadFailed
    {
        $status = $response->status();
        $reason = (string) ($response->json('error.status') ?? '');

        return match (true) {
            $status === 429 || $reason === 'RESOURCE_EXHAUSTED' => Ga4ReadFailed::quota($status),
            in_array($status, [401, 403], true) || in_array($reason, ['UNAUTHENTICATED', 'PERMISSION_DENIED'], true) => Ga4ReadFailed::auth($status, $step),
            default => Ga4ReadFailed::request($status, $step),
        };
    }

    /**
     * The report window: EXACTLY `$period->days` dates. The Data API's DateRange is inclusive at BOTH ends, so "Last 7 days" is
     * `6daysAgo..today`, seven dates. The window ENDS TODAY because that is what laravel-beam-analytics' period means: a reading
     * measured now, labelled "<measured at> · Last N days", covers the N days up to that moment, today included (08's fixtures
     * and trend run through today). One window serves all three reports, so they always agree.
     *
     * @return array{startDate: string, endDate: string}
     */
    public static function window(AnalyticsPeriod $period): array
    {
        $back = $period->days - 1;

        return ['startDate' => $back === 0 ? 'today' : $back.'daysAgo', 'endDate' => 'today'];
    }

    /** @return array<string, mixed> a top-N report over one dimension, by one metric, most first */
    private static function top(array $range, string $dimension, string $metric): array
    {
        return [
            'dateRanges' => $range,
            'dimensions' => [['name' => $dimension]],
            'metrics' => [['name' => $metric]],
            'orderBys' => [['metric' => ['metricName' => $metric], 'desc' => true]],
            'limit' => self::TOP,
        ];
    }

    /** @return list<array{label: string, value: int}> */
    private static function ranked(array $report): array
    {
        $rows = [];
        foreach ($report['rows'] ?? [] as $row) {
            $label = trim((string) ($row['dimensionValues'][0]['value'] ?? ''));
            if ($label !== '') {
                $rows[] = ['label' => $label, 'value' => self::count($row['metricValues'][0]['value'] ?? 0)];
            }
        }

        return $rows;
    }

    private static function count(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
