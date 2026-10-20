<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Form\Group;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Operation\Contribution;
use Contenir\Extension\Api\Operation\Operation;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Api\Settings\SettingsSection;
use Contenir\Extension\Cloudflare\Field\ZoneIdField;
use Contenir\Extension\Cloudflare\Handler\PurgeUrls;
use Contenir\Extension\Cloudflare\Operation\PurgeEverything;
use Contenir\Extension\Cloudflare\Operation\TestConnection;

use function sprintf;

/**
 * The add-on's settings section, Cloudflare under Integration, where the
 * CMS's Cloudflare plugin was. The zone ID, email and Global API Key keep the
 * plugin's keys (integration.cloudflare.*); the plugin's account ID and
 * Origin CA key are no longer drawn, but their stored values stay.
 *
 * @api
 */
final class CloudflareSection
{
    public const string NAME = 'cloudflare';

    public const string ZONE_ID = 'zone_id';

    /**
     * @mago-expect lint:no-literal-password The field's name, not a secret.
     */
    public const string API_TOKEN = 'api_token';

    public const string EMAIL = 'email';

    /**
     * @mago-expect lint:no-literal-password The field's name, not a secret.
     */
    public const string API_KEY = 'api_key';

    public const string ON_CHANGE = 'on_change';

    public const string PURGE_ASSETS = 'purge_assets';

    /**
     * The section, with its field type named for the add-on's handle.
     */
    public static function define(Handle $handle): SettingsSection
    {
        return new SettingsSection(
            name: self::NAME,
            title: 'Cloudflare',
            category: SettingsCategory::Integration,
            form: new Form(
                [
                    new Field(
                        name: self::ZONE_ID,
                        label: 'Zone ID',
                        type: $handle->fieldType(ZoneIdField::NAME),
                        description: 'From the zone\'s Overview page in Cloudflare, under API: 32 letters and digits.',
                        setting: 'integration.cloudflare.zone_id',
                    ),
                    Field::password(
                        name: self::API_TOKEN,
                        label: 'API token',
                        description: 'Make one in Cloudflare under My Profile > API Tokens, for this zone only, with'
                            . ' Zone > Cache Purge > Purge and Zone > Zone > Read. Kept in this environment\'s local'
                            . ' settings.',
                    ),
                    new Field(
                        name: self::ON_CHANGE,
                        label: 'When content changes',
                        type: FieldType::Radio,
                        description: 'What happens to Cloudflare\'s copy of the site when a page or setting changes.'
                            . ' Saves that change nothing visitors see, such as a profile or permissions, never purge.',
                        default: OnChange::Everything->value,
                        options: [
                            OnChange::Everything->value => 'Purge everything',
                            OnChange::Nothing->value    => 'Purge nothing',
                        ],
                    ),
                    new Field(
                        name: self::PURGE_ASSETS,
                        label: 'Purge files when assets change',
                        type: FieldType::Checkbox,
                        description: 'Purge an image or file from Cloudflare when it is uploaded again, renamed or'
                            . ' deleted, so visitors never see the old one.',
                        default: true,
                    ),
                    new Field(
                        name: self::EMAIL,
                        label: 'Account email',
                        type: FieldType::Email,
                        description: 'Only for the Global API Key, while there is no API token.',
                        setting: 'integration.cloudflare.email',
                    ),
                    Field::password(
                        name: self::API_KEY,
                        label: 'Global API Key',
                        description: 'Deprecated: the Global API Key can do anything your Cloudflare account can.'
                            . ' Used only while there is no API token.',
                        setting: 'integration.cloudflare.api_key',
                    ),
                ],
                [
                    new Group('connection', 'Connection', [self::ZONE_ID, self::API_TOKEN]),
                    new Group('purging', 'Purging', [self::ON_CHANGE, self::PURGE_ASSETS]),
                    new Group('global_key', 'Global API Key (deprecated)', [[self::EMAIL, self::API_KEY]]),
                ],
            ),
            operations: [
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
                    confirmMessage: 'Purge everything Cloudflare holds for this site? Pages will be slower until they'
                        . ' are cached again.',
                ),
            ],
            contributions: [
                new Contribution(
                    region: 'layout-nav',
                    type: 'link',
                    label: 'Purge URLs',
                    icon: 'link',
                    url: sprintf('/admin/extension/%s/%s', $handle->value, PurgeUrls::NAME),
                ),
            ],
        );
    }
}
