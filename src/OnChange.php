<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare;

/**
 * What a change to the public site does to Cloudflare's copy of it.
 *
 * @api
 */
enum OnChange: string
{
    case Everything = 'everything';
    case Nothing    = 'nothing';

    /**
     * The setting's choice, or Everything, today's behaviour, for anything
     * else.
     */
    public static function fromSetting(mixed $value): self
    {
        return self::Nothing->value === $value ? self::Nothing : self::Everything;
    }
}
