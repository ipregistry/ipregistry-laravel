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
make stan       # PHPStan at level max
make cs         # check coding style
make cs-fix     # fix coding style
```

The test suite runs on [Orchestra Testbench](https://packages.tools/testbench) and never contacts the Ipregistry API.

## Guidelines

- Match the existing code style (`@Symfony` + risky rules via PHP CS Fixer; the header comment is enforced).
- Keep PHPStan at level max passing.
- Add tests for any behavior change.
- Update `CHANGELOG.md` under `[Unreleased]`.

## Reporting issues

Use the [issue tracker](https://github.com/ipregistry/ipregistry-laravel/issues). For questions about the API itself, email support@ipregistry.co.
