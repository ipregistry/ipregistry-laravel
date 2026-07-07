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
use Ipregistry\Laravel\Testing\IpregistryFake;

final class FakeTest extends TestCase
{
    public function testFakeSwapsTheServiceAndAnswersFromStubs(): void
    {
        $fake = Ipregistry::fake([
            '8.8.8.8' => ['location' => ['country' => ['code' => 'US', 'name' => 'United States']]],
        ]);

        self::assertSame($fake, $this->laravel()->make(\Ipregistry\Laravel\Ipregistry::class));

        $info = Ipregistry::lookup('8.8.8.8');

        self::assertSame('8.8.8.8', $info->ip);
        self::assertSame('US', $info->location->country->code);
        $fake->assertLookedUp('8.8.8.8');
        $fake->assertLookedUpTimes('8.8.8.8', 1);
    }

    public function testWildcardStubAnswersUnknownIps(): void
    {
        $fake = Ipregistry::fake([
            '*' => ['location' => ['country' => ['code' => 'FR']]],
        ]);

        self::assertSame('FR', Ipregistry::lookup('1.2.3.4')->location->country->code);
        $fake->assertLookedUp('1.2.3.4');
        $fake->assertNotLookedUp('8.8.8.8');
    }

    public function testUnstubbedIpGetsEmptyInfoInsteadOfFailing(): void
    {
        Ipregistry::fake();

        $info = Ipregistry::lookup('9.9.9.9');

        self::assertSame('9.9.9.9', $info->ip);
        self::assertSame('', $info->location->country->code);
    }

    public function testThrowableStubSimulatesFailures(): void
    {
        Ipregistry::fake([
            '8.8.8.8' => new ApiException('out of credits', rawCode: 'INSUFFICIENT_CREDITS'),
        ]);

        $this->expectException(ApiException::class);

        Ipregistry::lookup('8.8.8.8');
    }

    public function testBatchMixesSuccessesAndPerEntryFailures(): void
    {
        Ipregistry::fake([
            '8.8.8.8' => ['location' => ['country' => ['code' => 'US']]],
            '1.1.1.1' => new ApiException('invalid', rawCode: 'INVALID_IP_ADDRESS'),
        ]);

        $list = Ipregistry::lookupBatch(['8.8.8.8', '1.1.1.1']);

        self::assertCount(2, $list);
        self::assertSame('US', $list->at(0)->location->country->code);
        self::assertNotNull($list->results[1]->error);
    }

    public function testOriginStub(): void
    {
        $fake = Ipregistry::fake([
            'origin' => ['ip' => '203.0.113.10', 'location' => ['country' => ['code' => 'DE']]],
        ]);

        $origin = Ipregistry::lookupOrigin();

        self::assertSame('203.0.113.10', $origin->ip);
        self::assertSame('DE', $origin->location->country->code);
        $fake->assertOriginLookedUp();
    }

    public function testParseUserAgentsIsRecorded(): void
    {
        $fake = Ipregistry::fake();

        $list = Ipregistry::parseUserAgents('Mozilla/5.0');

        self::assertSame('Mozilla/5.0', $list->at(0)->header);
        $fake->assertUserAgentsParsed('Mozilla/5.0');
    }

    public function testAssertNothingLookedUp(): void
    {
        $fake = Ipregistry::fake();

        $fake->assertNothingLookedUp();
    }

    public function testStubApiPayloadWithSecurityFlags(): void
    {
        Ipregistry::fake([
            '5.5.5.5' => ['security' => ['is_threat' => true, 'is_vpn' => true]],
        ]);

        $info = Ipregistry::lookup('5.5.5.5');

        self::assertTrue($info->security->isThreat);
        self::assertTrue($info->security->isVpn);
        self::assertTrue(Ipregistry::isThreat($info));
    }

    public function testFakeIsAnInstanceOfTheServiceItReplaces(): void
    {
        self::assertInstanceOf(\Ipregistry\Laravel\Ipregistry::class, new IpregistryFake());
    }
}
