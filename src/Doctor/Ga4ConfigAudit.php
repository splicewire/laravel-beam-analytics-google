<?php

namespace Splicewire\Beam\AnalyticsGoogle\Doctor;

use Rushing\Doctor\DoctorAudit;
use Rushing\Doctor\Finding;
use Splicewire\Beam\AnalyticsGoogle\Ga4\Ga4Config;

/**
 * Doctor's view of the Google Analytics child: is the install's GA4 property readable? A local check (config and the key
 * file), never a call to Google. A misconfiguration is a WARN finding that says what to fix, in words that expose no credential:
 * never the key, its client email or the file's contents ({@see Ga4Config::problems()}). The numeric property id is not a secret
 * and is named when it is valid.
 */
final class Ga4ConfigAudit implements DoctorAudit
{
    public const CHECK = 'Google Analytics property';

    public function __construct(private readonly Ga4Config $config) {}

    /** @return list<Finding> */
    public function run(): array
    {
        $problems = $this->config->problems();

        if ($problems !== []) {
            return [Finding::warn(self::CHECK, 'Traffic reads "Not connected" for the install: '.implode('; ', $problems).'.')];
        }

        return [Finding::pass(self::CHECK, "property {$this->config->propertyId()}, read with a service account key (install scope only; a real runReport is unmeasured until an owner runs it).")];
    }
}
