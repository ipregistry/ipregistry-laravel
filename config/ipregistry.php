<?php

declare(strict_types=1);

/*
 * Copyright 2026 Ipregistry (https://ipregistry.co).
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your Ipregistry API key. Sign up at https://ipregistry.co to get one
    | along with free lookups. Keep it in your environment file; never
    | commit it or expose it client-side.
    |
    */

    'api_key' => env('IPREGISTRY_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The Ipregistry API endpoint. Leave null for the default global
    | endpoint, set to 'eu' to route requests through the EU-based
    | endpoint, or provide a full URL for a private deployment.
    |
    */

    'base_url' => env('IPREGISTRY_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Fields Selection
    |--------------------------------------------------------------------------
    |
    | Restricts every lookup to the given fields, using Ipregistry's field
    | selector syntax (e.g. 'ip,location,security'). Fetching only what
    | you need reduces payload size and speeds up requests. Null fetches
    | the full response. Individual calls can always override this.
    | See https://ipregistry.co/docs/filtering-selecting-fields
    |
    */

    'fields' => env('IPREGISTRY_FIELDS'),

    /*
    |--------------------------------------------------------------------------
    | Hostname Resolution
    |--------------------------------------------------------------------------
    |
    | Whether lookups resolve the reverse-DNS hostname of the IP address.
    | Disabled by default because it slows down lookups.
    |
    */

    'hostname' => (bool) env('IPREGISTRY_HOSTNAME', false),

    /*
    |--------------------------------------------------------------------------
    | Timeout & Retries
    |--------------------------------------------------------------------------
    |
    | The per-request timeout is expressed in seconds. Retries default to a
    | single attempt so a transient failure never stalls a page load for
    | long; increase 'retries.max' for background/batch workloads.
    | Ipregistry does not rate limit by default (it is opt-in per API
    | key), hence 'on_too_many_requests' is false.
    |
    */

    'timeout' => (float) env('IPREGISTRY_TIMEOUT', 5),

    'retries' => [
        'max' => (int) env('IPREGISTRY_RETRIES', 1),
        'interval' => 1.0,
        'on_server_error' => true,
        'on_too_many_requests' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Successful lookups are cached in a Laravel cache store so repeated
    | visits from the same IP do not consume additional credits. 'store'
    | selects any store from config/cache.php (null uses your default
    | store), and 'ttl' is the entry lifetime in seconds.
    |
    */

    'cache' => [
        'enabled' => (bool) env('IPREGISTRY_CACHE_ENABLED', true),
        'store' => env('IPREGISTRY_CACHE_STORE'),
        'ttl' => (int) env('IPREGISTRY_CACHE_TTL', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development IP
    |--------------------------------------------------------------------------
    |
    | On localhost the client IP is private, so no lookup happens and
    | request-aware helpers return null. Set a fixed public IP here to
    | exercise geo features in development. Leave unset in production.
    |
    */

    'development_ip' => env('IPREGISTRY_DEVELOPMENT_IP'),

    /*
    |--------------------------------------------------------------------------
    | Fail Open
    |--------------------------------------------------------------------------
    |
    | When a request-time lookup fails (timeout, API error, missing key),
    | the request proceeds without data by default and blocking middleware
    | lets it through. Set to false to respond with 503 instead, for
    | security-sensitive apps that must not serve traffic without IP
    | intelligence. Failures are always reported to your exception handler.
    |
    */

    'fail_open' => (bool) env('IPREGISTRY_FAIL_OPEN', true),
];
