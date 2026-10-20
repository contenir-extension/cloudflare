<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Integration;

use Contenir\Cdn\PurgerInterface;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Cloudflare\CloudflareExtension;
use Contenir\Extension\Cloudflare\Handler\PurgeUrls;
use Contenir\Extension\Cloudflare\Operation\PurgeEverything;
use Contenir\Extension\Cloudflare\Operation\TestConnection;
use Contenir\Extension\Cloudflare\Purge\PurgerFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\CloudflareResponses;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\ConnectionsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\SettingsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Http\FakeHttpClient;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_key_exists;
use function array_values;
use function count;

/**
 * The add-on on the API's in-memory host: the purger it provides to the CMS,
 * its operations as their buttons run them, and the Purge URLs screen, each
 * logging under the add-on. Cloudflare is a fake HTTP client.
 */
#[Group('integration')]
final class InMemoryHostTest extends TestCase
{
    private const array SECTION = ['settings.integration.cloudflare'];

    private Psr17Factory $http;

    /**
     * The newest purge log entry: its level, message, trigger, result,
     * request id and source.
     *
     * @return list<mixed>
     */
    private static function lastPurge(InMemoryHost $host): array
    {
        $purges = array_values(array_filter(
            $host->logs(),
            static fn(array $entry): bool => array_key_exists('trigger', $entry['context']),
        ));
        $entry = $purges[count($purges) - 1];

        return [
            $entry['level'],
            $entry['message'],
            $entry['context']['trigger'],
            $entry['context']['result'] ?? null,
            $entry['context']['request_id'] ?? null,
            $entry['context']['source'] ?? null,
        ];
    }

    #[Test]
    public function logsARefusedPurgeUnderTheAddOn(): void
    {
        $cloudflare = new FakeHttpClient(CloudflareResponses::refused(10_000, 'Authentication error'));
        $host       = $this->host($cloudflare, SettingsFactory::withToken());

        $host->provided(PurgerInterface::class)?->purge(['https://example.com/a.jpg']);

        static::assertSame(
            [
                'error',
                'Cloudflare refused to purge {what} for zone {zone}: {error}',
                'asset',
                'rejected',
                CloudflareResponses::RAY,
                'extension.cloudflare',
            ],
            self::lastPurge($host),
        );
    }

    #[Test]
    public function providesAPurgerThatPurgesEverythingWhenContentChanges(): void
    {
        $cloudflare = new FakeHttpClient(CloudflareResponses::ok());
        $host       = $this->host($cloudflare, SettingsFactory::withToken());

        $host->provided(PurgerInterface::class)?->purgeEverything();

        static::assertSame(
            [
                '{"purge_everything":true}',
                [
                    'info',
                    'Purged {what} from Cloudflare for zone {zone}',
                    'content',
                    'purged',
                    CloudflareResponses::RAY,
                    'extension.cloudflare',
                ],
            ],
            [(string) $cloudflare->requests[0]->getBody(), self::lastPurge($host)],
        );
    }

    #[Test]
    public function providesNothingWhileDisabled(): void
    {
        $host = new InMemoryHost($this->http, $this->http, new FrozenClock(new DateTimeImmutable('2026-10-21')));
        $host->boot(['cloudflare' => new CloudflareExtension()], []);

        static::assertNull($host->provided(PurgerInterface::class));
    }

    #[Test]
    public function purgesEverythingFromItsButtonForSomeoneWhoMayUpdateTheSection(): void
    {
        $cloudflare = new FakeHttpClient(CloudflareResponses::ok());
        $host       = $this->host($cloudflare, SettingsFactory::withToken());

        static::assertSame(
            [null, 'success', 'person'],
            [
                $host->operate(
                    'cloudflare',
                    'cloudflare',
                    'purge-everything',
                    [],
                    [
                        'settings.integration.cloudflare:view',
                    ],
                ),
                $host->operate('cloudflare', 'cloudflare', 'purge-everything', [], self::SECTION)
                    ?->messages()[0]['level'],
                self::lastPurge($host)[2] ?? null,
            ],
        );
    }

    #[Test]
    public function purgesUrlsFromItsScreen(): void
    {
        $cloudflare = new FakeHttpClient(CloudflareResponses::zone('example.com'), CloudflareResponses::ok());
        $host       = $this->host($cloudflare, SettingsFactory::withToken());

        $response = $host->handle(
            $this->http
                ->createServerRequest('POST', '/admin/extension/cloudflare/purge-urls')
                ->withParsedBody(['urls' => 'https://www.example.com/news/']),
            ['extension.cloudflare.purge-urls'],
        );

        static::assertSame(
            [200, '{"files":["https://www.example.com/news/"]}', 'person'],
            [
                $response->getStatusCode(),
                (string) $cloudflare->requests[1]->getBody(),
                self::lastPurge($host)[2] ?? null,
            ],
        );
    }

    #[Test]
    public function testsTheConnectionFromItsButton(): void
    {
        $host = $this->host(
            new FakeHttpClient(CloudflareResponses::token(), CloudflareResponses::zone('example.com', 'Free Website')),
            SettingsFactory::withToken(),
        );

        static::assertSame(
            'Connected to example.com (Free Website). Purge methods: everything, by URL, by cache tag, by hostname, by'
                . ' prefix.',
            $host->operate('cloudflare', 'cloudflare', 'test-connection', [], self::SECTION)?->messages()[0]['text'],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->http = new Psr17Factory();
    }

    /**
     * The add-on enabled, its settings saved as $values, sending to $cloudflare.
     *
     * @param array<string, mixed> $values
     */
    private function host(FakeHttpClient $cloudflare, array $values): InMemoryHost
    {
        $connections = ConnectionsFactory::over($cloudflare);
        $host        = new InMemoryHost($this->http, $this->http, new FrozenClock(new DateTimeImmutable('2026-10-21')));
        $host->boot(
            ['cloudflare' => new CloudflareExtension()],
            ['cloudflare'],
            [
                PurgerFactory::class   => new PurgerFactory($connections),
                TestConnection::class  => new TestConnection($connections),
                PurgeEverything::class => new PurgeEverything($connections),
                PurgeUrls::class       => new PurgeUrls($connections),
            ],
        );
        $host->context('cloudflare')->settings()->set($values);

        return $host;
    }
}
