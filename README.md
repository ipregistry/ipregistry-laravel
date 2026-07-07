[<img src="https://cdn.ipregistry.co/icons/favicon-96x96.png" alt="Ipregistry" width="64"/>](https://ipregistry.co/)

# Ipregistry Laravel Library

[![License](http://img.shields.io/:license-apache-blue.svg)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/ipregistry/ipregistry-laravel)](https://packagist.org/packages/ipregistry/ipregistry-laravel)
[![CI](https://github.com/ipregistry/ipregistry-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/ipregistry/ipregistry-laravel/actions/workflows/ci.yml)
[![Lint](https://github.com/ipregistry/ipregistry-laravel/actions/workflows/lint.yml/badge.svg)](https://github.com/ipregistry/ipregistry-laravel/actions/workflows/lint.yml)

This is the official Laravel integration for the [Ipregistry](https://ipregistry.co) IP geolocation and threat data API. It is built on top of the official [`ipregistry/ipregistry-php`](https://github.com/ipregistry/ipregistry-php) client library and makes it feel native to Laravel: auto-discovered configuration, a facade, a request macro, route middleware for country and threat blocking, Laravel cache integration, a first-class testing fake, and an artisan command.

```php
Route::get('/welcome', function (Request $request) {
    return 'Hello ' . ($request->ipregistry()?->location->country->name ?? 'visitor') . '!';
});
```

## Features

- **One call anywhere**: `$request->ipregistry()` returns the visitor's data from any controller, form request, or view. The API is queried at most once per request.
- **Route middleware**: enrich requests, block countries (`ipregistry.countries:block,KP,IR`), and block threats, proxies, Tor, or VPNs (`ipregistry.threats:tor,vpn`) with one line.
- **Laravel caching**: lookups are memoized in any of your configured cache stores (Redis, Valkey, Memcached, and so on), so repeated visits from the same IP do not consume additional credits.
- **GDPR helper**: `Ipregistry::isEu()` based on the API's `location.in_eu` field.
- **Safe by default**: fails open when Ipregistry is unreachable, honors your trusted proxy configuration, and never sends private IP addresses to the API.
- **Testable**: `Ipregistry::fake()` swaps the service for a fake with canned responses and assertions. No HTTP, no credits.
- **Batteries included**: `php artisan ipregistry:lookup 8.8.8.8` and `php artisan about` integration.

## Getting started

You need an Ipregistry API key. Sign up at [https://ipregistry.co](https://ipregistry.co) to get one along with free lookups.

### Requirements

- PHP 8.2 or newer (8.3+ for Laravel 13)
- Laravel 12 or 13

### Installation

```sh
composer require ipregistry/ipregistry-laravel
```

Add your API key to `.env`:

```sh
IPREGISTRY_API_KEY=YOUR_API_KEY
```

That's it. The service provider is auto-discovered. Optionally publish the configuration file:

```sh
php artisan vendor:publish --tag=ipregistry-config
```

### Quick start

Read the visitor's data anywhere you have the request. The lookup runs lazily on first access, is cached, and returns `null` instead of throwing when the visitor IP is private (localhost) or Ipregistry is unreachable:

```php
Route::get('/welcome', function (Request $request) {
    $info = $request->ipregistry(); // ?IpInfo

    return view('welcome', [
        'country' => $info?->location->country->name,
        'city' => $info?->location->city,
        'currency' => $info?->currency->code,
    ]);
});
```

Or use the facade for explicit lookups. These throw on failure, like the underlying client:

```php
use Ipregistry\Laravel\Facades\Ipregistry;

$info = Ipregistry::lookup('54.85.132.205');
$list = Ipregistry::lookupBatch(['8.8.8.8', '1.1.1.1']);
$origin = Ipregistry::lookupOrigin();                 // your server's own IP
$agents = Ipregistry::parseUserAgents($userAgent);

Ipregistry::forRequest($request);                     // same as $request->ipregistry()
```

Dependency injection works too: type-hint `Ipregistry\Laravel\Ipregistry`, or the raw `Ipregistry\IpregistryClient` for direct SDK access.

## Middleware

### Enriching requests

The `ipregistry` middleware performs the lookup before your handlers run. Middleware parameters select the response fields for the route, keeping responses small and fast:

```php
Route::middleware('ipregistry:ip,location,security')->group(function () {
    // $request->ipregistry() answers from memory in here.
});
```

The middleware is optional: `$request->ipregistry()` triggers the lookup lazily wherever it is first called. Use the middleware when you want the data fetched up front, a per-route field selection, or fail-closed behavior.

### Blocking countries

Respond with `451 Unavailable For Legal Reasons` by ISO 3166-1 alpha-2 country code:

```php
// Block visitors from the listed countries:
Route::middleware('ipregistry.countries:block,KP,IR')->group(...);

// Or only allow visitors from the listed countries:
Route::middleware('ipregistry.countries:allow,FR,BE')->group(...);
```

### Blocking proxies, Tor, and threats

Respond with `403 Forbidden` to visitors flagged by Ipregistry security data. The `is_threat`, `is_attacker`, and `is_abuser` signals are always blocked; anonymization signals are opt-in:

```php
// Threats, attackers, and abusers:
Route::middleware('ipregistry.threats')->group(...);

// Additionally block proxies, Tor, and VPNs:
Route::middleware('ipregistry.threats:proxy,tor,vpn')->group(...);
```

Accepted signals: `proxy`, `tor`, `vpn`, `relay`, `anonymous`. Each maps to the same-named `security.is_*` field of the Ipregistry response (`tor` also covers `is_tor_exit`).

### Fail open, fail closed

All middleware fail open by default: when the country or threat status could not be determined (private IP, timeout, API error), the visitor is let through and the failure is reported to your exception handler. An Ipregistry outage never locks users out.

For security-sensitive apps that must not serve traffic without IP intelligence, set `IPREGISTRY_FAIL_OPEN=false` (or `'fail_open' => false`): the `ipregistry` middleware then responds with 503 when a lookup fails.

Ad-hoc decisions stay plain Laravel, no special API needed:

```php
if ($request->ipregistry()?->security->isTor) {
    abort(403, 'Not available over Tor.');
}
```

## GDPR and EU detection

```php
use Ipregistry\Laravel\Facades\Ipregistry;

if (Ipregistry::isEu($request)) {
    // Show the cookie consent banner.
}

// Default to showing consent UIs when the data is missing:
Ipregistry::isEu($request, assumeEu: true);
```

`isThreat()` and `isBot()` follow the same pattern and also accept an `IpInfo` or nothing (current request):

```php
Ipregistry::isThreat($request, tor: true, vpn: true);
Ipregistry::isBot($request);   // lightweight User-Agent heuristic, no API call
```

## Configuration

Everything is configured in `config/ipregistry.php`, backed by environment variables:

| Option | Environment variable | Default | Description |
|---|---|---|---|
| `api_key` | `IPREGISTRY_API_KEY` | None | Your Ipregistry API key. |
| `base_url` | `IPREGISTRY_BASE_URL` | default endpoint | API endpoint; `eu` selects the EU-based endpoint, or set a full URL. |
| `cache.enabled` | `IPREGISTRY_CACHE_ENABLED` | `true` | Cache successful lookups. |
| `cache.store` | `IPREGISTRY_CACHE_STORE` | default store | Any store from `config/cache.php`. |
| `cache.ttl` | `IPREGISTRY_CACHE_TTL` | `600` | Cache lifetime in seconds. |
| `development_ip` | `IPREGISTRY_DEVELOPMENT_IP` | None | Fixed public IP used when the client IP is private (localhost). |
| `fail_open` | `IPREGISTRY_FAIL_OPEN` | `true` | Let requests through when lookups fail. |
| `fields` | `IPREGISTRY_FIELDS` | full response | Default field selection for all lookups, e.g. `ip,location,security`. |
| `hostname` | `IPREGISTRY_HOSTNAME` | `false` | Resolve reverse-DNS hostnames. |
| `retries.max` | `IPREGISTRY_RETRIES` | `1` | Automatic retries; kept low so failures never stall page loads. |
| `timeout` | `IPREGISTRY_TIMEOUT` | `5` | Per-request timeout in seconds. |

> Tip: set `IPREGISTRY_FIELDS` to fetch only what you use, keeping payloads small and lookups fast. For example, `ip,location,security` covers geo features, blocking, and GDPR detection.

`php artisan about` shows the effective configuration at a glance.

### Client IP and trusted proxies

The visitor IP comes from `$request->ip()`, so it honors Laravel's [trusted proxy configuration](https://laravel.com/docs/requests#configuring-trusted-proxies). Behind a load balancer or CDN, configure your trusted proxies (e.g. in `bootstrap/app.php`), otherwise the extracted IP will be your proxy's, not your visitor's:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(at: '10.0.0.0/8');
})
```

Private and reserved addresses are never sent to the API. On localhost that means `$request->ipregistry()` returns `null`. Set a development IP to exercise geo features:

```sh
# .env (local only)
IPREGISTRY_DEVELOPMENT_IP=66.165.2.7
```

### Caching

Successful lookups are stored in the configured Laravel cache store with a 10-minute lifetime by default, keyed by IP and lookup options. With Redis or Valkey the cache is shared across workers and deploys. Within a single request the result is additionally memoized on the request object, so middleware, guards, controllers, and views share one lookup.

### Laravel Octane

The package is Octane-friendly: the client and service are stateless singletons, and per-visitor data is attached to the request instance, never to shared state.

## Testing

Call `Ipregistry::fake()` in your tests to replace the service with a fake. No HTTP request is ever sent, and lookups are recorded for assertions:

```php
use Ipregistry\Laravel\Facades\Ipregistry;

public function test_eu_visitors_see_consent_banner(): void
{
    Ipregistry::fake([
        '*' => ['location' => ['country' => ['code' => 'FR'], 'in_eu' => true]],
    ]);

    $this->get('/')->assertSee('cookie-consent');
}

public function test_tor_visitors_cannot_checkout(): void
{
    $fake = Ipregistry::fake([
        '1.2.3.4' => ['security' => ['is_tor' => true]],
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
        ->post('/checkout')
        ->assertForbidden();

    $fake->assertLookedUp('1.2.3.4');
}
```

Responses are keyed by IP address (`'*'` is the fallback, `'origin'` answers origin lookups) and use the API's payload shape; the `ip` key is filled in for you. Values can also be ready-made `IpInfo` instances, or `Throwable`s to simulate failures. Unlike the real service, the fake looks up private IPs too, so feature tests work without trusted-proxy setup.

Available assertions: `assertLookedUp()`, `assertNotLookedUp()`, `assertLookedUpTimes()`, `assertNothingLookedUp()`, `assertOriginLookedUp()`, `assertUserAgentsParsed()`, plus `lookups()` for the raw list.

## Artisan command

Verify your key and inspect responses from the terminal:

```sh
php artisan ipregistry:lookup 8.8.8.8
php artisan ipregistry:lookup 8.8.8.8 1.1.1.1 --fields=location,security
php artisan ipregistry:lookup --hostname      # your server's own IP
```

## Errors

Explicit lookups (`Ipregistry::lookup()` and friends) throw the client library's exceptions: `ApiException` for API-reported failures (with typed `errorCode`) and `ClientException` for network errors. Both extend `IpregistryException`. See the [ipregistry-php error documentation](https://github.com/ipregistry/ipregistry-php#errors).

Request-aware helpers (`$request->ipregistry()`, `Ipregistry::forRequest()`, the middleware) never throw: failures are reported to your exception handler, `null` is returned, and the exception is available as `$request->attributes->get('ipregistry.error')`.

## Migrating from `ipregistry/ipregistry-php`

Keep using the SDK directly for queue jobs and batch pipelines if you like; this package registers a ready-configured `Ipregistry\IpregistryClient` singleton you can inject. What the package adds on top: configuration, request-aware lookups with per-request memoization, IP extraction honoring trusted proxies, Laravel cache wiring, blocking middleware, and the testing fake.

## Other resources

- [API documentation](https://ipregistry.co/docs)
- [Issue tracker](https://github.com/ipregistry/ipregistry-laravel/issues)
- Email: support@ipregistry.co

## License

Apache License 2.0. See [LICENSE](LICENSE).
