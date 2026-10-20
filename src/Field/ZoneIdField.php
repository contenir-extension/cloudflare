<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Field;

use Contenir\Cdn\Cloudflare\ZoneId;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Field\FieldTypeInterface;
use Contenir\Extension\Api\Form\FieldType;
use Override;

use function is_string;
use function trim;

/**
 * A Cloudflare zone ID, checked as it is saved and stored lower-cased. Blank
 * is allowed: the add-on then purges nothing.
 *
 * @api
 */
final class ZoneIdField implements FieldTypeInterface
{
    public const string NAME = 'zone-id';

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    #[Override]
    public function component(): ?string
    {
        return null;
    }

    #[Override]
    public function control(): FieldType
    {
        return FieldType::Text;
    }

    #[Override]
    public function normalise(mixed $value): mixed
    {
        $text = self::text($value);
        $id   = ZoneId::tryParse($text);

        return null === $id ? $text : $id->value;
    }

    #[Override]
    public function options(ExtensionContextInterface $context): array
    {
        return [];
    }

    #[Override]
    public function validate(mixed $value): array
    {
        $text = self::text($value);
        if ('' === $text || null !== ZoneId::tryParse($text)) {
            return [];
        }

        return ['A zone ID is 32 letters and digits. Copy it from the zone\'s Overview page in Cloudflare, under API.'];
    }
}
