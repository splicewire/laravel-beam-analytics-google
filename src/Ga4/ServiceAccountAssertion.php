<?php

namespace Splicewire\Beam\AnalyticsGoogle\Ga4;

use RuntimeException;

/**
 * The JWT a service account signs to ask Google's token endpoint for an access token (RFC 7523): RS256 over
 * `{iss, scope, aud, iat, exp}`, scoped read-only to Analytics. Built here with openssl rather than a Google SDK, so a site
 * that installs this child gains Saloon and nothing larger.
 */
final class ServiceAccountAssertion
{
    public const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    /** @param  array{client_email: string, private_key: string}  $account */
    public static function sign(array $account, string $audience, int $now): string
    {
        $segments = [
            self::encode(['alg' => 'RS256', 'typ' => 'JWT']),
            self::encode(['iss' => $account['client_email'], 'scope' => self::SCOPE, 'aud' => $audience, 'iat' => $now, 'exp' => $now + 3600]),
        ];

        $key = openssl_pkey_get_private($account['private_key']);
        if ($key === false || ! openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            // Never the key, nor openssl's error text (it can quote it): just that it could not sign.
            throw new RuntimeException('The service account key could not sign a token request.');
        }

        $segments[] = self::base64url($signature);

        return implode('.', $segments);
    }

    private static function encode(array $part): string
    {
        return self::base64url((string) json_encode($part, JSON_UNESCAPED_SLASHES));
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
