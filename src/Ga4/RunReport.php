<?php

namespace Splicewire\Beam\AnalyticsGoogle\Ga4;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/** `POST /properties/{id}:runReport`: the GA4 Data API's one call this package makes. The body is the report's definition. */
final class RunReport extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /** @param  array<string, mixed>  $report  dateRanges, dimensions, metrics, orderBys, limit, metricAggregations */
    public function __construct(
        private readonly string $propertyId,
        private readonly array $report,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/properties/'.$this->propertyId.':runReport';
    }

    protected function defaultBody(): array
    {
        return $this->report;
    }
}
