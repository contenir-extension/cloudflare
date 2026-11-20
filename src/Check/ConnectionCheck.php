<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Check;

use Contenir\Cdn\Cloudflare\ConnectionCheck as CloudflareConnection;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Cloudflare\CloudflareSettings;
use Contenir\Extension\Cloudflare\Connections;
use Override;

use function rtrim;
use function sprintf;

/**
 * Whether the add-on can reach Cloudflare: its zone and credentials entered, and Cloudflare accepting them. Until
 * then nothing is purged, silently, so this is where the CMS says so: in its update check and on the add-on's card.
 *
 * @api
 */
final readonly class ConnectionCheck implements CheckInterface
{
    public const string NAME = 'connection';

    public function __construct(
        private Connections $connections = new Connections(),
    ) {}

    private static function fix(): GuidedFix
    {
        return new GuidedFix(
            'Open the Cloudflare settings',
            'Enter the zone ID and an API token in Site Settings > Integration > Cloudflare, save, and press Test'
                . ' connection.',
        );
    }

    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        $settings = CloudflareSettings::from($context->settings());
        if (null === $settings->zone || null === $settings->credentials) {
            return CheckResult::warning(
                sprintf('%s Until then, nothing is purged from Cloudflare.', (string) $settings->missing()),
                self::fix(),
            );
        }

        $connection = (new CloudflareConnection($this->connections->client($settings->credentials)))->run(
            $settings->zone,
        );
        if (null === $connection->zone) {
            return CheckResult::failure(
                sprintf(
                    '%s. Nothing is purged from Cloudflare until it\'s put right.',
                    rtrim((string) $connection->problem, characters: '.'),
                ),
                self::fix(),
            );
        }

        if ($connection->deprecatedKey) {
            return CheckResult::warning(
                sprintf(
                    'Connected to %s with the Global API Key, which can do anything your Cloudflare account can.'
                        . ' Make an API token with Cache Purge and Zone Read for this zone, and enter it instead.',
                    $connection->zone->name,
                ),
                self::fix(),
            );
        }

        return CheckResult::success(sprintf('Connected to %s.', $connection->zone->name));
    }

    #[Override]
    public function label(): string
    {
        return 'Cloudflare connection';
    }
}
