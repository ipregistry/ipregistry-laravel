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

use Ipregistry\Laravel\Facades\Ipregistry;
use Ipregistry\Laravel\Ipregistry as IpregistryService;
use Ipregistry\Model\IpInfo;

final class GuardsTest extends TestCase
{
    public function testIsEu(): void
    {
        Ipregistry::fake();

        $eu = IpInfo::fromArray(['location' => ['in_eu' => true]]);
        $us = IpInfo::fromArray(['location' => ['in_eu' => false]]);

        self::assertTrue(Ipregistry::isEu($eu));
        self::assertFalse(Ipregistry::isEu($us));
    }

    public function testIsEuAssumesConfiguredDefaultWhenDataMissing(): void
    {
        Ipregistry::fake(['*' => new \Ipregistry\Exception\ClientException('down')]);
        $request = \Illuminate\Http\Request::create('/', server: ['REMOTE_ADDR' => '8.8.8.8']);

        self::assertFalse(Ipregistry::isEu($request));
        self::assertTrue(Ipregistry::isEu(\Illuminate\Http\Request::create('/', server: ['REMOTE_ADDR' => '8.8.4.4']), assumeEu: true));
    }

    public function testIsThreatCountsCoreSignalsAlways(): void
    {
        Ipregistry::fake();

        self::assertTrue(Ipregistry::isThreat(IpInfo::fromArray(['security' => ['is_threat' => true]])));
        self::assertTrue(Ipregistry::isThreat(IpInfo::fromArray(['security' => ['is_attacker' => true]])));
        self::assertTrue(Ipregistry::isThreat(IpInfo::fromArray(['security' => ['is_abuser' => true]])));
        self::assertFalse(Ipregistry::isThreat(IpInfo::fromArray(['security' => []])));
    }

    public function testIsThreatAnonymizationSignalsAreOptIn(): void
    {
        Ipregistry::fake();

        $vpn = IpInfo::fromArray(['security' => ['is_vpn' => true]]);
        $torExit = IpInfo::fromArray(['security' => ['is_tor_exit' => true]]);

        self::assertFalse(Ipregistry::isThreat($vpn));
        self::assertTrue(Ipregistry::isThreat($vpn, vpn: true));
        self::assertFalse(Ipregistry::isThreat($torExit));
        self::assertTrue(Ipregistry::isThreat($torExit, tor: true));
    }

    public function testIsBot(): void
    {
        Ipregistry::fake();

        self::assertTrue(Ipregistry::isBot('Googlebot/2.1 (+http://www.google.com/bot.html)'));
        self::assertFalse(Ipregistry::isBot('Mozilla/5.0 (X11; Linux x86_64)'));

        $request = \Illuminate\Http\Request::create('/', server: ['HTTP_USER_AGENT' => 'bingbot/2.0']);
        self::assertTrue(Ipregistry::isBot($request));
    }

    public function testIsPublicIp(): void
    {
        self::assertTrue(IpregistryService::isPublicIp('8.8.8.8'));
        self::assertTrue(IpregistryService::isPublicIp('2001:4860:4860::8888'));
        self::assertFalse(IpregistryService::isPublicIp('127.0.0.1'));
        self::assertFalse(IpregistryService::isPublicIp('10.0.0.1'));
        self::assertFalse(IpregistryService::isPublicIp('192.168.1.10'));
        self::assertFalse(IpregistryService::isPublicIp('::1'));
        self::assertFalse(IpregistryService::isPublicIp('not-an-ip'));
    }

    public function testRealServiceSkipsPrivateIpsAndHonorsDevelopmentIp(): void
    {
        $service = $this->laravel()->make(IpregistryService::class);
        $request = \Illuminate\Http\Request::create('/', server: ['REMOTE_ADDR' => '127.0.0.1']);

        self::assertNull($service->requestIp($request));

        config()->set('ipregistry.development_ip', '66.165.2.7');
        $this->laravel()->forgetInstance(IpregistryService::class);
        $service = $this->laravel()->make(IpregistryService::class);

        self::assertSame('66.165.2.7', $service->requestIp($request));
        self::assertSame('8.8.8.8', $service->requestIp(
            \Illuminate\Http\Request::create('/', server: ['REMOTE_ADDR' => '8.8.8.8']),
        ));
    }
}
