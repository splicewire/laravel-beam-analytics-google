# splicewire/laravel-beam-analytics-google

The Google Analytics child: a GA4 Data API TrafficSource for laravel-beam-analytics, install scope only.

A Composer Beam Extension: require it in a Beam host and it registers itself into the manifests beam
already binds; nothing in the host is edited.

It gives laravel-beam-analytics' **Traffic** area real numbers for the INSTALL's own site. These are its users, sessions and views, the daily trend, the top pages and the top referrers, read from the install's Google Analytics 4 property through the GA4 Data API (`properties/{id}:runReport`).

A site that does not install this child carries no Google dependency and no service account key. The base package has neither.

## Install

Require `splicewire/laravel-beam-analytics-google`. It requires `splicewire/laravel-beam` and
`splicewire/laravel-beam-analytics`, and adds `saloonphp/saloon` (no Google SDK). The provider is auto-discovered, and
`splicewire:beam:install` publishes its config with the rest of the stack. To publish the config alone:

```bash
php artisan vendor:publish --tag=beam-analytics-google-config
```

## Connect a property

1. **Find the NUMERIC property id.** In Google Analytics, open Admin, then Property settings, then **Property ID**, for example `123456789`.
   This is not the `G-XXXXXXX` measurement id the site's tag uses. The Data API addresses a property by number, and a `G-`
   id is refused (doctor says so).
2. **Create a service account and its JSON key.** In Google Cloud, enable the **Google Analytics Data API** for the project. Create a
   service account and download a JSON key for it.
3. **Give the service account read access to the property.** In Google Analytics, open Admin, then Property access management. Add the service
   account's email with the **Viewer** role.
4. **Configure the install** (`.env`, never committed):

   ```dotenv
   BEAM_ANALYTICS_GOOGLE_PROPERTY_ID=123456789
   BEAM_ANALYTICS_GOOGLE_CREDENTIALS=/absolute/path/to/service-account.json
   ```

   Keep the key file outside the web root. It is read only to sign a token request. It is never logged, reported or shown.
5. **Run a queue worker.** Traffic is read by laravel-beam-analytics' refresh job, never on a reader's request.
6. **Check it.** Run `php artisan splicewire:beam:doctor`. It reports **Google Analytics property** as pass, or as warn with what to fix.
   It never prints the key. A key file whose private key does not load as an RSA key that can sign is a WARN, not a pass.

Traffic then reads "Not measured yet" until the first refresh lands, then the install's numbers.

## How it behaves

- **Install scope only.** The install's property measures the install's site. For every TENANT scope the source does not
  cover (`Ga4TrafficSource::covers()` is false), so a tenant reads its own honest "Not connected", never the install's
  numbers. That holds even with the install's credentials configured. Per-tenant Google is a later ticket (OAuth).
- **No call on a request.** `covers()` reads config and checks the key file locally. Google is asked only by the refresh job.
- **Honest failures.** A refused credential, an exhausted quota or any other failure is reported, and the reader keeps
  laravel-beam-analytics' honest state. That state is "Couldn't be measured" with no earlier reading, or the last good numbers marked failed.
  An empty report is a measured zero. A failure's message names its kind and HTTP status only, never a credential.
- **Each period is its own report, EXACTLY N days.** The period comes from the Traffic page's period facet (7, 28 or 90 days),
  and the overview reads 28. GA4's DateRange is inclusive at both ends, so "Last 7 days" asks for `6daysAgo..today`: seven
  dates ending TODAY, which is what laravel-beam-analytics' "measured at · Last N days" means.

## Registries

This package declares no registries. It registers into:

- [`beam.install.steps`](https://github.com/splicewire/laravel-beam/blob/main/docs/registries/beam.install.steps.mdx):
  its install step (`beam-analytics-google-config`, order 900), in `src/Concerns/WiresInstallStep.php`.
- `beam.analytics.sources` (laravel-beam-analytics): `Ga4TrafficSource` under the key `google-analytics`, by class name
  string, when the host resolves the registry (`src/Concerns/WiresTrafficSource.php`).
- beam's doctor manifest: `Ga4ConfigAudit`, advisory (`src/Concerns/WiresDoctorAudit.php`).

## Testing

Run `composer test`. No test reaches Google. Saloon's `MockClient` answers every request, the provider URLs point at a
closed local port, and `preventStrayRequests` refuses anything unmocked. Each run generates its own throwaway service
account key.

A REAL `runReport` against a real property is owner-run. It needs a real credential and property id, and it stays
**unmeasured** until an owner runs it.
