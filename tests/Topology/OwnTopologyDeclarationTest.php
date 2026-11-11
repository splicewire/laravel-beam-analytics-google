<?php

/*
 * This package's own `extra.package-topology`, checked against its own runtime `require`.
 *
 * Why not `AssertsDeclaredTopology` here: `DeclaredContractSource` reads only VENDORED manifests, and a
 * package does not vendor itself, so in this repo that assertion runs over zero of this package's rules
 * and passes by not looking (measured at splicewire/laravel-beam-market; see splicewire/laravel-beam-dev's
 * `OwnTopologyDeclarationTest`). The graph-level half — transitive `downOnly`/`neverReaches` and
 * `mustBeAcyclic` — runs where this package IS vendored: a host's `DeclaredTopologyTest`.
 *
 * `require-dev` is out of scope, matching the tool: its graph is runtime `require` only.
 *
 * The fences below are a deliberate second copy of the manifest's, written with it by `pnpm package:new`
 * (splicewire-ecosystem): changing either one is a failing test until the other changes with it.
 */

const OWN_TOPOLOGY_FENCES = [
    'mustRequire' => [
        'splicewire/laravel-beam',
    ],
    'mustNotRequire' => [
        'splicewire/laravel-beam-market',
        'splicewire/tower',
        'splicewire/laravel-satellite*',
        'splicewire/laravel-tower-*',
    ],
];

function ownManifest(): array
{
    $path = __DIR__.'/../../composer.json';
    $decoded = json_decode((string) file_get_contents($path), true);

    expect($decoded)->toBeArray("Could not read {$path}");

    return $decoded;
}

function ownTopology(): array
{
    $topology = ownManifest()['extra']['package-topology'] ?? null;

    // A missing declaration must FAIL. "No rules" and "no violations" are otherwise the same green.
    expect($topology)->toBeArray('extra.package-topology is absent from composer.json');

    return $topology;
}

/** The names a direct `require` must never match: `mustNotRequire` globs, and `downOnly`'s direct half. */
function ownForbidden(): array
{
    return [...(ownTopology()['mustNotRequire'] ?? []), ...(ownTopology()['downOnly'] ?? [])];
}

function ownMatchesAny(string $package, array $globs): bool
{
    foreach ($globs as $glob) {
        if (fnmatch($glob, $package)) {
            return true;
        }
    }

    return false;
}

it('declares its fences, pinned so narrowing one is a failing test rather than a quiet edit', function () {
    $fences = ownTopology();
    $because = $fences['because'] ?? '';
    unset($fences['because']);

    expect($fences)->toBe(OWN_TOPOLOGY_FENCES)
        ->and($because)->not->toBe('');
});

it('requires every package it declares it must require', function () {
    $requires = array_keys(ownManifest()['require'] ?? []);

    expect(array_values(array_diff(ownTopology()['mustRequire'] ?? [], $requires)))->toBe([]);
});

it('requires nothing its fences forbid', function () {
    $requires = array_keys(ownManifest()['require'] ?? []);

    expect($requires)->not->toBeEmpty('No runtime requires read; the manifest probe is broken.');

    $violations = array_values(array_filter($requires, fn (string $p): bool => ownMatchesAny($p, ownForbidden())));

    expect($violations)->toBe([]);
});

it('would catch a forbidden require, and does not forbid what it must require', function () {
    foreach ([
        'splicewire/laravel-beam-market',
        'splicewire/tower',
        'splicewire/laravel-satellite',
        'splicewire/laravel-satellite-probe',
        'splicewire/laravel-tower-probe',
    ] as $planted) {
        expect(ownMatchesAny($planted, ownForbidden()))->toBeTrue("A require on {$planted} would NOT be caught.");
    }

    foreach (ownTopology()['mustRequire'] ?? [] as $required) {
        expect(ownMatchesAny($required, ownForbidden()))->toBeFalse("The fences forbid {$required}, which they also require.");
    }
});
