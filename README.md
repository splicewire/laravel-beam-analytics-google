# splicewire/laravel-beam-analytics-google

The Google Analytics child: a GA4 Data API TrafficSource for laravel-beam-analytics, install scope only.

A Composer Beam Extension: require it in a Beam host and it registers itself into the manifests beam
already binds; nothing in the host is edited.

This release is the package scaffold: the service provider, the `beam.analytics-google` config root and the
install step.

## Install

Require `splicewire/laravel-beam-analytics-google`. It requires `splicewire/laravel-beam`. The provider is auto-discovered, and
`splicewire:beam:install` publishes its config with the rest of the stack. To publish the config alone:

```bash
php artisan vendor:publish --tag=beam-analytics-google-config
```

## Registries

This package declares no registries. It registers into:

- [`beam.install.steps`](https://github.com/splicewire/laravel-beam/blob/main/docs/registries/beam.install.steps.mdx):
  its install step (`beam-analytics-google-config`, order 900), in `src/Concerns/WiresInstallStep.php`.

## Testing

Run `composer test`.
