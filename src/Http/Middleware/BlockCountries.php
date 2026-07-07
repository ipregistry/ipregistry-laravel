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
 * Blocks (or exclusively allows) visitors by ISO 3166-1 alpha-2 country
 * code, responding with 451 Unavailable For Legal Reasons:
 *
 * ```php
 * // Block visitors from the listed countries:
 * Route::middleware('ipregistry.countries:block,KP,IR')->group(...);
 *
 * // Or only allow visitors from the listed countries:
 * Route::middleware('ipregistry.countries:allow,FR,BE')->group(...);
 * ```
 *
 * Fails open: when the country could not be determined (private IP, lookup
 * failure) the visitor is let through, so an Ipregistry outage never locks
 * users out. Combine with the 'fail_open' configuration value set to false
 * to refuse traffic without IP intelligence instead.
 */
final class BlockCountries
{
    public function __construct(private readonly Ipregistry $ipregistry)
    {
    }

    /**
     * @param \Closure(Request): Response $next
     */
    public function handle(Request $request, \Closure $next, string $mode = 'block', string ...$countries): Response
    {
        $mode = strtolower(trim($mode));
        if (!\in_array($mode, ['block', 'allow'], true)) {
            throw new \InvalidArgumentException(\sprintf("ipregistry.countries expects 'block' or 'allow' as its first parameter, got '%s'. Usage: ipregistry.countries:block,KP,IR", $mode));
        }
        if ([] === $countries) {
            throw new \InvalidArgumentException('ipregistry.countries expects at least one country code. Usage: ipregistry.countries:block,KP,IR');
        }

        $countries = array_map(static fn (string $country): string => strtoupper(trim($country)), $countries);
        $code = $this->ipregistry->forRequest($request)?->location->country->code;

        if (null === $code || '' === $code) {
            return $next($request);
        }

        $listed = \in_array(strtoupper($code), $countries, true);

        if ('block' === $mode ? $listed : !$listed) {
            abort(451, 'Unavailable For Legal Reasons');
        }

        return $next($request);
    }
}
