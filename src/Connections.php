<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare;

use Contenir\Cdn\Cloudflare\Client;
use Contenir\Cdn\Cloudflare\CloudflarePurger;
use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Cloudflare\Zone;
use Contenir\Cdn\Cloudflare\ZoneId;
use Contenir\Cdn\Exception\TransportException;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

use function sprintf;

/**
 * Builds Cloudflare clients and purgers over one HTTP client: Guzzle, with
 * short timeouts so a slow Cloudflare never holds up a save for long, unless
 * a test gives it another.
 *
 * @api
 */
final readonly class Connections
{
    /**
     * Guzzle's options: at most 5 seconds to connect and 10 for an answer.
     */
    public const array GUZZLE = ['timeout' => 10, 'connect_timeout' => 5];

    public function __construct(
        private ClientInterface $http = new Guzzle(self::GUZZLE),
        private RequestFactoryInterface $requests = new HttpFactory(),
        private StreamFactoryInterface $streams = new HttpFactory(),
    ) {}

    /**
     * A client signing in with $credentials.
     */
    public function client(Credentials $credentials): Client
    {
        return new Client($this->http, $this->requests, $this->streams, $credentials);
    }

    /**
     * The domain of $zone, as Cloudflare names it, or null and why it
     * couldn't be read. Reading it needs the token to hold Zone Read.
     *
     * @return array{string, string}|array{null, string}
     */
    public function domain(ZoneId $zone, Credentials $credentials): array
    {
        try {
            $read = $this->client($credentials)->zone($zone);
        } catch (TransportException $exception) {
            return [null, $exception->getMessage()];
        }

        $name = Zone::fromResult($read->result)->name;
        if (! $read->success || '' === $name) {
            return [
                null,
                sprintf(
                    'Cloudflare didn\'t say which domain zone %s is, so the URLs can\'t be checked: %s. Give the token'
                        . ' Zone Read as well as Cache Purge.',
                    $zone,
                    $read->reason(),
                ),
            ];
        }

        return [$name, ''];
    }

    /**
     * A purger for $zone, logging each request to $log with $trigger, or
     * null when the settings don't reach Cloudflare yet.
     */
    public function purger(CloudflareSettings $settings, LoggerInterface $log, Trigger $trigger): ?CloudflarePurger
    {
        return null === $settings->zone || null === $settings->credentials
            ? null
            : $this->purgerFor($settings->zone, $settings->credentials, $log, $trigger);
    }

    /**
     * A purger for $zone, signing in with $credentials.
     */
    public function purgerFor(
        ZoneId $zone,
        Credentials $credentials,
        LoggerInterface $log,
        Trigger $trigger,
    ): CloudflarePurger {
        return new CloudflarePurger($this->client($credentials), $zone, $log, $trigger->value);
    }
}
