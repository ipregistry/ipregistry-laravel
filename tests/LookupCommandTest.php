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

use Ipregistry\Exception\ApiException;
use Ipregistry\Laravel\Facades\Ipregistry;

final class LookupCommandTest extends TestCase
{
    public function testLooksUpASingleIp(): void
    {
        Ipregistry::fake([
            '8.8.8.8' => [
                'type' => 'IPv4',
                'location' => ['city' => 'Mountain View', 'country' => ['code' => 'US', 'name' => 'United States']],
                'connection' => ['asn' => 15169, 'organization' => 'Google LLC'],
                'security' => ['is_cloud_provider' => true],
            ],
        ]);

        $this->pendingCommand('ipregistry:lookup', ['ip' => ['8.8.8.8']])
            ->expectsOutputToContain('8.8.8.8')
            ->expectsOutputToContain('United States')
            ->expectsOutputToContain('AS15169 Google LLC')
            ->expectsOutputToContain('cloud provider')
            ->assertSuccessful();
    }

    public function testLooksUpOriginWhenNoIpGiven(): void
    {
        $fake = Ipregistry::fake([
            'origin' => ['ip' => '203.0.113.10', 'location' => ['country' => ['code' => 'DE', 'name' => 'Germany']]],
        ]);

        $this->pendingCommand('ipregistry:lookup')
            ->expectsOutputToContain('203.0.113.10')
            ->expectsOutputToContain('Germany')
            ->assertSuccessful();

        $fake->assertOriginLookedUp();
    }

    public function testReportsPerEntryErrorsWithoutFailing(): void
    {
        Ipregistry::fake([
            '8.8.8.8' => ['location' => ['country' => ['name' => 'United States']]],
            'nope' => new ApiException('invalid IP', rawCode: 'INVALID_IP_ADDRESS'),
        ]);

        $this->pendingCommand('ipregistry:lookup', ['ip' => ['8.8.8.8', 'nope']])
            ->expectsOutputToContain('United States')
            ->expectsOutputToContain('INVALID_IP_ADDRESS')
            ->assertSuccessful();
    }

    public function testFailsOnWholeRequestError(): void
    {
        Ipregistry::fake([
            'origin' => new ApiException('invalid key', rawCode: 'INVALID_API_KEY'),
        ]);

        $this->pendingCommand('ipregistry:lookup')->assertFailed();
    }
}
