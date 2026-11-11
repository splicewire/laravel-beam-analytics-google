<?php

namespace Splicewire\Beam\AnalyticsGoogle\Ga4;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * The install's GA4 configuration (`beam.analytics-google.*`), checked without ever exposing the credential.
 *
 * - The property is the NUMERIC GA4 property id. A `G-` measurement id (what the site's tag uses, and what
 *   laravel-beam-analytics stores for rendering) cannot address the Data API, so it is refused, never converted.
 * - The credential is a service account's JSON key file, by path. {@see problems()} says what is wrong in words that name
 *   no secret: never the key, the client email or the file's contents.
 */
final class Ga4Config
{
    public const KEY = 'beam.analytics-google';

    public function __construct(private readonly Config $config) {}

    /** The numeric property id, or null when it is missing or is not one (a `G-` measurement id included). */
    public function propertyId(): ?string
    {
        $id = trim((string) $this->config->get(self::KEY.'.property_id', ''));

        return preg_match('/^[0-9]{1,20}$/', $id) === 1 ? $id : null;
    }

    /** The configured credential path, or null when none is set. Not read, so not checked here. */
    public function credentialsPath(): ?string
    {
        $path = trim((string) $this->config->get(self::KEY.'.credentials', ''));

        return $path === '' ? null : $path;
    }

    /**
     * What stops the install's property from being read, in words that expose no credential. Empty when it is configured.
     * A local check only (config and the key file), never an outbound call.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];
        $raw = trim((string) $this->config->get(self::KEY.'.property_id', ''));

        if ($raw === '') {
            $problems[] = 'no GA4 property id is set (beam.analytics-google.property_id)';
        } elseif (preg_match('/^G-/i', $raw) === 1) {
            $problems[] = 'the property id is a G- measurement id; the Data API needs the NUMERIC property id (Admin → Property settings)';
        } elseif ($this->propertyId() === null) {
            $problems[] = 'the property id is not a numeric GA4 property id';
        }

        $path = $this->credentialsPath();

        if ($path === null) {
            $problems[] = 'no service account key is set (beam.analytics-google.credentials)';
        } elseif (! is_file($path) || ! is_readable($path)) {
            $problems[] = 'the service account key file is missing or unreadable';
        } elseif ($this->serviceAccount() === null) {
            $problems[] = 'the service account key file is not a service account JSON key (it needs client_email and private_key)';
        }

        return $problems;
    }

    public function isConfigured(): bool
    {
        return $this->problems() === [];
    }

    /**
     * The service account's email and private key, read from the key file only to sign a token request. Null when the file is
     * not a service account key. Never logged, never put in a message.
     *
     * @return array{client_email: string, private_key: string}|null
     */
    public function serviceAccount(): ?array
    {
        $path = $this->credentialsPath();

        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || ! is_string($json['client_email'] ?? null) || ! is_string($json['private_key'] ?? null)
            || $json['client_email'] === '' || ! str_contains($json['private_key'], 'PRIVATE KEY')) {
            return null;
        }

        return ['client_email' => $json['client_email'], 'private_key' => $json['private_key']];
    }

    public function dataUrl(): string
    {
        return rtrim((string) $this->config->get(self::KEY.'.data_url', 'https://analyticsdata.googleapis.com/v1beta'), '/');
    }

    public function tokenUrl(): string
    {
        return rtrim((string) $this->config->get(self::KEY.'.token_url', 'https://oauth2.googleapis.com'), '/');
    }
}
