# HTECH LeadFlow

**Capture. Organize. Convert.** A self-hosted WordPress enquiry and lead-management plugin by HTECH Digital Solutions.

**Version 0.9.0 is a release candidate for staging, not a production release.** See the [verification report](docs/TEST-REPORT.md), [security review](docs/SECURITY-REVIEW.md), and [known issues](BUGS.md) before distributing it.

LeadFlow stores data in your WordPress database. It does not require a licence key, an HTECH account, a subscription, a CDN or an external CRM. WhatsApp links open WhatsApp when clicked; they neither send messages nor confirm delivery. Email is handed to `wp_mail()` and depends on how your site delivers email.

## Install and configure

1. Back up your site and install on staging first. Target minimums are **WordPress 6.6**, **PHP 8.1**, and a WordPress-compatible MySQL/MariaDB server with InnoDB. Only configurations in the test report were exercised. Use supported, patched versions in production.
2. In WordPress, choose **Plugins → Add Plugin → Upload Plugin**, select `htech-leadflow-0.9.0.zip`, install and activate it. On multisite, use site-level activation; network activation is not supported.
3. In **LeadFlow → Overview**, open setup, enter your business details and create your first form. You can skip setup and configure the features independently.
4. Publish the shortcode, submit a test enquiry while signed out, then inspect **LeadFlow → Leads**. Test real mail delivery independently if you enabled it.

See the [client setup guide](docs/CLIENT-GUIDE.md) for daily use.

| Shortcode | Attributes | Result |
|---|---|---|
| `[htech_lead_form id="1"]` | `id` is the numerical ID of an active form; required for rendering. | Enquiry form with the chosen fields, validation and local settings. |
| `[htech_whatsapp]` | None. | Configured business click-to-chat button. No output if the number or device visibility is unavailable. |

Use the Shortcode block in Gutenberg; in Elementor or another builder, use its shortcode widget if available. See the test report before treating a builder as verified.

## Dashboard map

| Area | What you do there |
|---|---|
| Overview | Run onboarding, see stage totals and overdue work. |
| Leads | Search and filter, edit records, add private notes, assign a user, follow up, and export selected leads. |
| Forms | Create and edit forms, map standard contact fields, configure consent and success behavior. |
| Pipeline | Move a lead between six stages. Board shows the eight newest leads per stage; the table is paginated. |
| Follow-ups | See open, overdue, due today, upcoming, or completed work. |
| Analytics | Report on lead totals by stage, source, page, form, and campaign. Print from the browser. |
| WhatsApp Settings | Set international number, messages, button label, placement, colour and device visibility. |
| Notifications | Set email recipients, subject, body, disclosed fields, and optional assignment emails. |
| General Settings | Set business details, retention, privacy link, optional first-page attribution, scoring and uninstall policy. |
| Tools | Get help, download a full CSV or map and preview a CSV import. |

**Conversion rate** is the percentage of leads received in the selected period that are currently marked Converted, not the percentage of sales completed during that period. Zero leads produces 0%. Priority is a configurable checklist, not a prediction.

## Troubleshooting

- **Expired form session:** purge your cache after a change and exclude form pages from page caching. Reload the page, wait two seconds, then submit. The plugin does not depend on JavaScript to save a valid form.
- **No email received:** confirm notifications are enabled and check both form and global recipients. Check WordPress mail setup, the provider and spam folders. A `wp_mail()` success result is not proof of delivery.
- **Missing campaign or referrer:** browsers may suppress referrers. Without JavaScript, campaign collection may be unavailable. Without optional first-page tracking, reports use the current form page. Use non-personal campaign labels.
- **WhatsApp does not open:** use a number in international format, including the leading `+`. Check visibility and whether WhatsApp is available on the device.
- **Privacy tools cannot find a record:** automated matching uses the lead’s current mapped email. Review phone-only leads, old emails, custom fields, merged archives, CSVs and mail copies separately.
- **Removing the plugin:** deactivation never deletes leads. Uninstall keeps data unless you explicitly enable the purge option. Back up before enabling purge.

## Develop and test

See [developer documentation](docs/DEVELOPER.md) for schema, permission scopes, a disposable WordPress setup, verification and release commands. See [SECURITY.md](SECURITY.md) for responsible disclosure and [CHANGELOG.md](CHANGELOG.md) for version history.
