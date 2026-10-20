<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Unit;

use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Cloudflare\ZoneId;
use Contenir\Extension\Cloudflare\CloudflareSettings;
use Contenir\Extension\Cloudflare\OnChange;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\SettingsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Settings\ArraySettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CloudflareSettingsTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function flags(): array
    {
        return [
            'true'     => [true, true],
            'one'      => [1, true],
            'one text' => ['1', true],
            'false'    => [false, false],
            'zero'     => ['0', false],
            'blank'    => ['', false],
            'null'     => [null, false],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, ?string}>
     */
    public static function missing(): array
    {
        return [
            'nothing'            => [[], 'Enter the zone ID and an API token, and save, first.'],
            'no zone'            => [['api_token' => SettingsFactory::TOKEN], 'Enter the zone ID, and save, first.'],
            'a malformed zone'   => [
                ['zone_id' => 'abc', 'api_token' => SettingsFactory::TOKEN],
                'Enter the zone ID, and save, first.',
            ],
            'no token'           => [['zone_id' => SettingsFactory::ZONE_ID], 'Enter an API token, and save, first.'],
            'a token'            => [SettingsFactory::withToken(), null],
            'the Global API Key' => [SettingsFactory::withGlobalKey(), null],
        ];
    }

    #[Test]
    public function fallsBackToTheGlobalKeyWithoutAToken(): void
    {
        static::assertEquals(
            Credentials::globalKey('ops@example.com', SettingsFactory::KEY),
            CloudflareSettings::from(new ArraySettings(SettingsFactory::withGlobalKey()))->credentials,
        );
    }

    #[Test]
    public function purgesEverythingAndAssetsByDefault(): void
    {
        $settings = CloudflareSettings::from(new ArraySettings());

        static::assertSame([null, null, OnChange::Everything, true], [
            $settings->zone,
            $settings->credentials,
            $settings->onChange,
            $settings->purgeAssets,
        ]);
    }

    #[Test]
    public function readsASiteSetUpWithAToken(): void
    {
        static::assertEquals(
            new CloudflareSettings(
                ZoneId::parse(SettingsFactory::ZONE_ID),
                Credentials::token(SettingsFactory::TOKEN),
                OnChange::Nothing,
                false,
            ),
            CloudflareSettings::from(new ArraySettings(SettingsFactory::withToken([
                'email'        => 'ops@example.com',
                'api_key'      => SettingsFactory::KEY,
                'on_change'    => 'nothing',
                'purge_assets' => '0',
            ]))),
        );
    }

    #[Test]
    #[DataProvider('flags')]
    public function readsPurgeFilesAsTheCheckboxSavedIt(mixed $value, bool $expected): void
    {
        static::assertSame(
            $expected,
            CloudflareSettings::from(new ArraySettings(['purge_assets' => $value]))->purgeAssets,
        );
    }

    #[Test]
    public function readsValuesThatArentTextAsBlank(): void
    {
        static::assertNull(CloudflareSettings::from(new ArraySettings(['zone_id' => 12, 'api_token' => ['x']]))->zone);
    }

    /**
     * @param array<string, mixed> $values
     */
    #[Test]
    #[DataProvider('missing')]
    public function saysWhatIsMissing(array $values, ?string $expected): void
    {
        static::assertSame($expected, CloudflareSettings::from(new ArraySettings($values))->missing());
    }
}
