<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Handler;

use Contenir\Cdn\Cloudflare\Credentials;
use Contenir\Cdn\Cloudflare\ZoneId;
use Contenir\Cdn\UrlList;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\Screen;
use Contenir\Extension\Cloudflare\CloudflareSettings;
use Contenir\Extension\Cloudflare\Connections;
use Contenir\Extension\Cloudflare\Trigger;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_keys;
use function implode;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Purge URLs: a screen with a box for full addresses, one to a line. Each
 * must be on the zone's domain or one of its subdomains, which the screen
 * reads from Cloudflare; the rest are refused, each with its reason, and
 * left in the box. The purge is logged as started by a person.
 *
 * @api
 */
final readonly class PurgeUrls implements HandlerInterface
{
    public const string NAME = 'purge-urls';

    public const string FIELD = 'urls';

    public function __construct(
        private Connections $connections = new Connections(),
    ) {}

    /**
     * The screen, with $text in the box.
     */
    public static function screen(string $text = ''): Screen
    {
        return new Screen(
            title: 'Purge URLs',
            intro: 'Drop pages and files from Cloudflare\'s cache, so the next visitor gets them fresh from the site.'
                . ' Cloudflare purges up to 100 at a time, and longer lists are sent in batches.',
            form: new Form([
                new Field(
                    name: self::FIELD,
                    label: 'URLs',
                    type: FieldType::Textarea,
                    description: 'Full addresses on the site\'s domain, one to a line, such as'
                        . ' https://www.example.com/news/.',
                    default: $text,
                    attributes: ['rows' => 10],
                ),
            ]),
        );
    }

    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        if ('POST' !== $request->getMethod()) {
            return HandlerResult::screen(self::screen());
        }

        $body     = $request->getParsedBody();
        $text     = is_array($body) && is_string($body[self::FIELD] ?? null) ? $body[self::FIELD] : '';
        $settings = CloudflareSettings::from($context->settings());
        if (null === $settings->zone || null === $settings->credentials) {
            return HandlerResult::screen(self::screen($text))->withMessage('warning', (string) $settings->missing());
        }

        return $this->purge($text, $settings->zone, $settings->credentials, $context);
    }

    private function purge(
        string $text,
        ZoneId $zone,
        Credentials $credentials,
        ExtensionContextInterface $context,
    ): HandlerResult {
        [$domain, $problem] = $this->connections->domain($zone, $credentials);
        if (null === $domain) {
            return HandlerResult::screen(self::screen($text))->withMessage('danger', $problem);
        }

        $list   = UrlList::fromText($text, [$domain, "*.{$domain}"]);
        $result = HandlerResult::screen(self::screen(implode("\n", array_keys($list->refused))));
        if ([] === $list->urls && [] === $list->refused) {
            return $result->withMessage('warning', 'Enter the URLs to purge, one to a line.');
        }

        if ([] !== $list->urls) {
            $report = $this->connections
                ->purgerFor($zone, $credentials, $context->logger(), Trigger::Person)
                ->purgeUrls($list->urls);
            $result = $report->succeeded()
                ? $result->withMessage('success', sprintf(
                    'Cloudflare purged %d %s.',
                    $report->count,
                    1 === $report->count ? 'URL' : 'URLs',
                ))
                : $result->withMessage('danger', implode(' ', $report->problems));
        }

        foreach ($list->refused as $url => $reason) {
            $result = $result->withMessage('warning', sprintf('Not purged: %s. %s.', $url, $reason));
        }

        return $result;
    }
}
