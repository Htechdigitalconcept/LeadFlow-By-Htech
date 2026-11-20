# Bugs and known issues · 0.9.0

Severity refers to this release candidate. Closed items have a regression check; open items have an acceptance test identified for follow-up.

| ID | Severity / state | How to reproduce and root cause | Resolution and regression check |
|---|---|---|---|
| LF-001 | Medium / fixed | Add a field and use an assistive-tool lookup for the exact field type label. The wrapped select made option text part of some label lookups. | Assign explicit, per-instance label/control IDs; update IDs when cloning fields. `browser-qa.cjs` selects the custom field type by its exact label. |
| LF-002 | Medium / fixed | On a site using plain permalinks, strip all query parameters when deriving a form landing URL; different pages become indistinguishable in analytics. | Retain allowlisted numeric `p`/`page_id` for attribution, keep a separate canonical return URL, strip other parameters. PHPUnit attribution test. |
| LF-003 | Low / fixed (QA only) | Use the WP-CLI-generated local document-root URL without checking the stored `home` and `siteurl`; browser login can loop through an unintended URL path. | Set both URLs explicitly in the disposable setup. Browser test verifies authenticated login and upload. |
| LF-004 | Medium / fixed | Give a team member only form-editing access and submit a per-form notification recipient override. Without a separate check the edit could reroute customer data. | Require settings capability for the override; preserve the existing address for form-only editors. PHPUnit covers existing, new and authorized settings cases. |
| LF-005 | Release blocker / open | Run `vendor/bin/phpcs`. Strict WPCS still flags PHP conventions, output-escaping recognition, and custom-table query patterns, among other findings. | Review and resolve each finding, including any justified narrow annotations. Acceptance: CI WPCS step and rerun of functional tests both pass. No rule is suppressed in CI. |
| LF-006 | Medium / open | Use full-page caching on a page with a form (particularly across logins or after nonce expiry). Cached HTML contains a time-limited, session-dependent nonce. | Exclude form pages from shared page caching. Future regression: cached guest form and signed-in form after automated token refresh is implemented. |
| LF-007 | Medium / open | Edit the lead's mapped email, use an unmapped email field, or capture phone-only contacts; search WordPress Privacy using a historical or unmapped address. | Privacy lookup currently uses the current mapped email. Review those records manually. Future regression: reviewed historical-address lookup without deleting unrelated records. |
| LF-008 | Medium / open | Send submissions from many addresses, or place WordPress behind a proxy presenting all visitors as one IP. The limiter keys on server-reported IP per form. | Configure host-level limits and correct trusted-proxy handling outside the plugin; no untrusted forwarding headers are used. Future regression: load and address-sharing test on the intended host. |
| LF-009 | Low / open | Import identical, previously absent contacts concurrently using multiple administrators; detection isn't globally serialized between imports. | Normal import rechecks before insert; remaining duplicates can be reviewed. Future regression: concurrent import test with a lock or documented batch semantics. |

## Other product limits

- Site-level activation only; no multisite or network activation compatibility claim. See the qualification matrix before use with Elementor, other themes, PHP 8.1/8.3, or MySQL.
- Kanban moves use stage controls instead of drag-and-drop. The board shows up to eight recent leads per stage. The full table is paginated. Lead detail shows the latest 100 notes, activities and follow-ups in each section.
- CSV importing accepts up to 200 rows and does not restore complete CRM history from a CSV. It will not merge records automatically. Reviewed merges refuse differing non-empty email addresses to reduce the risk of mixing data subjects.
- Changing scoring rules affects new or subsequently edited records, not every stored score. Business-email points use a short list of common personal domains.
- Lead management has capability-based access, not assignee-only access. Administrators should not grant `htecl_view` to users who may see only their own leads.
- An inactive or deleted form no longer accepts enquiries. Deleting a form does not delete its existing leads. No automatic reminder emails or WhatsApp delivery tracking.
- WordPress or plugin caches, logs, exports, mailboxes and backups may hold copies independently of LeadFlow cleanup.
