<?php

declare(strict_types=1);

namespace Contenir\Extension\Cloudflare;

/**
 * What started a purge, as the purge log records it.
 *
 * @api
 */
enum Trigger: string
{
    /** An asset uploaded again, renamed or deleted */
    case Asset = 'asset';

    /** A save that changed the public site */
    case Content = 'content';

    /** Someone pressing Purge everything or Purge URLs */
    case Person = 'person';
}
