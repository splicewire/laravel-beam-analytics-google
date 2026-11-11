<?php

// Ticket 14: the base package must not gain a Google dependency; a site that does not install this child carries none.

it('leaves the base package with no Google or Saloon dependency', function () {
    $base = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/vendor/splicewire/laravel-beam-analytics/composer.json'), true);
    $requires = array_keys(array_merge($base['require'] ?? [], $base['require-dev'] ?? []));

    expect(array_filter($requires, fn (string $name): bool => (bool) preg_match('/google|saloon|ga4/i', $name)))->toBe([]);
});

it('requires the base package, which the child plugs into', function () {
    $own = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    expect($own['require'])->toHaveKey('splicewire/laravel-beam-analytics')->toHaveKey('saloonphp/saloon');
});
