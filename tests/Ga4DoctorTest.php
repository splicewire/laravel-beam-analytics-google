<?php

// Ticket 14, criterion 5: doctor reports a misconfigured child as a finding, and never exposes the credential.

use Rushing\Doctor\DoctorStatus;
use Rushing\Doctor\Finding;
use Splicewire\Beam\Analytics\Scope\AnalyticsScope;
use Splicewire\Beam\AnalyticsGoogle\Doctor\Ga4ConfigAudit;
use Splicewire\Beam\AnalyticsGoogle\Ga4\Ga4Config;
use Splicewire\Beam\AnalyticsGoogle\Ga4TrafficSource;
use Splicewire\Beam\AnalyticsGoogle\Tests\Support\Ga4Fixture;

function auditOnce(): Finding
{
    $findings = app(Ga4ConfigAudit::class)->run();
    expect($findings)->toHaveCount(1);

    return $findings[0];
}

it('warns, naming what to fix, when the child is not configured', function () {
    $finding = auditOnce();

    expect($finding->status)->toBe(DoctorStatus::Warn)
        ->and($finding->detail)->toContain('no GA4 property id is set')->toContain('no service account key is set');
});

it('warns on a key file that is not a service account key, and quotes nothing from it', function () {
    $file = tempnam(sys_get_temp_dir(), 'aa14-bad-').'.json';
    $secretish = 'SECRET-'.bin2hex(random_bytes(8));
    file_put_contents($file, json_encode(['client_email' => 'leak-'.$secretish.'@example.test', 'not_a_key' => $secretish]));
    config(['beam.analytics-google.property_id' => '123456789', 'beam.analytics-google.credentials' => $file]);

    $finding = auditOnce();

    expect($finding->status)->toBe(DoctorStatus::Warn)
        ->and($finding->detail)->toContain('not a service account JSON key')
        ->and($finding->detail)->not->toContain($secretish)->not->toContain($file);
});

it('passes a configured child, naming the property but never the credential', function () {
    $keyFile = Ga4Fixture::configure();
    $key = json_decode($keyFile, true);

    $finding = auditOnce();

    expect($finding->status)->toBe(DoctorStatus::Pass)
        ->and($finding->detail)->toContain(Ga4Fixture::PROPERTY)
        ->and($finding->detail)->not->toContain(Ga4Fixture::CLIENT_EMAIL)->not->toContain($key['private_key'])
        ->and($finding->detail)->not->toContain((string) config('beam.analytics-google.credentials'));
});

it('is registered with beam\'s doctor manifest, advisory', function () {
    $manifest = app('Splicewire\\Beam\\Doctor\\BeamDoctorManifest');

    $ours = array_values(array_filter($manifest->registrations(), fn ($r): bool => $r->audit === Ga4ConfigAudit::class));

    expect($ours)->toHaveCount(1)->and($ours[0]->package)->toBe('splicewire/laravel-beam-analytics-google')->and($ours[0]->gate)->toBeFalse();
});

/** Write a GENERATED service account JSON key file with `$privateKey` as its private_key, and configure it. */
function configureKey(string $privateKey): string
{
    $file = tempnam(sys_get_temp_dir(), 'aa14-key-').'.json';
    file_put_contents($file, json_encode(['client_email' => Ga4Fixture::CLIENT_EMAIL, 'private_key' => $privateKey], JSON_UNESCAPED_SLASHES));
    config(['beam.analytics-google.property_id' => Ga4Fixture::PROPERTY, 'beam.analytics-google.credentials' => $file]);

    return $file;
}

it('warns on a malformed private key that only carries the PEM marker, and quotes none of it (14 AMEND 1)', function () {
    $garbage = 'MALFORMED-'.bin2hex(random_bytes(12));
    $file = configureKey("-----BEGIN PRIVATE KEY-----\n{$garbage}\n-----END PRIVATE KEY-----\n");

    $finding = auditOnce();

    expect($finding->status)->toBe(DoctorStatus::Warn)
        ->and($finding->detail)->toContain('not a usable RSA key')
        ->and($finding->detail)->not->toContain($garbage)->not->toContain('PRIVATE KEY')->not->toContain($file)->not->toContain(Ga4Fixture::CLIENT_EMAIL)
        ->and(app(Ga4Config::class)->isConfigured())->toBeFalse()
        ->and(app(Ga4TrafficSource::class)->covers(AnalyticsScope::install()))->toBeFalse('a bad key is not configured, so Traffic reads Not connected');
});

it('warns on a well-formed key that is not RSA, which cannot sign RS256 (14 AMEND 1)', function () {
    // private_key_bits is set because OpenSSL 3.6 (Herd's PHP 8.4.17) refuses an EC request without it; the key is still EC.
    $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'private_key_bits' => 2048]);
    expect(openssl_pkey_get_details($ec)['type'])->toBe(OPENSSL_KEYTYPE_EC);
    openssl_pkey_export($ec, $pem);
    configureKey($pem);

    $finding = auditOnce();

    expect($finding->status)->toBe(DoctorStatus::Warn)
        ->and($finding->detail)->toContain('not a usable RSA key')
        ->and($finding->detail)->not->toContain(substr($pem, 40, 40))
        ->and(app(Ga4Config::class)->isConfigured())->toBeFalse();
});
