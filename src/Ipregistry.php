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

namespace Ipregistry\Laravel;

use Illuminate\Http\Request;
use Ipregistry\Exception\ApiException;
use Ipregistry\Exception\ClientException;
use Ipregistry\IpregistryClient;
use Ipregistry\Model\IpInfo;
use Ipregistry\Model\IpInfoList;
use Ipregistry\Model\RequesterIpInfo;
use Ipregistry\Model\UserAgentList;
use Ipregistry\UserAgents;

/**
 * The Laravel-facing Ipregistry service. It wraps the official
 * {@see IpregistryClient} with the application's configuration (API key,
 * default field selection, Laravel cache store) and adds request-aware
 * helpers: {@see forRequest()} resolves the data for the current HTTP
 * request's client IP once, memoizes it on the request, and fails open.
 *
 * Resolve it from the container, inject it, or use the
 * {@see Facades\Ipregistry} facade.
 */
class Ipregistry
{
    /** The released version of the integration. */
    public const VERSION = '1.0.0';

    /** The request attribute holding the memoized lookup result. */
    public const REQUEST_ATTRIBUTE = 'ipregistry';

    /** The request attribute holding the error of a failed lookup. */
    public const ERROR_ATTRIBUTE = 'ipregistry.error';

    public function __construct(
        protected IpregistryClient $client,
        /** The default field selection applied when a call passes none. */
        protected ?string $fields = null,
        /** Whether lookups resolve reverse-DNS hostnames by default. */
        protected ?bool $hostname = null,
        /** A fixed public IP used when the client IP is private or missing. */
        protected ?string $developmentIp = null,
    ) {
    }

    /**
     * Returns the underlying Ipregistry client for advanced use.
     */
    public function client(): IpregistryClient
    {
        return $this->client;
    }

    /**
     * Returns the data associated with the given IP address. Successful
     * lookups are cached in the configured Laravel cache store.
     *
     * @param string|null           $fields   restricts the response to the given fields; defaults
     *                                        to the 'fields' configuration value
     * @param bool|null             $hostname enables reverse-DNS hostname resolution; defaults to
     *                                        the 'hostname' configuration value
     * @param array<string, scalar> $params   arbitrary extra query parameters
     *
     * @throws ApiException    when the API reports a failure
     * @throws ClientException on network errors or an undecodable response
     */
    public function lookup(string $ip, ?string $fields = null, ?bool $hostname = null, array $params = []): IpInfo
    {
        return $this->client->lookup($ip, $fields ?? $this->fields, $hostname ?? $this->hostname, $params);
    }

    /**
     * Resolves several IP addresses at once. The returned list preserves
     * input order, and each entry may independently succeed or fail. Arrays
     * larger than the API's per-request limit are transparently split.
     *
     * @param list<string>          $ips      the IPv4 or IPv6 addresses to resolve
     * @param string|null           $fields   restricts the response to the given fields
     * @param bool|null             $hostname enables reverse-DNS hostname resolution
     * @param array<string, scalar> $params   arbitrary extra query parameters
     *
     * @throws ApiException    when the API reports a whole-request failure
     * @throws ClientException on invalid input, network errors, or an undecodable response
     */
    public function lookupBatch(array $ips, ?string $fields = null, ?bool $hostname = null, array $params = []): IpInfoList
    {
        return $this->client->lookupBatch($ips, $fields ?? $this->fields, $hostname ?? $this->hostname, $params);
    }

    /**
     * Returns the data associated with the IP address the request to the
     * Ipregistry API originates from (i.e. your server's public IP),
     * enriched with parsed User-Agent data. For the IP of the visitor
     * hitting your Laravel app, use {@see forRequest()} instead.
     *
     * @param string|null           $fields   restricts the response to the given fields
     * @param bool|null             $hostname enables reverse-DNS hostname resolution
     * @param array<string, scalar> $params   arbitrary extra query parameters
     *
     * @throws ApiException    when the API reports a failure
     * @throws ClientException on network errors or an undecodable response
     */
    public function lookupOrigin(?string $fields = null, ?bool $hostname = null, array $params = []): RequesterIpInfo
    {
        return $this->client->lookupOrigin($fields ?? $this->fields, $hostname ?? $this->hostname, $params);
    }

    /**
     * Parses one or more raw User-Agent strings into structured data.
     * Results preserve the order of the input.
     *
     * @throws ApiException    when the API reports a whole-request failure
     * @throws ClientException on network errors or an undecodable response
     */
    public function parseUserAgents(string ...$userAgents): UserAgentList
    {
        return $this->client->parseUserAgents(...$userAgents);
    }

