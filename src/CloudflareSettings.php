<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare;

use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Cloudflare\ZoneId;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Settings\SettingsInterface;

use function in_array;
use function is_string;

/**
 * The Cloudflare section's saved values, read once: the zone, how to sign
 * in, and what to purge. A zone ID that isn't one, or credentials missing a
 * part, read as not set.
 *
 * @api
 */
final readonly class CloudflareSettings
{
    public function __construct(
        public ?ZoneId $zone,
        public ?Credentials $credentials,
        public OnChange $onChange,
        public bool $purgeAssets,
    ) {}

    /**
     * @throws UnknownSettingException Never: every field read is declared.
     */
    public static function from(SettingsInterface $settings): self
    {
        return new self(
            ZoneId::tryParse(self::text($settings->get(CloudflareSection::ZONE_ID))),
            Credentials::choose(
                self::text($settings->get(CloudflareSection::API_TOKEN)),
                self::text($settings->get(CloudflareSection::EMAIL)),
                self::text($settings->get(CloudflareSection::API_KEY)),
            ),
            OnChange::fromSetting($settings->get(CloudflareSection::ON_CHANGE)),
            in_array($settings->get(CloudflareSection::PURGE_ASSETS), [true, 1, '1'], strict: true),
        );
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * What still has to be entered before the add-on can reach Cloudflare,
     * or null when nothing does.
     */
    public function missing(): ?string
    {
        return match (true) {
            null === $this->zone && null === $this->credentials
                => 'Enter the zone ID and an API token, and save, first.',
            null === $this->zone => 'Enter the zone ID, and save, first.',
            null === $this->credentials => 'Enter an API token, and save, first.',
            default => null,
        };
    }
}
