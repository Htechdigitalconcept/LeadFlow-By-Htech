=== HTECH LeadFlow ===
Contributors: htechdigitalsolutions
Tags: contact form, leads, crm, whatsapp, follow-ups
Requires at least: 6.6
Requires PHP: 8.1
Stable tag: 0.9.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Capture. Organize. Convert. A self-hosted enquiry and lead workspace for WordPress.

== Description ==

Build enquiry forms, track leads through a sales pipeline, schedule follow-ups, and review capture performance inside WordPress. No licence activation, external CRM, or required signup. WhatsApp links use standard click-to-chat. Email uses wp_mail() and depends on site configuration.

== Installation ==

1. On a staging site, open Plugins > Add Plugin > Upload Plugin.
2. Upload the ZIP, install it and activate HTECH LeadFlow.
3. Open LeadFlow > Overview, finish setup and copy the first form shortcode.
4. Paste [htech_lead_form id="1"] into a Shortcode block, using your actual form ID.
5. Submit a test enquiry and verify it under LeadFlow > Leads.

== Frequently Asked Questions ==

= Is there a subscription or API key? =
No. Click-to-chat links open WhatsApp only when clicked.

= What if I use full-page caching? =
Exclude pages containing forms from full-page caching. Form tokens are time-limited and may differ for logged-in visitors.

= Is data removed on deactivation? =
No. Uninstall removes data only if the administrator explicitly enables purge in General Settings.

== Changelog ==

= 0.9.0 =
First release candidate. See source documentation for verification status and limitations.
