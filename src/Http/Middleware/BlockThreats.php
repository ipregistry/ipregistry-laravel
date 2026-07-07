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

namespace Ipregistry\Laravel\Http\Middleware;

use Illuminate\Http\Request;
use Ipregistry\Laravel\Ipregistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks visitors flagged by Ipregistry security data with a 403 response.
 * The `is_threat`, `is_attacker`, and `is_abuser` signals are always
 * blocked; anonymization signals are opt-in as middleware parameters:
 *
 * ```php
 * // Threats, attackers, and abusers only:
 * Route::middleware('ipregistry.threats')->group(...);
 *
 * // Additionally block proxies, Tor, and VPNs:
 * Route::middleware('ipregistry.threats:proxy,tor,vpn')->group(...);
 * ```
 *
 * Accepted parameters: proxy, tor, vpn, relay, anonymous — each mapping to
 * the same-named `security.is_*` field of the Ipregistry response (tor also
 * covers `is_tor_exit`, and anonymous covers all anonymization signals).
 *
 * Fails open: when no data is available (private IP, lookup failure) the
 * visitor is let through. Combine with the 'fail_open' configuration value
 * set to false to refuse traffic without IP intelligence instead.
 */
final class BlockThreats
{
    private const SIGNALS = ['proxy', 'tor', 'vpn', 'relay', 'anonymous'];

    public function __construct(private readonly Ipregistry $ipregistry)
    {
    }

    /**
     * Builds the middleware string with additional opt-in signals, typed
     * and validated at route-definition time. The core threat signals are
     * always blocked. Equivalent to the 'ipregistry.threats:...' alias
     * syntax:
     *
     * ```php
     * Route::middleware(BlockThreats::including('tor', 'vpn'))
     * // same as: Route::middleware('ipregistry.threats:tor,vpn')
     * ```
     */
    public static function including(string ...$signals): string
    {
        foreach ($signals as $signal) {
            if (!\in_array(strtolower($signal), self::SIGNALS, true)) {
                throw new \InvalidArgumentException(\sprintf("'%s' is not a known signal; accepted signals are %s", $signal, implode(', ', self::SIGNALS)));
            }
        }

        return [] === $signals ? static::class : static::class.':'.implode(',', $signals);
    }

    /**
     * @param \Closure(Request): Response $next
     */
    public function handle(Request $request, \Closure $next, string ...$signals): Response
    {
        $signals = array_map(static fn (string $signal): string => strtolower(trim($signal)), $signals);
        if ([] !== $unknown = array_diff($signals, self::SIGNALS)) {
            throw new \InvalidArgumentException(\sprintf('ipregistry.threats received unknown signal(s) %s; accepted signals are %s. Usage: ipregistry.threats:proxy,tor,vpn', implode(', ', $unknown), implode(', ', self::SIGNALS)));
        }

        $info = $this->ipregistry->forRequest($request);

        if (null !== $info && $this->ipregistry->isThreat(
            $info,
            proxy: \in_array('proxy', $signals, true),
            tor: \in_array('tor', $signals, true),
            vpn: \in_array('vpn', $signals, true),
            relay: \in_array('relay', $signals, true),
            anonymous: \in_array('anonymous', $signals, true),
        )) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
