<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Unit\Operation;

use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\Cloudflare\Operation\PurgeEverything;
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
final class PurgeEverythingTest extends TestCase
{
    #[Test]
    public function asksForWhatIsMissingWithoutReachingCloudflare(): void
    {
        $http = new FakeHttpClient();

        static::assertSame(
            [[['level' => 'warning', 'text' => 'Enter the zone ID, and save, first.']], []],
            [$this->purge($http, new MemoryLogger(), ['api_token' => SettingsFactory::TOKEN]), $http->requests],
        );
    }

    #[Test]
    public function purgesEverythingAsAPerson(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok());
        $log  = new MemoryLogger();

        $messages = $this->purge($http, $log, SettingsFactory::withToken(['on_change' => 'nothing']));

        static::assertSame(
            [
                [[
                    'level' => 'success',
                    'text'  => 'Cloudflare purged everything it held for the site. Pages are a little slower until it'
                        . ' caches them again.',
                ]],
                '{"purge_everything":true}',
                'person',
            ],
            [$messages, (string) $http->requests[0]->getBody(), $log->entries()[0]['context']['trigger'] ?? null],
        );
    }

    #[Test]
    public function saysWhatCloudflareRefused(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::refused(10_000, 'Authentication error'));

        static::assertSame(
            [['level' => 'danger', 'text' => 'Cloudflare refused: 10000 Authentication error']],
            $this->purge($http, new MemoryLogger(), SettingsFactory::withToken()),
        );
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<array{level: string, text: string}>
     */
    private function purge(FakeHttpClient $http, MemoryLogger $log, array $values): array
    {
        return (new PurgeEverything(ConnectionsFactory::over(
            $http,
        )))->handle(new FakeOperationContext(ContextFactory::make($values, $log)))
            ->messages();
    }
}
