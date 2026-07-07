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

namespace Ipregistry\Laravel\Testing;

use Illuminate\Http\Request;
use Ipregistry\Exception\ApiException;
use Ipregistry\IpregistryClient;
use Ipregistry\Laravel\Ipregistry;
use Ipregistry\Model\IpInfo;
use Ipregistry\Model\IpInfoList;
use Ipregistry\Model\IpInfoResult;
use Ipregistry\Model\RequesterIpInfo;
use Ipregistry\Model\UserAgent;
use Ipregistry\Model\UserAgentList;
use Ipregistry\Model\UserAgentResult;
use PHPUnit\Framework\Assert;

/**
 * A drop-in replacement for the {@see Ipregistry} service used in tests.
 * It never sends HTTP requests: lookups answer from canned responses and
 * are recorded for assertions. Install it with `Ipregistry::fake([...])`
 * (see {@see \Ipregistry\Laravel\Facades\Ipregistry::fake()}).
 *
 * Responses are keyed by IP address; '*' is the fallback for any other IP,
 * and 'origin' answers origin lookups. Values are API-shaped arrays (the
 * 'ip' key is filled in for you), ready-made IpInfo instances, or
 * Throwables to simulate failures.
 *
 * Unlike the real service, requests with a private client IP (the norm in
 * feature tests) are looked up too, so `$request->ipregistry()` and the
 * blocking middleware behave in tests without trusted-proxy setup.
 */
class IpregistryFake extends Ipregistry
{
    /** @var array<string, IpInfo|\Throwable> */
    private array $responses = [];

    private RequesterIpInfo|\Throwable|null $originResponse = null;

    /** @var list<string> */
    private array $lookedUp = [];

    private int $originLookups = 0;

    /** @var list<string> */
    private array $parsedUserAgents = [];

    /**
     * @param array<string, IpInfo|array<string, mixed>|\Throwable> $responses
     */
    public function __construct(array $responses = [])
    {
        parent::__construct(new IpregistryClient('fake'));

        foreach ($responses as $ip => $response) {
            $this->stub((string) $ip, $response);
        }
    }

    /**
     * Registers or replaces the canned response for an IP address ('*' for
     * the fallback, 'origin' for origin lookups). Arrays use the API's
     * snake_case payload shape, e.g.
     * `['location' => ['country' => ['code' => 'US']]]`.
     *
     * @param IpInfo|array<string, mixed>|\Throwable $response
     */
    public function stub(string $ip, IpInfo|array|\Throwable $response): static
    {
        if ('origin' === $ip) {
            $this->originResponse = match (true) {
                \is_array($response) => RequesterIpInfo::fromArray($response),
                $response instanceof RequesterIpInfo, $response instanceof \Throwable => $response,
                default => throw new \InvalidArgumentException('the origin stub must be a RequesterIpInfo, an array payload, or a Throwable'),
            };

            return $this;
        }

        if (\is_array($response)) {
            $response = IpInfo::fromArray('*' === $ip ? $response : ['ip' => $ip] + $response);
        }

        $this->responses[$ip] = $response;

        return $this;
    }

    public function lookup(string $ip, ?string $fields = null, ?bool $hostname = null, array $params = []): IpInfo
    {
        $this->lookedUp[] = $ip;

        $response = $this->responses[$ip] ?? $this->responses['*'] ?? null;

        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response ?? IpInfo::fromArray(['ip' => $ip]);
    }

    public function lookupBatch(array $ips, ?string $fields = null, ?bool $hostname = null, array $params = []): IpInfoList
    {
        $results = [];
        foreach ($ips as $ip) {
            try {
                $results[] = IpInfoResult::success($this->lookup($ip, $fields, $hostname, $params));
            } catch (ApiException $e) {
                $results[] = IpInfoResult::failure($e);
            }
        }

        return new IpInfoList($results);
    }

    public function lookupOrigin(?string $fields = null, ?bool $hostname = null, array $params = []): RequesterIpInfo
    {
        ++$this->originLookups;

        if ($this->originResponse instanceof \Throwable) {
            throw $this->originResponse;
        }

        return $this->originResponse ?? new RequesterIpInfo();
    }

    public function parseUserAgents(string ...$userAgents): UserAgentList
    {
        $userAgents = array_values($userAgents);
        $this->parsedUserAgents = [...$this->parsedUserAgents, ...$userAgents];

        return new UserAgentList(array_map(
            static fn (string $userAgent): UserAgentResult => UserAgentResult::success(
                UserAgent::fromArray(['header' => $userAgent]),
            ),
            $userAgents,
        ));
    }

    /**
     * Unlike the real service, private and reserved client IPs are used
     * verbatim so feature tests (127.0.0.1) exercise lookups.
     */
    public function requestIp(Request $request): ?string
    {
        return $request->ip() ?? parent::requestIp($request);
    }

    public function assertLookedUp(string $ip): void
    {
        Assert::assertContains($ip, $this->lookedUp, \sprintf('Expected [%s] to have been looked up, but it was not. Looked up: [%s].', $ip, implode(', ', $this->lookedUp)));
    }

    public function assertNotLookedUp(string $ip): void
    {
        Assert::assertNotContains($ip, $this->lookedUp, \sprintf('Expected [%s] not to have been looked up, but it was.', $ip));
    }

    public function assertLookedUpTimes(string $ip, int $times = 1): void
    {
        $actual = \count(array_keys($this->lookedUp, $ip, true));
        Assert::assertSame($times, $actual, \sprintf('Expected [%s] to have been looked up %d time(s), but it was looked up %d time(s).', $ip, $times, $actual));
    }

    public function assertNothingLookedUp(): void
    {
        Assert::assertSame([], $this->lookedUp, \sprintf('Expected no lookups, but these IP addresses were looked up: [%s].', implode(', ', $this->lookedUp)));
    }

    public function assertOriginLookedUp(): void
    {
        Assert::assertGreaterThan(0, $this->originLookups, 'Expected an origin lookup, but none was performed.');
    }

    public function assertUserAgentsParsed(string ...$userAgents): void
    {
        foreach ($userAgents as $userAgent) {
            Assert::assertContains($userAgent, $this->parsedUserAgents, \sprintf('Expected the User-Agent [%s] to have been parsed, but it was not.', $userAgent));
        }
    }

    /**
     * Returns every IP address looked up so far, in order.
     *
     * @return list<string>
     */
    public function lookups(): array
    {
        return $this->lookedUp;
    }
}
