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

namespace Ipregistry\Laravel\Console;

use Illuminate\Console\Command;
use Ipregistry\Enum\IpType;
use Ipregistry\Exception\IpregistryException;
use Ipregistry\Laravel\Ipregistry;
use Ipregistry\Model\IpInfo;

/**
 * Looks up IP addresses from the terminal — handy to verify the API key
 * and inspect what the API returns for a given visitor:
 *
 * ```
 * php artisan ipregistry:lookup 8.8.8.8
 * php artisan ipregistry:lookup 8.8.8.8 1.1.1.1 --fields=location,security
 * php artisan ipregistry:lookup            # your server's own IP
 * ```
 */
final class LookupCommand extends Command
{
    protected $signature = 'ipregistry:lookup
        {ip?* : The IP addresses to look up; omit to look up your server\'s own IP}
        {--fields= : Restrict the response to the given fields (e.g. "location,security")}
        {--hostname : Resolve the reverse-DNS hostname}';

    protected $description = 'Look up IP addresses with the Ipregistry API';

    public function handle(Ipregistry $ipregistry): int
    {
        /** @var list<string> $ips */
        $ips = $this->argument('ip');
        $fields = \is_string($this->option('fields')) && '' !== $this->option('fields') ? $this->option('fields') : null;
        $hostname = $this->option('hostname') ? true : null;

        try {
            if ([] === $ips) {
                $this->display($ipregistry->lookupOrigin($fields, $hostname));
            } else {
                foreach ($ipregistry->lookupBatch($ips, $fields, $hostname)->results as $index => $result) {
                    if (null !== $result->error) {
                        $this->newLine();
                        $this->components->error(\sprintf('%s: %s', $ips[$index], $result->error->getMessage()));
                        continue;
                    }
                    \assert(null !== $result->info);
                    $this->display($result->info);
                }
            }
        } catch (IpregistryException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function display(IpInfo $info): void
    {
        $location = $info->location;
        $connection = $info->connection;
        $timeZone = $info->timeZone;

        $this->newLine();
        $this->row('IP', trim($info->ip.(IpType::Unknown !== $info->type ? ' ('.$info->type->value.')' : '')));
        $this->row('Hostname', $info->hostname);
        $this->row('Location', implode(', ', array_filter([
            $location->city,
            $location->region->name,
            '' !== $location->country->name
                ? $location->country->name.('' !== $location->country->code ? ' ('.$location->country->code.')' : '')
                : '',
        ], static fn (string $part): bool => '' !== $part)));
        if (null !== $location->latitude && null !== $location->longitude) {
            $this->row('Coordinates', $location->latitude.', '.$location->longitude);
        }
        if ('' !== $location->country->code) {
            $this->row('EU member', $location->inEu ? 'Yes' : 'No');
        }
        if (null !== $connection->asn) {
            $this->row('Connection', trim(\sprintf('AS%d %s', $connection->asn, $connection->organization)));
        }
        $this->row('Company', $info->company->name);
        $this->row('Time zone', trim($timeZone->id.('' !== $timeZone->currentTime ? ' ('.$timeZone->currentTime.')' : '')));
        $this->row('Currency', $info->currency->code);
        $this->row('Security', implode(', ', $this->securityFlags($info)));
    }

    private function row(string $label, string $value): void
    {
        if ('' !== $value) {
            $this->components->twoColumnDetail($label, $value);
        }
    }

    /**
     * @return list<string>
     */
    private function securityFlags(IpInfo $info): array
    {
        $security = $info->security;

        return array_keys(array_filter([
            'abuser' => $security->isAbuser,
            'attacker' => $security->isAttacker,
            'bogon' => $security->isBogon,
            'cloud provider' => $security->isCloudProvider,
            'proxy' => $security->isProxy,
            'relay' => $security->isRelay,
            'tor' => $security->isTor,
            'tor exit' => $security->isTorExit,
            'anonymous' => $security->isAnonymous,
            'threat' => $security->isThreat,
            'vpn' => $security->isVpn,
        ]));
    }
}
