<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare\Tests\Unit\Field;

use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Cloudflare\Field\ZoneIdField;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Factory\SettingsFactory;
use Contenir\Extension\Cloudflare\Tests\TestAsset\Settings\ContextFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ZoneIdFieldTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function accepted(): array
    {
        return [
            'a zone ID'   => [SettingsFactory::ZONE_ID],
            'upper-cased' => ['023E105F4ECEF8AD9CA31A8372D0C353'],
            'blank'       => ['  '],
            'nothing'     => [null],
        ];
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function normalised(): array
    {
        return [
            'trimmed and lower-cased' => [' 023E105F4ECEF8AD9CA31A8372D0C353 ', SettingsFactory::ZONE_ID],
            'not a zone ID, trimmed'  => [' abc ', 'abc'],
            'not text'                => [12, ''],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function refused(): array
    {
        return [
            'too short'     => ['023e105f4ecef8ad'],
            'an account ID' => ['not-a-zone-023e105f4ecef8ad9ca31a'],
        ];
    }

    #[Test]
    #[DataProvider('accepted')]
    public function acceptsAZoneIdOrNothing(mixed $value): void
    {
        static::assertSame([], (new ZoneIdField())->validate($value));
    }

    #[Test]
    public function isAPlainTextBoxWithNoOptions(): void
    {
        $field = new ZoneIdField();

        static::assertSame([FieldType::Text, null, []], [
            $field->control(),
            $field->component(),
            $field->options(ContextFactory::make()),
        ]);
    }

    #[Test]
    #[DataProvider('refused')]
    public function refusesWhatIsntAZoneId(mixed $value): void
    {
        static::assertSame(
            ['A zone ID is 32 letters and digits. Copy it from the zone\'s Overview page in Cloudflare, under API.'],
            (new ZoneIdField())->validate($value),
        );
    }

    #[Test]
    #[DataProvider('normalised')]
    public function storesTheZoneIdAsCloudflareWritesIt(mixed $value, string $expected): void
    {
        static::assertSame($expected, (new ZoneIdField())->normalise($value));
    }
}
