# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased] - 2026-10-21

### Changed

- Requires contenir/extension-api `^0.6` and contenir/cdn `^0.1`, where it required their feature branches, which are
  merged and deleted. Until those are tagged, both resolve to their `main` (0.6.x-dev and 0.1.x-dev). contenir/cdn
  still comes through a `vcs` entry, since it isn't on Packagist yet.

### Added

- The Cloudflare settings section (Site Settings > Integration > Cloudflare): the zone ID, email and Global API Key
  under the CMS plugin's keys (`integration.cloudflare.*`), a new API token kept in local settings, "When content
  changes" (purge everything or nothing) and "Purge files when assets change". The email and Global API Key are a
  deprecated fallback while a site has no token.
- The `cloudflare:zone-id` field type, which checks the zone ID as it is saved.
- The purger the CMS calls, provided as contenir/cdn's `PurgerInterface` (`Purge\PurgerFactory`). While the add-on is
  disabled, the CMS purges nothing.
- Test connection and Purge everything (with a confirm step) on the section, and the Purge URLs screen.
- A log entry for every purge, including those Cloudflare refuses, with its trigger, scope, count, result and
  Cloudflare's request id, under the source `extension.cloudflare`.
