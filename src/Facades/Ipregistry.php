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

namespace Ipregistry\Laravel\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Ipregistry\Laravel\Testing\IpregistryFake;
use Ipregistry\Model\IpInfo;

/**
 * @method static IpInfo                            lookup(string $ip, ?string $fields = null, ?bool $hostname = null, array<string, scalar> $params = [])
 * @method static \Ipregistry\Model\IpInfoList      lookupBatch(list<string> $ips, ?string $fields = null, ?bool $hostname = null, array<string, scalar> $params = [])
 * @method static \Ipregistry\Model\RequesterIpInfo lookupOrigin(?string $fields = null, ?bool $hostname = null, array<string, scalar> $params = [])
 * @method static \Ipregistry\Model\UserAgentList   parseUserAgents(string ...$userAgents)
 * @method static IpInfo|null                       forRequest(?Request $request = null, ?string $fields = null)
 * @method static string|null                       requestIp(Request $request)
 * @method static bool                              isEu(Request|IpInfo|null $subject = null, bool $assumeEu = false)
 * @method static bool                              isThreat(Request|IpInfo|null $subject = null, bool $proxy = false, bool $tor = false, bool $vpn = false, bool $relay = false, bool $anonymous = false)
 * @method static bool                              isBot(Request|string|null $subject = null)
 * @method static \Ipregistry\IpregistryClient      client()
 *
 * @see \Ipregistry\Laravel\Ipregistry
 */
final class Ipregistry extends Facade
{
    /**
     * Replaces the Ipregistry service with a fake for the current test. No
     * HTTP request is ever sent; lookups answer from the given canned
     * responses and are recorded for assertions.
     *
     * ```php
     * $fake = Ipregistry::fake([
     *     '8.8.8.8' => ['location' => ['country' => ['code' => 'US']]],
     *     '*' => ['security' => ['is_threat' => false]], // fallback
     * ]);
     *
     * // ... exercise the app ...
     *
     * $fake->assertLookedUp('8.8.8.8');
     * ```
     *
     * @param array<string, IpInfo|array<string, mixed>|\Throwable> $responses canned responses keyed by IP
     *                                                                         address, with '*' as a fallback
     *                                                                         and 'origin' for origin lookups;
     *                                                                         a Throwable value is thrown
     */
    public static function fake(array $responses = []): IpregistryFake
    {
        $fake = new IpregistryFake($responses);

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return \Ipregistry\Laravel\Ipregistry::class;
    }
}
