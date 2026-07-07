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

use Illuminate\Foundation\Application;
use Ipregistry\Laravel\IpregistryServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * @param Application $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [IpregistryServiceProvider::class];
    }

    /**
     * @param Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('ipregistry.api_key', 'test-key');
    }

    /**
     * Returns the booted application with a non-nullable type, for use in
     * assertions.
     */
    protected function laravel(): Application
    {
        \assert(null !== $this->app);

        return $this->app;
    }

    /**
     * Calls an artisan command, typed for interactive expectations.
     *
     * @param array<string, mixed> $parameters
     */
    protected function pendingCommand(string $command, array $parameters = []): \Illuminate\Testing\PendingCommand
    {
        $result = $this->artisan($command, $parameters);
        \assert($result instanceof \Illuminate\Testing\PendingCommand);

        return $result;
    }
}
