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

namespace Ipregistry\Laravel;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Ipregistry\IpregistryClient;
use Ipregistry\Laravel\Console\LookupCommand;
use Ipregistry\Laravel\Http\Middleware\BlockCountries;
use Ipregistry\Laravel\Http\Middleware\BlockThreats;
use Ipregistry\Laravel\Http\Middleware\EnrichWithIpregistry;
use Ipregistry\Model\IpInfo;
use Psr\SimpleCache\CacheInterface;

/**
 * Wires the Ipregistry client and service into the container, registers the
 * `ipregistry`, `ipregistry.countries`, and `ipregistry.threats` middleware
 * aliases, the `$request->ipregistry()` macro, the `ipregistry:lookup`
 * command, and the config publishing.
 */
final class IpregistryServiceProvider extends ServiceProvider
{
    /** The EU-based API endpoint selected by the 'eu' base URL shortcut. */
    public const EU_BASE_URL = 'https://eu.api.ipregistry.co';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ipregistry.php', 'ipregistry');

        $this->app->singleton(IpregistryClient::class, static function (Container $app): IpregistryClient {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('ipregistry', []);
            /** @var array<string, mixed> $retries */
            $retries = \is_array($config['retries'] ?? null) ? $config['retries'] : [];
            /** @var array<string, mixed> $cache */
            $cache = \is_array($config['cache'] ?? null) ? $config['cache'] : [];

            return new IpregistryClient(
                apiKey: \is_string($config['api_key'] ?? null) ? $config['api_key'] : '',
                baseUrl: self::resolveBaseUrl(\is_string($config['base_url'] ?? null) ? $config['base_url'] : null),
                timeout: self::floatValue($config['timeout'] ?? null, 5.0),
                maxRetries: self::intValue($retries['max'] ?? null, 1),
                retryInterval: self::floatValue($retries['interval'] ?? null, 1.0),
                retryOnServerError: (bool) ($retries['on_server_error'] ?? true),
                retryOnTooManyRequests: (bool) ($retries['on_too_many_requests'] ?? false),
                cache: self::resolveCache($app, $cache),
                cacheTtl: self::intValue($cache['ttl'] ?? null, 600),
                userAgent: 'IpregistryLaravel/'.Ipregistry::VERSION,
            );
        });

        $this->app->singleton(Ipregistry::class, static function (Container $app): Ipregistry {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('ipregistry', []);
            $fields = $config['fields'] ?? null;
            $developmentIp = $config['development_ip'] ?? null;

            return new Ipregistry(
                $app->make(IpregistryClient::class),
                fields: \is_string($fields) && '' !== $fields ? $fields : null,
                hostname: ($config['hostname'] ?? false) ? true : null,
                developmentIp: \is_string($developmentIp) && '' !== $developmentIp ? $developmentIp : null,
            );
        });

        $this->app->alias(Ipregistry::class, 'ipregistry');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/ipregistry.php' => $this->app->configPath('ipregistry.php'),
        ], 'ipregistry-config');

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('ipregistry', EnrichWithIpregistry::class);
        $router->aliasMiddleware('ipregistry.countries', BlockCountries::class);
        $router->aliasMiddleware('ipregistry.threats', BlockThreats::class);

        // Lets any code holding the request — controllers, form requests,
        // Blade views — read the visitor data with $request->ipregistry().
        Request::macro('ipregistry', function (): ?IpInfo {
            /** @var Request $this */
            return \Illuminate\Container\Container::getInstance()
                ->make(Ipregistry::class)
                ->forRequest($this);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([LookupCommand::class]);
        }

        if (class_exists(AboutCommand::class)) {
            AboutCommand::add('Ipregistry', static function (): array {
                /** @var array<string, mixed> $config */
                $config = config('ipregistry', []);
                /** @var array<string, mixed> $cache */
                $cache = \is_array($config['cache'] ?? null) ? $config['cache'] : [];
                $store = $cache['store'] ?? null;

                return [
                    'Version' => Ipregistry::VERSION,
                    'API Key' => filled($config['api_key'] ?? null)
                        ? '<fg=green;options=bold>SET</>'
                        : '<fg=yellow;options=bold>MISSING</>',
                    'Base URL' => self::resolveBaseUrl(\is_string($config['base_url'] ?? null) ? $config['base_url'] : null),
                    'Fields' => \is_string($config['fields'] ?? null) && '' !== $config['fields'] ? $config['fields'] : 'all',
                    'Cache' => ($cache['enabled'] ?? true)
                        ? \sprintf(
                            '%s (%ds)',
                            \is_string($store) && '' !== $store ? $store : 'default store',
                            self::intValue($cache['ttl'] ?? null, 600),
                        )
                        : 'disabled',
                ];
            });
        }
    }

    /**
     * Maps the 'base_url' configuration value to an API endpoint: null or
     * empty selects the default endpoint, 'eu' the EU-based endpoint, and
     * anything else is used verbatim.
     */
    public static function resolveBaseUrl(?string $baseUrl): string
    {
        return match (true) {
            null === $baseUrl, '' === $baseUrl => IpregistryClient::DEFAULT_BASE_URL,
            'eu' === strtolower($baseUrl) => self::EU_BASE_URL,
            default => $baseUrl,
        };
    }

    private static function floatValue(mixed $value, float $default): float
    {
        return \is_int($value) || \is_float($value) || (\is_string($value) && is_numeric($value))
            ? (float) $value
            : $default;
    }

    private static function intValue(mixed $value, int $default): int
    {
        return \is_int($value) || \is_float($value) || (\is_string($value) && is_numeric($value))
            ? (int) $value
            : $default;
    }

    /**
     * Returns the configured Laravel cache store to memoize lookups in, or
     * null when caching is disabled. Laravel cache repositories implement
     * PSR-16, which is what the Ipregistry client expects.
     *
     * @param array<string, mixed> $config
     */
    private static function resolveCache(Container $app, array $config): ?CacheInterface
    {
        if (!($config['enabled'] ?? true)) {
            return null;
        }

        /** @var CacheManager $manager */
        $manager = $app->make('cache');
        $store = $config['store'] ?? null;

        return $manager->store(\is_string($store) && '' !== $store ? $store : null);
    }
}
