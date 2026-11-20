<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Unit\Check;

use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Cloudflare\Check\ConnectionCheck;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\CloudflareResponses;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\ConnectionsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\SettingsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Settings\ContextFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConnectionCheckTest extends TestCase
{
    /**
     * @param array<string, mixed> $values
     */
    private static function check(FakeHttpClient $http, array $values): CheckResult
    {
        return (new ConnectionCheck(ConnectionsFactory::over($http)))->check(ContextFactory::make($values));
    }

    private static function fix(): GuidedFix
    {
        return new GuidedFix(
            'Open the Cloudflare settings',
            'Enter the zone ID and an API token in Site Settings > Integration > Cloudflare, save, and press Test'
                . ' connection.',
        );
    }

    #[Test]
    public function endsWhatCloudflareRefusedWithOneFullStop(): void
    {
        static::assertSame(
            'Cloudflare refused the API token: 9109 Invalid access token. Nothing is purged from Cloudflare until it\'s'
                . ' put right.',
            self::check(
                new FakeHttpClient(CloudflareResponses::refused(9109, 'Invalid access token.', 401)),
                SettingsFactory::withToken(),
            )->message,
        );
    }

    #[Test]
    public function failsWithWhatCloudflareRefused(): void
    {
        $result = self::check(
            new FakeHttpClient(CloudflareResponses::refused(1000, 'Invalid API Token', 401)),
            SettingsFactory::withToken(),
        );

        static::assertEquals(
            [
                Status::Failure,
                'Cloudflare refused the API token: 1000 Invalid API Token. Nothing is purged from Cloudflare until'
                    . ' it\'s put right.',
                self::fix(),
            ],
            [$result->status, $result->message, $result->fix],
        );
    }

    #[Test]
    public function isLabelledForTheUpdateCheck(): void
    {
        static::assertSame('Cloudflare connection', (new ConnectionCheck())->label());
    }

    #[Test]
    public function passesWithTheZoneItReached(): void
    {
        $result = self::check(
            new FakeHttpClient(CloudflareResponses::token(), CloudflareResponses::zone('example.com')),
            SettingsFactory::withToken(),
        );

        static::assertEquals(
            [Status::Success, 'Connected to example.com.', null],
            [$result->status, $result->message, $result->fix],
        );
    }

    #[Test]
    public function warnsAboutTheGlobalApiKeyOnceConnected(): void
    {
        $result = self::check(
            new FakeHttpClient(CloudflareResponses::ok(['id' => 'user']), CloudflareResponses::zone('example.com')),
            SettingsFactory::withGlobalKey(),
        );

        static::assertEquals(
            [
                Status::Warning,
                'Connected to example.com with the Global API Key, which can do anything your Cloudflare account'
                    . ' can. Make an API token with Cache Purge and Zone Read for this zone, and enter it instead.',
                self::fix(),
            ],
            [$result->status, $result->message, $result->fix],
        );
    }

    #[Test]
    public function warnsWhatIsMissingWithoutReachingCloudflare(): void
    {
        $http   = new FakeHttpClient();
        $result = self::check($http, ['zone_id' => SettingsFactory::ZONE_ID]);

        static::assertEquals(
            [
                Status::Warning,
                'Enter an API token, and save, first. Until then, nothing is purged from Cloudflare.',
                self::fix(),
                [],
            ],
            [$result->status, $result->message, $result->fix, $http->requests],
        );
    }
}
