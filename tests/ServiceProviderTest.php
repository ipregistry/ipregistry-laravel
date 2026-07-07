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

namespace Ipregistry\Laravel\Tests;

use Illuminate\Http\Request;
use Ipregistry\IpregistryClient;
use Ipregistry\Laravel\Ipregistry;
use Ipregistry\Laravel\IpregistryServiceProvider;

final class ServiceProviderTest extends TestCase
{
    public function testClientIsRegisteredAsSingleton(): void
    {
        $client = $this->laravel()->make(IpregistryClient::class);

        self::assertInstanceOf(IpregistryClient::class, $client);
        self::assertSame($client, $this->laravel()->make(IpregistryClient::class));
    }

    public function testServiceIsRegisteredAsSingletonWithAlias(): void
    {
        $service = $this->laravel()->make(Ipregistry::class);

        self::assertSame($service, $this->laravel()->make(Ipregistry::class));
        self::assertSame($service, $this->laravel()->make('ipregistry'));
    }

    public function testRequestMacroIsRegistered(): void
    {
        self::assertTrue(Request::hasMacro('ipregistry'));
    }

    public function testMiddlewareAliasesAreRegistered(): void
    {
        $aliases = $this->laravel()->make('router')->getMiddleware();

        self::assertArrayHasKey('ipregistry', $aliases);
        self::assertArrayHasKey('ipregistry.countries', $aliases);
        self::assertArrayHasKey('ipregistry.threats', $aliases);
    }

    public function testBaseUrlResolution(): void
    {
        self::assertSame(IpregistryClient::DEFAULT_BASE_URL, IpregistryServiceProvider::resolveBaseUrl(null));
        self::assertSame(IpregistryClient::DEFAULT_BASE_URL, IpregistryServiceProvider::resolveBaseUrl(''));
        self::assertSame(IpregistryServiceProvider::EU_BASE_URL, IpregistryServiceProvider::resolveBaseUrl('eu'));
        self::assertSame(IpregistryServiceProvider::EU_BASE_URL, IpregistryServiceProvider::resolveBaseUrl('EU'));
        self::assertSame('https://example.test', IpregistryServiceProvider::resolveBaseUrl('https://example.test'));
    }

    public function testConfigIsMerged(): void
    {
        self::assertSame('test-key', config('ipregistry.api_key'));
        self::assertTrue(config('ipregistry.cache.enabled'));
        self::assertSame(600, config('ipregistry.cache.ttl'));
        self::assertTrue(config('ipregistry.fail_open'));
    }
}
