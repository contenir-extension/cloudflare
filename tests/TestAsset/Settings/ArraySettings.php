<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\TestAsset\Settings;

use Contenir\Extension\Api\Event\ActionResult;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Settings\SettingsInterface;
use Contenir\Extension\Cloudflare\CloudflareSection;
use LogicException;
use Override;

use function array_key_exists;

/**
 * The Cloudflare section's settings held in an array: saved values over the
 * declared defaults, as a host reads them. The add-on never writes them.
 */
final readonly class ArraySettings implements SettingsInterface
{
    /**
     * @param array<string, mixed> $values By field name
     */
    public function __construct(
        private array $values = [],
    ) {}

    /**
     * @return list<Field>
     */
    private static function fields(): array
    {
        return CloudflareSection::define(new Handle('cloudflare'))->form->fields;
    }

    #[Override]
    public function all(): array
    {
        $defaults = [];
        foreach (self::fields() as $field) {
            $defaults[$field->name] = $field->default;
        }

        return [...$defaults, ...$this->values];
    }

    #[Override]
    public function get(string $field, mixed $default = null): mixed
    {
        $all = $this->all();
        if (! array_key_exists($field, $all)) {
            throw UnknownSettingException::field(CloudflareSection::NAME, $field);
        }

        return $all[$field] ?? $default;
    }

    #[Override]
    public function section(): string
    {
        return CloudflareSection::NAME;
    }

    #[Override]
    public function set(array $values): ActionResult
    {
        throw new LogicException('The add-on never writes its settings');
    }
}
