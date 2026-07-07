# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-07-07

### Added

- Initial release, built on top of `ipregistry/ipregistry-php`.
- Auto-discovered service provider with `config/ipregistry.php` and environment-based configuration.
- `Ipregistry` facade and container-bound service with `lookup`, `lookupBatch`, `lookupOrigin`, and `parseUserAgents`.
- Request-aware lookups: `$request->ipregistry()` macro and `Ipregistry::forRequest()`, memoized per request, honoring trusted proxies, failing open.
- Route middleware: `ipregistry` (enrichment with per-route field selection), `ipregistry.countries` (block/allow by country), `ipregistry.threats` (threat and anonymization blocking), with typed static builders (`EnrichWithIpregistry::using()`, `BlockCountries::block()/allow()`, `BlockThreats::including()`) validated at route-definition time.
- Guards: `isEu()` (GDPR), `isThreat()`, `isBot()`.
- Laravel cache store integration (PSR-16) for lookup caching.
- `Ipregistry::fake()` testing fake with canned responses and assertions.
- `ipregistry:lookup` artisan command and `php artisan about` integration.

[Unreleased]: https://github.com/ipregistry/ipregistry-laravel/compare/1.0.0...HEAD
[1.0.0]: https://github.com/ipregistry/ipregistry-laravel/releases/tag/1.0.0
