<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Unit;

use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Cloudflare\ZoneId;
use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\Cloudflare\CloudflareSettings;
use Contenir\Extension\Cloudflare\Connections;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\CloudflareResponses;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\ConnectionsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\SettingsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Http\FakeHttpClient;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Http\NetworkError;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Settings\ArraySettings;
use Contenir\Extension\Cloudflare\Trigger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ConnectionsTest extends TestCase
{
    /**
     * @param array<string, mixed> $values
     */
    private static function settings(array $values): CloudflareSettings
    {
        return CloudflareSettings::from(new ArraySettings($values));
    }

    private static function token(): Credentials
    {
        return Credentials::token(SettingsFactory::TOKEN);
    }

    private static function zone(): ZoneId
    {
        return ZoneId::parse(SettingsFactory::ZONE_ID);
    }

    #[Test]
    public function givesCloudflareFiveSecondsToConnectAndTenToAnswer(): void
    {
        static::assertSame(['timeout' => 10, 'connect_timeout' => 5], Connections::GUZZLE);
    }

    #[Test]
    public function makesAPurgerLoggingItsTrigger(): void
    {
        $http = new FakeHttpClient(CloudflareResponses::ok());
        $log  = new MemoryLogger();

        ConnectionsFactory::over($http)
            ->purger(self::settings(SettingsFactory::withToken()), $log, Trigger::Asset)
            ?->purge(['https://example.com/a.jpg']);

        static::assertSame(
            ['Bearer ' . SettingsFactory::TOKEN, 'asset'],
            [$http->requests[0]->getHeaderLine('Authorization'), $log->entries()[0]['context']['trigger'] ?? null],
        );
    }

    #[Test]
    public function makesNoPurgerUntilTheZoneAndATokenAreSaved(): void
    {
        $connections = ConnectionsFactory::over(new FakeHttpClient());
        $log         = new MemoryLogger();

        static::assertSame([null, null], [
            $connections->purger(self::settings(['api_token' => SettingsFactory::TOKEN]), $log, Trigger::Content),
            $connections->purger(self::settings(['zone_id' => SettingsFactory::ZONE_ID]), $log, Trigger::Content),
        ]);
    }

    #[Test]
    public function readsTheZonesDomain(): void
    {
        $connections = ConnectionsFactory::over(new FakeHttpClient(CloudflareResponses::zone('example.org')));

        static::assertSame(['example.org', ''], $connections->domain(self::zone(), self::token()));
    }

    #[Test]
    public function saysWhenCloudflareCantBeReachedForTheDomain(): void
    {
        $connections = ConnectionsFactory::over(new FakeHttpClient(new NetworkError('Connection timed out')));

        static::assertSame(
            [null, 'Could not reach Cloudflare: Connection timed out'],
            $connections->domain(self::zone(), self::token()),
        );
    }

    #[Test]
    public function saysWhyTheDomainCantBeRead(): void
    {
        $connections = ConnectionsFactory::over(new FakeHttpClient(CloudflareResponses::refused(
            9109,
            'Unauthorized to access requested resource',
        )));

        static::assertSame(
            [
                null,
                'Cloudflare didn\'t say which domain zone '
                    . SettingsFactory::ZONE_ID
                    . ' is, so the URLs can\'t be'
                    . ' checked: 9109 Unauthorized to access requested resource. Give the token Zone Read as well as Cache'
                    . ' Purge.',
            ],
            $connections->domain(self::zone(), self::token()),
        );
    }

    #[Test]
    public function signsInWithTheCredentialsGiven(): void
    {
        $credentials = self::token();

        static::assertSame(
            $credentials,
            (new Connections())->client($credentials)
                ->credentials(),
        );
    }

    #[Test]
    public function treatsAZoneWithNoNameAsUnread(): void
    {
        $connections = ConnectionsFactory::over(new FakeHttpClient(CloudflareResponses::ok(['id' => 'x'])));

        static::assertNull($connections->domain(self::zone(), self::token())[0]);
    }
}
