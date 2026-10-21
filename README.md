# contenir-extension/cloudflare

[![Continuous Integration](https://github.com/contenir-extension/cloudflare/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir-extension/cloudflare/actions/workflows/continuous-integration.yml)

The Cloudflare add-on for Contenir CMS. It purges Cloudflare's cache when content and files change, and signs in with
an API token rather than the account's Global API Key. Editors get a connection test, Purge everything and Purge URLs,
and every purge is logged with Cloudflare's request id. It replaces the CMS's built-in Cloudflare plugin and its
`CloudflarePurger`. The `integration.cloudflare.*` keys are unchanged, so moving a site to it needs no new settings
until someone adds a token.

The work itself is in [contenir/contenir-cdn](https://github.com/contenir/contenir-cdn): the purger interface, batching, URL
checks and the Cloudflare client. This add-on adapts it to the CMS.

## What it adds

**A settings section**, Site Settings > Integration > Cloudflare, where the CMS's Cloudflare plugin was:

| Field | Stored under | Default |
|---|---|---|
| Zone ID | `integration.cloudflare.zone_id` | blank |
| API token, masked | `extensions.cloudflare.api_token`, in local settings | blank |
| When content changes: Purge everything or Purge nothing | `extensions.cloudflare.on_change` | Purge everything |
| Purge files when assets change | `extensions.cloudflare.purge_assets` | on |
| Account email (deprecated) | `integration.cloudflare.email` | blank |
| Global API Key (deprecated), masked | `integration.cloudflare.api_key`, in local settings | blank |

The zone ID is checked as it is saved, by the add-on's field type `cloudflare:zone-id`. The token wins whenever there
is one. The email and Global API Key are used only while a site has no token, and Test connection warns while they
are. The old plugin's account ID and Origin CA key are no longer drawn, but their stored values stay.

Make the token in Cloudflare under My Profile > API Tokens, for the site's zone only, with these permissions:

| Permission | Needed for |
|---|---|
| Zone > Cache Purge > Purge | every purge |
| Zone > Zone > Read | Test connection, and checking Purge URLs against the zone's domain |

**The purger the CMS calls** (P13). The add-on provides contenir/contenir-cdn's `PurgerInterface` through
`Registry::provide()`. While the add-on is enabled, the CMS's cache clearing and file browser purge through it; while
it is disabled, nothing purges. It reads the settings at each purge:

| The CMS asks to | The add-on |
|---|---|
| purge everything, after a save that changes the public site | purges everything, unless "When content changes" says nothing |
| purge an asset's URLs, after an upload, rename or delete | purges them, in requests of up to 100, unless "Purge files when assets change" is off |

Saves that change nothing public, such as a profile or a role's permissions, don't ask (CDN-1). Until the zone and a
token or key are entered, the add-on purges nothing, so a development copy without them keeps working.

**Two buttons on the section's view screen**, for someone who may update it:

| Button | Does |
|---|---|
| Test connection | checks the credentials and reads the zone, without purging, then shows its domain, plan and purge methods |
| Purge everything | after a confirm step, drops everything Cloudflare holds for the zone |

**A Purge URLs screen**, at `/admin/extension/cloudflare/purge-urls`, linked from the section. It takes full
addresses, one to a line. Each must be on the zone's domain or one of its subdomains, which the screen reads from
Cloudflare. Refused lines stay in the box, each with its reason. Opening it needs `extension.cloudflare.purge-urls`
View, and purging needs Update.

**The purge log.** Every request to purge is logged under the source `extension.cloudflare`, including the ones
Cloudflare refuses or can't be reached for:

| Context key | Holds |
|---|---|
| `trigger` | `content` (a save), `asset` (a file) or `person` (a button or the Purge URLs screen) |
| `scope` | `everything` or `urls` |
| `count`, `targets` | how many URLs, and which |
| `result` | `purged`, `rejected` or `failed` |
| `request_id` | Cloudflare's id for the request (its `cf-ray`), for its support |
| `status`, `error` | the HTTP status, and Cloudflare's errors or why it couldn't be reached |

Neither the token nor the key is ever logged. The log holds public URLs only: no visitor data, and no personal data
beyond what the CMS's log adds itself.

It needs no listeners, tables, scripts or other add-ons.

## Requirements

- PHP 8.3, 8.4 or 8.5
- A CMS that hosts contenir/extension-api 0.6

## Installing

```bash
composer require contenir-extension/cloudflare
```

Then enable it in the site's `config/autoload/extensions.global.php`:

```php
return ['extensions' => ['enabled' => ['cloudflare']]];
```

---

## Notes for later add-ons

This add-on follows the [Tracking add-on](https://github.com/contenir-extension/tracking#building-an-add-on)'s
layout, tooling and release steps. These are the parts it does differently, which a later add-on may need too.

### Providing a service the CMS calls

Where core calls something an add-on supplies, the interface lives in the add-on's library, and the CMS and the add-on
both require that library. The add-on declares it with `provide()`, and a factory makes it with the add-on's context:

```php
$registry->provide(PurgerInterface::class, PurgerFactory::class);
```

The host makes it once, and only while the add-on is enabled. A second enabled add-on providing the same interface is
logged and not used.

### Operations that need the add-on's settings

An operation on the add-on's settings section gets its add-on's context from `OperationContextInterface::extension()`
(API 0.6). `Operation\AbstractCloudflareOperation` reads the settings from it and says what to enter when something
is missing, before any operation reaches Cloudflare.

### A button that needs input

Operations are buttons with no fields. Purge URLs needs a text box, so it is a described screen
(`Handler\PurgeUrls`), linked from the section by a `link` contribution in the `layout-nav` region. A POST answers
with the screen again, holding what still needs attention, plus its messages.

### Talking to an outside service

- Send HTTP through a PSR-18 client the add-on can be given. `Connections` defaults to Guzzle with short timeouts
  (`Connections::GUZZLE`), and tests hand every class that uses it a `Connections` over `FakeHttpClient`, through
  `InMemoryHost::boot()`'s services. Tests never reach Cloudflare.
- Mask secrets with `Field::password()`, which also keeps them in local settings. Never let a secret reach a message:
  contenir/contenir-cdn takes it out of every message it writes, and doesn't chain the HTTP client's exceptions, since their
  requests carry the headers.

### Testing

```bash
composer cs-check          # mago format --check, lint, guard
composer static-analysis   # mago analyze
composer test              # unit suite
composer test-integration  # conformance and the in-memory host
composer mutation-test     # infection
```

CI pins Mago 1.52.0, so format with that release.

## Licence

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
