<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Purge;

use Contenir\Cdn\PurgerInterface;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Cloudflare\CloudflareSettings;
use Contenir\Extension\Cloudflare\Connections;
use Contenir\Extension\Cloudflare\OnChange;
use Contenir\Extension\Cloudflare\Trigger;
use Override;

/**
 * The purger the CMS calls (P13), while the add-on is enabled: it purges
 * through Cloudflare as the section's settings say. A change to the public
 * site purges everything unless "When content changes" says nothing; an
 * asset's URLs are purged unless "Purge files when assets change" is off.
 * Until the zone and a token are entered, it purges nothing.
 *
 * It reads the settings at each purge, so a change takes effect at once.
 *
 * @api
 */
final readonly class SitePurger implements PurgerInterface
{
    public function __construct(
        private ExtensionContextInterface $context,
        private Connections $connections,
    ) {}

    #[Override]
    public function purge(array $urls): void
    {
        $settings = CloudflareSettings::from($this->context->settings());
        if (! $settings->purgeAssets) {
            return;
        }

        $this->connections->purger($settings, $this->context->logger(), Trigger::Asset)?->purge($urls);
    }

    #[Override]
    public function purgeEverything(): void
    {
        $settings = CloudflareSettings::from($this->context->settings());
        if (OnChange::Nothing === $settings->onChange) {
            return;
        }

        $this->connections->purger($settings, $this->context->logger(), Trigger::Content)?->purgeEverything();
    }
}
