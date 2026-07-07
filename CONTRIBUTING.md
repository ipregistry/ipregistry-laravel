# Contributing

Thanks for your interest in contributing to the Ipregistry Laravel library!

## Setup

```sh
composer install
```

Requires PHP 8.2+ with the `curl` and `json` extensions.

## Workflow

```sh
make            # composer validate + code style + static analysis + tests
make test       # run the test suite (offline, no API key needed)
make system     # live system tests (requires IPREGISTRY_API_KEY; consumes credits)
make stan       # PHPStan at level max
make cs         # check coding style
make cs-fix     # fix coding style
```

The default test suite runs on [Orchestra Testbench](https://packages.tools/testbench) and never contacts the Ipregistry API. The `system` suite exercises the live API through the full Laravel wiring; it skips cleanly when `IPREGISTRY_API_KEY` is not set.

## Releasing

Releases are cut from the Actions tab (Release > Run workflow) with the version to publish. The workflow verifies `Ipregistry::VERSION` and the matching `CHANGELOG.md` section, runs the full gate including system tests, then tags, publishes the GitHub Release, and notifies Packagist.

## Guidelines

- Match the existing code style (`@Symfony` + risky rules via PHP CS Fixer; the header comment is enforced).
- Keep PHPStan at level max passing.
- Add tests for any behavior change.
- Update `CHANGELOG.md` under `[Unreleased]`.

## Reporting issues

Use the [issue tracker](https://github.com/ipregistry/ipregistry-laravel/issues). For questions about the API itself, email support@ipregistry.co.
