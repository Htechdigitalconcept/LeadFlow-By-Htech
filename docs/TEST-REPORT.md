# HTECH LeadFlow · Verification report

**Version:** 0.9.0 release candidate · **Date:** 20 November 2026 · **Decision:** staging evaluation only.

## Automated and integration checks

| Check | Environment | Result |
|---|---|---|
| PHPUnit | WordPress 7.1.5, PHP 8.4.28, MariaDB 11.8.6, WooCommerce inactive | **Pass** — 11 tests, 174 assertions |
| PHPUnit | Same stack, WooCommerce 11.3.1 active | **Pass** — 11 tests, 174 assertions |
| PHPUnit | WordPress 6.6, PHP 8.4.28, MariaDB 11.8.6 | **Pass** — 11 tests, 174 assertions |
| PHP and JavaScript syntax | PHP CLI and Node | **Pass** |
| PHPCompatibilityWP source scan | Configured for PHP 8.1+ | **Pass** — static scan, not a PHP 8.1 runtime test |
| WordPress Coding Standards | Full `WordPress` ruleset | **Fail** — 385 errors and 114 warnings; CI quality gate is not waived |
| Packaging | Runtime file allowlist, compressed ZIP integrity and SHA-256 | **Pass** |
| GitHub Actions | Workflow configured for PRs, main and version tags | **Not run remotely** — no authenticated repo or push |

PHPUnit exercises schema and index creation, defaults and capabilities, form rendering and validation, local storage, deduplication, cascades, WhatsApp URLs, nonce age and rate limiting, source/date search and conversion, CSV parsing and formula guards, privacy export/erase, retention, mail contents and failure handling, recipient privilege separation, and idempotent schema reapplication.

## Real WordPress browser and operator checks

The installable ZIP was uploaded through **Plugins → Add Plugin → Upload Plugin** in Chromium against a disposable WordPress 7.1.5 site with fresh LeadFlow tables, then activated. The browser suite exercised:

- Onboarding, form field editing, shortcode insertion through the Gutenberg editor, and a 390 px public enquiry.
- Bad public nonce, honeypot rejection, duplicate submission, real lead creation and campaign attribution.
- Lead editing, assignment, private note and history, follow-up scheduling and completion, persisted pipeline transitions, real analytics and the print button callback.
- Correct prefilled WhatsApp URL (no message sent), settings change reflected on the published page, selected CSV export, rejected non-CSV upload, CSV column mapping, duplicate and invalid preview rows, import, deletion, and confirmed duplicate merging with the secondary submission archived.
- Administrator nonce enforcement and signed-in subscriber denial of both lead read and mutation.
- Dashboard deactivation and reactivation with the lead and note retained; no uncaught browser JavaScript errors in the passing browser journey.

Manually reviewed the rendered public form and mobile pipeline at 390 px, and the analytics dashboard at desktop width. No new PHP debug diagnostics were observed in these browser checks. Separate WP-CLI operator checks confirmed actual uninstall **keeps data by default**, reinstall reads those records, and explicit purge on uninstall drops all plugin tables. There was no old public release to upgrade from: schema reapplication and reinstall are verified, not a cross-version upgrade.

## Not verified

- Runtime PHP 8.1 / 8.3, MySQL, different database/proxy arrangements, multisite, Elementor, other themes, Safari/Firefox, assistive technology and production-scale load. Only the listed matrix is verified.
- Real mail or WhatsApp delivery (the plugin does not send WhatsApp messages). WooCommerce was activated for the database suite; shop and checkout journeys were not certified.
- Independent security audit, complete human-run release acceptance, and the remote GitHub checks. The walkthrough remains available as a reproducible automated browser journey.
