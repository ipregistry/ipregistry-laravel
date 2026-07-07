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

namespace Ipregistry\Laravel\Tests\System;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Ipregistry\Laravel\Ipregistry;
use Ipregistry\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * System tests run against the live Ipregistry API, through the full
 * Laravel wiring (service provider, cache store, middleware, macro, and
 * command). They are excluded from the default test run and skip unless the
 * IPREGISTRY_API_KEY environment variable is set:
 *
 *     IPREGISTRY_API_KEY=YOUR_API_KEY vendor/bin/phpunit --testsuite system
 *
 * A valid API key is required; each successful lookup consumes credits.
 */
#[Group('system')]
final class SystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $apiKey = getenv('IPREGISTRY_API_KEY');
        if (false === $apiKey || '' === $apiKey) {
            self::markTestSkipped('set IPREGISTRY_API_KEY to run system tests');
        }
    }

    /**
     * @param Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('ipregistry.api_key', (string) getenv('IPREGISTRY_API_KEY'));
        $app['config']->set('ipregistry.timeout', 30);
    }

    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/system/geo', static function (Request $request): array {
            /** @var \Ipregistry\Model\IpInfo|null $info */
            $info = $request->ipregistry();

            return [
                'ip' => $info?->ip,
                'country' => $info?->location->country->code,
            ];
        })->middleware('ipregistry:ip,location');

        $router->get('/system/embargo', static fn (): string => 'ok')
            ->middleware('ipregistry.countries:block,KP');
    }

    public function testLookupThroughContainerAndCache(): void
    {
        $service = $this->laravel()->make(Ipregistry::class);

        $info = $service->lookup('8.8.8.8');

        self::assertSame('8.8.8.8', $info->ip);
        self::assertNotSame('', $info->location->country->code);
        self::assertNotNull($info->connection->asn, 'expected a non-null ASN for a well-known address');

        // Served from the Laravel cache store: equal data, no extra credit.
        self::assertEquals($info, $service->lookup('8.8.8.8'));
    }

    public function testLookupOrigin(): void
    {
        $origin = $this->laravel()->make(Ipregistry::class)->lookupOrigin();

        self::assertNotSame('', $origin->ip);
    }

    public function testLookupBatchWithPerEntryError(): void
    {
        $list = $this->laravel()->make(Ipregistry::class)->lookupBatch(['8.8.8.8', '1.1.1.1', 'not-an-ip']);

        self::assertCount(3, $list);
        self::assertSame('8.8.8.8', $list->at(0)->ip);
        self::assertNotNull($list->results[2]->error, 'entry 2 (invalid IP) should have failed');
    }

    public function testMiddlewareAndMacroEndToEnd(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->getJson('/system/geo')
            ->assertOk();

        self::assertSame('8.8.8.8', $response->json('ip'));

        $country = $response->json('country');
        self::assertIsString($country);
        self::assertNotSame('', $country);
    }

    public function testBlockCountriesLetsNonListedCountryThrough(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->get('/system/embargo')
            ->assertOk();
    }

    public function testLookupCommand(): void
    {
        $this->pendingCommand('ipregistry:lookup', ['ip' => ['8.8.8.8'], '--fields' => 'ip,location'])
            ->expectsOutputToContain('8.8.8.8')
            ->assertSuccessful();
    }

    public function testParseUserAgents(): void
    {
        $list = $this->laravel()->make(Ipregistry::class)->parseUserAgents(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
        );

        self::assertCount(1, $list);
        self::assertNotSame('', $list->at(0)->name);
    }
}
