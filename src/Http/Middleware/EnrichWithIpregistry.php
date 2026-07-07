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

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Ipregistry\Laravel\Ipregistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enriches the request with Ipregistry data before your handlers run, so
 * `$request->ipregistry()` answers from memory everywhere downstream.
 *
 * Middleware parameters select the fields to fetch for the route, keeping
 * responses small and fast:
 *
 * ```php
 * Route::get('/pricing', ...)->middleware('ipregistry:ip,location,currency');
 * ```
 *
 * The middleware fails open by default: when the lookup fails the request
 * proceeds without data. Set the 'fail_open' configuration value to false
 * to respond with 503 instead. Requests without a public client IP are
 * always let through untouched.
 */
final class EnrichWithIpregistry
{
    public function __construct(
        private readonly Ipregistry $ipregistry,
        private readonly Repository $config,
    ) {
    }

    /**
     * @param \Closure(Request): Response $next
     */
    public function handle(Request $request, \Closure $next, string ...$fields): Response
    {
        $this->ipregistry->forRequest($request, [] === $fields ? null : implode(',', $fields));

        if (!$this->config->get('ipregistry.fail_open', true)
            && $request->attributes->has(Ipregistry::ERROR_ATTRIBUTE)) {
            abort(503, 'Service temporarily unavailable.');
        }

        return $next($request);
    }
}
