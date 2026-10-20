<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Unit;

use Closure;
use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\Group;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Operation\Contribution;
use Contenir\Extension\Api\Operation\Operation;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Api\Settings\SettingsSection;
use Contenir\Extension\Cloudflare\CloudflareSection;
use Contenir\Extension\Cloudflare\Operation\PurgeEverything;
use Contenir\Extension\Cloudflare\Operation\TestConnection;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[TestGroup('unit')]
final class CloudflareSectionTest extends TestCase
{
    /**
     * @template T
     *
     * @param Closure(Field): T $read
     *
     * @return array<string, T>
     */
    private static function column(Closure $read): array
    {
        $fields = [];
        foreach (self::section()->form->fields as $field) {
            $fields[$field->name] = $read($field);
        }

        return $fields;
    }

    private static function section(): SettingsSection
    {
        return CloudflareSection::define(new Handle('cloudflare'));
    }

    #[Test]
    public function describesEachField(): void
    {
        static::assertSame(
            [
                'zone_id'      => 'From the zone\'s Overview page in Cloudflare, under API: 32 letters and digits.',
                'api_token'    => 'Make one in Cloudflare under My Profile > API Tokens, for this zone only, with Zone >'
                    . ' Cache Purge > Purge and Zone > Zone > Read. Kept in this environment\'s local settings.',
                'on_change'    => 'What happens to Cloudflare\'s copy of the site when a page or setting changes.'
                    . ' Saves that change nothing visitors see, such as a profile or permissions, never purge.',
                'purge_assets' => 'Purge an image or file from Cloudflare when it is uploaded again, renamed or'
                    . ' deleted, so visitors never see the old one.',
                'email'        => 'Only for the Global API Key, while there is no API token.',
                'api_key'      => 'Deprecated: the Global API Key can do anything your Cloudflare account can. Used'
                    . ' only while there is no API token.',
            ],
            self::column(static fn(Field $field): string => $field->description),
        );
    }

    #[Test]
    public function groupsTheFields(): void
    {
        static::assertEquals(
            [
                new Group('connection', 'Connection', ['zone_id', 'api_token']),
                new Group('purging', 'Purging', ['on_change', 'purge_assets']),
                new Group('global_key', 'Global API Key (deprecated)', [['email', 'api_key']]),
            ],
            self::section()->form->groups,
        );
    }

    #[Test]
    public function isCloudflareUnderIntegration(): void
    {
        $section = self::section();

        static::assertSame(['cloudflare', 'Cloudflare', SettingsCategory::Integration], [
            $section->name,
            $section->title,
            $section->category,
        ]);
    }

    #[Test]
    public function keepsThePluginsKeysAndAddsItsOwn(): void
    {
        static::assertSame(
            [
                'zone_id'                  => 'integration.cloudflare.zone_id',
                'api_token'                => null,
                'on_change'                => null,
                'purge_assets'             => null,
                'email'                    => 'integration.cloudflare.email',
                CloudflareSection::API_KEY => 'integration.cloudflare.api_key',
            ],
            self::column(static fn(Field $field): ?string => $field->setting),
        );
    }

    #[Test]
    public function keepsTheSecretsMaskedAndLocal(): void
    {
        static::assertSame(
            [
                'zone_id'      => [false, false],
                'api_token'    => [true, true],
                'on_change'    => [false, false],
                'purge_assets' => [false, false],
                'email'        => [false, false],
                'api_key'      => [true, true],
            ],
            self::column(static fn(Field $field): array => [$field->isMasked(), $field->local]),
        );
    }

    #[Test]
    public function labelsEachField(): void
    {
        static::assertSame(
            [
                'zone_id'      => ['Zone ID', 'cloudflare:zone-id', null],
                'api_token'    => ['API token', 'password', null],
                'on_change'    => ['When content changes', 'radio', 'everything'],
                'purge_assets' => ['Purge files when assets change', 'checkbox', true],
                'email'        => ['Account email', 'email', null],
                'api_key'      => ['Global API Key', 'password', null],
            ],
            self::column(static fn(Field $field): array => [$field->label, $field->type, $field->default]),
        );
    }

    #[Test]
    public function linksToPurgeUrls(): void
    {
        static::assertEquals(
            [
                new Contribution(
                    region: 'layout-nav',
                    type: 'link',
                    label: 'Purge URLs',
                    icon: 'link',
                    url: '/admin/extension/cloudflare/purge-urls',
                ),
            ],
            self::section()->contributions,
        );
    }

    #[Test]
    public function offersPurgeEverythingOrNothingWhenContentChanges(): void
    {
        static::assertSame(
            ['everything' => 'Purge everything', 'nothing' => 'Purge nothing'],
            self::section()->form->field('on_change')?->options,
        );
    }

    #[Test]
    public function offersTestConnectionAndPurgeEverythingOnTheViewScreen(): void
    {
        static::assertEquals(
            [
                new Operation(
                    name: 'test-connection',
                    label: 'Test connection',
                    icon: 'plug-connected',
                    privilege: 'update',
                    handler: TestConnection::class,
                    surface: 'view',
                ),
                new Operation(
                    name: 'purge-everything',
                    label: 'Purge everything',
                    icon: 'trash',
                    privilege: 'update',
                    handler: PurgeEverything::class,
                    surface: 'view',
                    confirm: true,
                    confirmMessage: 'Purge everything Cloudflare holds for this site? Pages will be slower until'
                        . ' they are cached again.',
                ),
            ],
            self::section()->operations,
        );
    }
}
