# HTECH LeadFlow 0.9.0 · First-party security review

**Scope:** code and browser checks for the release candidate. Not a third-party audit, penetration test, production sign-off, or guarantee. The failing WPCS audit and missing compatibility evidence block commercial release.

| Control | What was reviewed |
|---|---|
| Authorization and direct object access | Admin route checks map operations to individual capabilities. Record access is site-wide for users with the view capability. Follow-up completion matches both the child and lead ID. Merge requires view, edit, delete, explicit confirmation and a server-checked match. A signed-in subscriber was denied lead detail and mutation in browser QA. |
| CSRF | Admin changes use action- and record-bound WordPress nonces. Public form posts require a form-scoped nonce and signed issue time; unauthenticated WordPress nonces are not a substitute for authentication or anti-bot controls. Invalid public and administrative nonces were exercised. |
| SQL input | Fixed query fragments and allowlisted sorting/groupings; table identifiers use `%i`, values use placeholders or `$wpdb` methods. Checked search, dates, attribution storage and duplicate matching. Direct custom-table queries and interpolation notices still need line-by-line WPCS disposition. |
| Output and private data | Sanitize public input by field type and escape at output by HTML context. Public responses contain a signed status message, not submitted values, notes, or record detail. Runtime does not register a lead REST API. Verify public cache, mail routing and exported material at deploy-time. |
| Uploaded files | Only the authorized temporary CSV upload is read; require `.csv`, an actual PHP upload, size/row/column limits, UTF-8 text and no null bytes. No import file is moved into public web storage or executed. Browser test rejected a `.php` file. Do not treat a CSV export as a database backup. |
| Spreadsheet safety | Prefix formula-like values when exporting, including leading Unicode separator/control characters, and write CSV with explicit quoting behavior. Spreadsheet engines vary; import as text if unsure. |
| Redirects | Allow same-origin configured destinations, validate URLs and use WordPress safe redirects. Fields and internal activity are not included in redirect URLs. |
| Abuse and data minimization | Honeypot, time checks, scoped nonce, short-lived keyed duplicate fingerprint, bounded input, keyed rate counters and periodic cleanup. No IP storage or invasive fingerprinting. Botnet or shared-IP that bypasses or over-triggers per-IP controls remains possible. |
| Email disclosure | Selected fields only; assignment mail has only a dashboard link. Form editors without settings cannot reroute notifications. `wp_mail()` acceptance does not prove receipt; the lead record is retained on failure. |
| Retention and erasure | Retention is optional and depends on WordPress cron. Erasure cascades to child tables for a matched mapped email. Matching limitations and secondary copies require administrator review. Opt-in uninstall was tested separately. |
| Dependencies | No third-party package is included at runtime. Composer audit of locked development dependencies reported no known advisory at the time of verification. |

**Before deployment:** finish the strict WPCS review; threat-model the host/proxy/caching setup; run the workflow on GitHub; have a reviewer independent of the implementation inspect capability boundaries, SQL and escaping; and test the site's data handling, backup and notification procedures. Disable public display of PHP/database diagnostics in any client environment. Report discoveries through the private process in `SECURITY.md`.
