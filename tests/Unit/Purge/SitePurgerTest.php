<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Unit\Purge;

use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\Cloudflare\Purge\PurgerFactory;
use Contenir\Extension\Cloudflare\Purge\SitePurger;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\CloudflareResponses;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\ConnectionsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\SettingsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Settings\ContextFactory;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SitePurgerTest extends TestCase
{
    private FakeHttpClient $http;

    private MemoryLogger $log;

    #[Test]
    public function isWhatTheFactoryMakes(): void
    {
        $context = ContextFactory::make();

        static::assertEquals(
            new SitePurger($context, ConnectionsFactory::over($this->http)),
            (new PurgerFactory(ConnectionsFactory::over($this->http)))->create($context),
        );
    }

    #[Test]
    public function leavesAssetsAloneIfToldTo(): void
    {
        $this->purger(SettingsFactory::withToken(['purge_assets' => '0']))->purge(['https://example.com/a.jpg']);

        static::assertSame([], $this->http->requests);
    }

    #[Test]
    public function purgesAnAssetsUrls(): void
    {
        $this->purger(SettingsFactory::withToken())->purge(['https://example.com/a.jpg']);

        static::assertSame(
            ['{"files":["https://example.com/a.jpg"]}', 'asset'],
            [(string) $this->http->requests[0]->getBody(), $this->log->entries()[0]['context']['trigger'] ?? null],
        );
    }

    #[Test]
    public function purgesEverythingWhenContentChanges(): void
    {
        $this->purger(SettingsFactory::withToken())->purgeEverything();

        static::assertSame(
            ['{"purge_everything":true}', 'content'],
            [(string) $this->http->requests[0]->getBody(), $this->log->entries()[0]['context']['trigger'] ?? null],
        );
    }

    #[Test]
    public function purgesNothingUntilCloudflareIsSetUp(): void
    {
        $purger = $this->purger([]);
        $purger->purge(['https://example.com/a.jpg']);
        $purger->purgeEverything();

        static::assertSame([[], []], [$this->http->requests, $this->log->entries()]);
    }

    #[Test]
    public function purgesNothingWhenContentChangesIfToldTo(): void
    {
        $this->purger(SettingsFactory::withToken(['on_change' => 'nothing']))->purgeEverything();

        static::assertSame([], $this->http->requests);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->http = new FakeHttpClient(CloudflareResponses::ok());
        $this->log  = new MemoryLogger();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function purger(array $values): SitePurger
    {
        return new SitePurger(ContextFactory::make($values, $this->log), ConnectionsFactory::over($this->http));
    }
}
