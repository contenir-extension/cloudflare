<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Unit\Operation;

use Contenir\Extension\Cloudflare\Operation\TestConnection;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\CloudflareResponses;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\ConnectionsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\SettingsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Operation\FakeOperationContext;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Settings\ContextFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TestConnectionTest extends TestCase
{
    #[Test]
    public function asksForWhatIsMissingWithoutReachingCloudflare(): void
    {
        $http = new FakeHttpClient();

        static::assertSame(
            [[['level' => 'warning', 'text' => 'Enter an API token, and save, first.']], []],
            [$this->connect($http, ['zone_id' => SettingsFactory::ZONE_ID]), $http->requests],
        );
    }

    #[Test]
    public function needsTheAddOnsContext(): void
    {
        static::assertSame(
            [['level' => 'danger', 'text' => 'The CMS ran this without the Cloudflare add-on\'s settings.']],
            (new TestConnection(ConnectionsFactory::over(new FakeHttpClient())))->handle(new FakeOperationContext(null))
                ->messages(),
        );
    }

    #[Test]
    public function saysWhatCloudflareRefused(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::refused(1000, 'Invalid API Token', 401));

        static::assertSame(
            [['level' => 'danger', 'text' => 'Cloudflare refused the API token: 1000 Invalid API Token']],
            $this->connect($http, SettingsFactory::withToken()),
        );
    }

    #[Test]
    public function saysWhichZoneAndPlanItReached(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::token(), CloudflareResponses::zone('example.com', 'Pro Plan'));

        static::assertSame(
            [[
                'level' => 'success',
                'text'  => 'Connected to example.com (Pro Plan). Purge methods: everything, by URL, by cache tag, by'
                    . ' hostname, by prefix.',
            ]],
            $this->connect($http, SettingsFactory::withToken()),
        );
    }

    #[Test]
    public function warnsAboutTheGlobalApiKey(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok(['id' => 'user']), CloudflareResponses::zone());

        static::assertSame(
            [
                'success',
                [
                    'level' => 'warning',
                    'text'  => 'This signs in with the Global API Key, which can do anything your Cloudflare account'
                        . ' can. Make an API token with Cache Purge and Zone Read for this zone, and enter it instead.',
                ],
            ],
            [
                $this->connect($http, SettingsFactory::withGlobalKey())[0]['level'] ?? null,
                $this->connect(
                    new FakeHttpClient(CloudflareResponses::ok(['id' => 'user']), CloudflareResponses::zone()),
                    SettingsFactory::withGlobalKey(),
                )[1] ?? null,
            ],
        );
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<array{level: string, text: string}>
     */
    private function connect(FakeHttpClient $http, array $values): array
    {
        return (new TestConnection(ConnectionsFactory::over(
            $http,
        )))->handle(new FakeOperationContext(ContextFactory::make($values)))
            ->messages();
    }
}
