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
use Illuminate\Routing\Router;
use Ipregistry\Exception\ClientException;
use Ipregistry\Laravel\Facades\Ipregistry;

final class MiddlewareTest extends TestCase
{
    /**
     * @param Router $router
     */
    protected function defineRoutes($router): void
    {
        $countryOf = static function (Request $request): array {
            /** @var \Ipregistry\Model\IpInfo|null $info */
            $info = $request->ipregistry();

            return ['country' => $info?->location->country->code];
        };

        $router->get('/geo', $countryOf)->middleware('ipregistry');

        $router->get('/lazy', $countryOf);

        $router->get('/embargo', static fn (): string => 'ok')
            ->middleware('ipregistry.countries:block,KP,IR');

        $router->get('/fr-only', static fn (): string => 'ok')
            ->middleware('ipregistry.countries:allow,FR');

        $router->get('/no-threats', static fn (): string => 'ok')
            ->middleware('ipregistry.threats');

        $router->get('/no-tor', static fn (): string => 'ok')
            ->middleware('ipregistry.threats:tor,vpn');
    }

    public function testEnrichMiddlewareExposesDataToHandlers(): void
    {
        Ipregistry::fake(['*' => ['location' => ['country' => ['code' => 'US']]]]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->getJson('/geo')
            ->assertOk()
            ->assertJson(['country' => 'US']);
    }

    public function testRequestMacroLooksUpLazilyWithoutMiddleware(): void
    {
        $fake = Ipregistry::fake(['*' => ['location' => ['country' => ['code' => 'FR']]]]);

        $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
            ->getJson('/lazy')
            ->assertOk()
            ->assertJson(['country' => 'FR']);

        $fake->assertLookedUp('9.9.9.9');
    }

    public function testLookupIsMemoizedPerRequest(): void
    {
        $fake = Ipregistry::fake(['*' => ['location' => ['country' => ['code' => 'US']]]]);

        // /geo runs the enrich middleware AND calls $request->ipregistry().
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->getJson('/geo')->assertOk();

        $fake->assertLookedUpTimes('8.8.8.8', 1);
    }

    public function testEnrichFailsOpenOnLookupFailure(): void
    {
        Ipregistry::fake(['*' => new ClientException('network down')]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->getJson('/geo')
            ->assertOk()
            ->assertJson(['country' => null]);
    }

    public function testEnrichFailsClosedWhenConfigured(): void
    {
        config()->set('ipregistry.fail_open', false);
        Ipregistry::fake(['*' => new ClientException('network down')]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->getJson('/geo')
            ->assertStatus(503);
    }

    public function testBlockCountriesBlocksListedCountry(): void
    {
        Ipregistry::fake(['*' => ['location' => ['country' => ['code' => 'KP']]]]);

        $this->withServerVariables(['REMOTE_ADDR' => '175.45.176.1'])
            ->get('/embargo')
            ->assertStatus(451);
    }

    public function testBlockCountriesLetsOtherCountriesThrough(): void
    {
        Ipregistry::fake(['*' => ['location' => ['country' => ['code' => 'US']]]]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->get('/embargo')
            ->assertOk();
    }

    public function testBlockCountriesFailsOpenWhenCountryUnknown(): void
    {
        Ipregistry::fake(['*' => new ClientException('network down')]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->get('/embargo')
            ->assertOk();
    }

    public function testAllowModeBlocksUnlistedCountry(): void
    {
        Ipregistry::fake(['*' => ['location' => ['country' => ['code' => 'US']]]]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->get('/fr-only')
            ->assertStatus(451);
    }

    public function testAllowModeLetsListedCountryThrough(): void
    {
        Ipregistry::fake(['*' => ['location' => ['country' => ['code' => 'FR']]]]);

        $this->withServerVariables(['REMOTE_ADDR' => '2.2.2.2'])
            ->get('/fr-only')
            ->assertOk();
    }

    public function testBlockThreatsBlocksFlaggedVisitors(): void
    {
        Ipregistry::fake(['*' => ['security' => ['is_threat' => true]]]);

        $this->withServerVariables(['REMOTE_ADDR' => '6.6.6.6'])
            ->get('/no-threats')
            ->assertStatus(403);
    }

    public function testBlockThreatsIgnoresAnonymizationSignalsByDefault(): void
    {
        Ipregistry::fake(['*' => ['security' => ['is_vpn' => true, 'is_tor' => true]]]);

        $this->withServerVariables(['REMOTE_ADDR' => '6.6.6.6'])
            ->get('/no-threats')
            ->assertOk();
    }

    public function testBlockThreatsBlocksOptInSignals(): void
    {
        Ipregistry::fake(['*' => ['security' => ['is_tor' => true]]]);

        $this->withServerVariables(['REMOTE_ADDR' => '6.6.6.6'])
            ->get('/no-tor')
            ->assertStatus(403);
    }

    public function testBlockThreatsLetsCleanVisitorsThrough(): void
    {
        Ipregistry::fake(['*' => ['security' => []]]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->get('/no-tor')
            ->assertOk();
    }
}