    /**
     * Returns the Ipregistry data for the request's client IP, or null when
     * the IP is private/unknown or the lookup failed. This method never
     * throws: failures are reported to the exception handler and stored on
     * the request under {@see ERROR_ATTRIBUTE}.
     *
     * The result is memoized as a request attribute, so the API is queried
     * at most once per request regardless of how many middleware, guards,
     * or views call this (also available as `$request->ipregistry()`).
     *
     * The client IP comes from {@see Request::ip()}, which honors your
     * trusted proxy configuration. When it is private or missing — the norm
     * on localhost — the configured 'development_ip' is used instead when
     * set; otherwise the lookup is skipped.
     *
     * @param Request|null $request the request to enrich; defaults to the current request
     * @param string|null  $fields  restricts the response to the given fields; defaults to
     *                              the 'fields' configuration value
     */
    public function forRequest(?Request $request = null, ?string $fields = null): ?IpInfo
    {
        $request ??= $this->currentRequest();
        if (null === $request) {
            return null;
        }

        if ($request->attributes->has(self::REQUEST_ATTRIBUTE)) {
            $memoized = $request->attributes->get(self::REQUEST_ATTRIBUTE);

            return $memoized instanceof IpInfo ? $memoized : null;
        }

        $ip = $this->requestIp($request);
        if (null === $ip) {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, false);

            return null;
        }

        try {
            $info = $this->lookup($ip, $fields);
        } catch (\Throwable $e) {
            report($e);
            $request->attributes->set(self::REQUEST_ATTRIBUTE, false);
            $request->attributes->set(self::ERROR_ATTRIBUTE, $e);

            return null;
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $info);

        return $info;
    }

    /**
     * Returns the public IP address a lookup for the given request should
     * run against: the client IP as resolved by Laravel (honoring trusted
     * proxies), or the configured 'development_ip' when the client IP is
     * private or missing. Returns null when no public IP is available, in
     * which case no lookup is performed — private and reserved addresses
     * are never sent to the API.
     */
    public function requestIp(Request $request): ?string
    {
        $ip = $request->ip();
        if (null !== $ip && self::isPublicIp($ip)) {
            return $ip;
        }

        if (null !== $this->developmentIp && '' !== $this->developmentIp) {
            return $this->developmentIp;
        }

        return null;
    }

    /**
     * Reports whether the given string is a valid, public (non-private,
     * non-reserved) IPv4 or IPv6 address.
     */
    public static function isPublicIp(string $ip): bool
    {
        return false !== filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Reports whether the visitor is located in the European Union, based
     * on the API's `location.in_eu` field. Useful for GDPR consent UIs.
     *
     * @param Request|IpInfo|null $subject  an IpInfo, a request to resolve (fail-open), or
     *                                      null for the current request
     * @param bool                $assumeEu the value returned when no data is available;
     *                                      pass true to default to showing consent UIs
     */
    public function isEu(Request|IpInfo|null $subject = null, bool $assumeEu = false): bool
    {
        $info = $this->resolveInfo($subject);

        return null === $info ? $assumeEu : $info->location->inEu;
    }

    /**
     * Reports whether the IP is flagged by Ipregistry security data. The
     * `is_threat`, `is_attacker`, and `is_abuser` signals always count;
     * anonymization signals are opt-in through the flags. Returns false
     * when no data is available (fail-open).
     *
     * @param Request|IpInfo|null $subject an IpInfo, a request to resolve (fail-open), or
     *                                     null for the current request
     */
    public function isThreat(
        Request|IpInfo|null $subject = null,
        bool $proxy = false,
        bool $tor = false,
        bool $vpn = false,
        bool $relay = false,
        bool $anonymous = false,
    ): bool {
        $info = $this->resolveInfo($subject);
        if (null === $info) {
            return false;
        }

        $security = $info->security;

        return $security->isThreat
            || $security->isAttacker
            || $security->isAbuser
            || ($proxy && $security->isProxy)
            || ($tor && ($security->isTor || $security->isTorExit))
            || ($vpn && $security->isVpn)
            || ($relay && $security->isRelay)
            || ($anonymous && $security->isAnonymous);
    }

    /**
     * Reports whether the User-Agent looks like a crawler or bot, using the
     * SDK's lightweight heuristic. Accepts a raw User-Agent string, a
     * request, or null for the current request.
     */
    public function isBot(Request|string|null $subject = null): bool
    {
        if (null === $subject) {
            $subject = $this->currentRequest();
        }

        $userAgent = $subject instanceof Request ? $subject->userAgent() : $subject;

        return null !== $userAgent && '' !== $userAgent && UserAgents::isBot($userAgent);
    }

    protected function resolveInfo(Request|IpInfo|null $subject): ?IpInfo
    {
        if ($subject instanceof IpInfo) {
            return $subject;
        }

        return $this->forRequest($subject);
    }

    protected function currentRequest(): ?Request
    {
        $app = \Illuminate\Container\Container::getInstance();
        if (!$app->bound('request')) {
            return null;
        }

        $request = $app->make('request');

        return $request instanceof Request ? $request : null;
    }
}
