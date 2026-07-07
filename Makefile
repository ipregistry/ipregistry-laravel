.PHONY: all install test system cover stan cs cs-fix validate

all: validate cs stan test

install:
	composer install

test:
	vendor/bin/phpunit

# Live system tests. Requires IPREGISTRY_API_KEY; consumes credits.
system:
	vendor/bin/phpunit --testsuite system

cover:
	vendor/bin/phpunit --coverage-text

stan:
	vendor/bin/phpstan analyse

cs:
	vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	vendor/bin/php-cs-fixer fix

validate:
	composer validate --strict
