<?php

// splicewire/laravel-beam-analytics-google, merged under `beam.analytics-google.*`.
//
// The install's Google Analytics 4 property, read through the GA4 Data API by a service account. INSTALL scope only: a tenant
// never reads these (Ga4TrafficSource::covers() is false for every tenant scope).

return [

    // The NUMERIC GA4 property id (Admin → Property settings → Property ID, e.g. 123456789). NOT the `G-` measurement id the
    // site's tag uses: the Data API addresses a property by number, and a `G-` id is refused (the doctor says so).
    'property_id' => env('BEAM_ANALYTICS_GOOGLE_PROPERTY_ID'),

    // The path to the service account's JSON key file. The service account needs the property's Viewer role. The key is read
    // only to sign the token request; it is never logged, reported or shown.
    'credentials' => env('BEAM_ANALYTICS_GOOGLE_CREDENTIALS'),

    // Where the Data API and Google's token endpoint live. Overridable so a host can point them at a local stub; never needed
    // in production.
    'data_url' => env('BEAM_ANALYTICS_GOOGLE_DATA_URL', 'https://analyticsdata.googleapis.com/v1beta'),
    'token_url' => env('BEAM_ANALYTICS_GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com'),

];
