<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare;

use Contenir\Cdn\PurgerInterface;
use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Cloudflare\Field\ZoneIdField;
use Contenir\Extension\Cloudflare\Handler\PurgeUrls;
use Contenir\Extension\Cloudflare\Purge\PurgerFactory;
use Override;

/**
 * The Cloudflare add-on: a settings section with Test connection and Purge
 * everything, a Purge URLs screen, a field type for the zone ID, and the
 * purger the CMS calls when content and assets change.
 *
 * @api
 */
final class CloudflareExtension implements ExtensionInterface
{
    #[Override]
    public function register(Registry $registry): void
    {
        $registry->fieldType(ZoneIdField::NAME, ZoneIdField::class);
        $registry->settingsSection(CloudflareSection::define($registry->handle()));
        $registry->handler(PurgeUrls::NAME, PurgeUrls::class);
        $registry->provide(PurgerInterface::class, PurgerFactory::class);
    }
}
