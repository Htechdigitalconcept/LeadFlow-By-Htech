# Developer guide

## Runtime design

The `HTECH\LeadFlow` namespace contains the activation/bootstrap, `Store` repository and migrations, `Security` validation/guard helpers, `Capture` public form controller, `Settings` configuration/WhatsApp/mail, `Admin` authorization and writes, `Screens` admin HTML, and `Tools` CSV/privacy/retention. `assets/` has scoped CSS and vanilla JavaScript; no remote runtime dependencies. Shortcodes and WordPress `admin-post.php` actions are used; no custom REST routes are registered.

`Store::activate()` applies the schema with `dbDelta()` and stores `htecl_schema`. Change schema version only with an explicit upgrade plan and test migration from real older packages. Dates for events are stored in UTC; due dates and analytics date inputs are interpreted in the site's timezone. Table names derive from `$wpdb->prefix`:

| Suffix | Contents and access patterns |
|---|---|
| `htecl_forms` | Title, active flag, timestamp and JSON field/config definitions. |
| `htecl_leads` | Contact and stage, form linkage, assignment, score, JSON field values/attribution. Indexed for creation, stage, source, form, email, phone, assignee, campaign; unique nullable submission fingerprint. |
| `htecl_notes`, `htecl_activities` | Child records keyed by lead, actor and timestamp. |
| `htecl_followups` | Lead-linked due dates and completion; index on completion/due. |
| `htecl_guard` | Expiring keyed rate counters; never stores raw IP addresses. |

Configuration is stored in the non-autoload `htecl_options` option. Custom table relations are maintained in application transactions; lead deletion removes its dependent rows. Removing a form does not remove submissions. Deactivation clears scheduled work only; uninstall purge is opt-in. Network activation is rejected. The daily `htecl_cleanup` action prunes guard rows, stale fingerprints, and opted-in lead retention in bounded batches. WP-Cron does not guarantee a precise execution time. No automatic follow-up mail or WhatsApp messaging is scheduled.

### Capabilities

`htecl_view`, `htecl_edit`, `htecl_delete`, `htecl_export`, `htecl_forms`, `htecl_settings`, `htecl_analytics`. Activation assigns them to the administrator role. To create a trusted role with read-only CRM access, a site administrator can add `htecl_view` with WP-CLI (`wp cap add ROLE htecl_view`). Lead mutations and exports also require `htecl_view`; merge needs view, edit and delete. View covers all leads on the same site. Do not grant it if an assignee should only see their own records. After any role change, test with a separate account. The first 200 eligible accounts are listed in the assignee picker.

## Disposable development and test site

Dependencies: a supported PHP CLI with DOM, mysqli, mbstring, and Composer; WP-CLI; optionally Docker Compose and Node/Playwright. PHP extensions available on a particular WordPress environment may vary. Never run destructive fixtures against client data.

```sh
composer install
docker compose up -d --wait
export HTECL_WP_PATH="$(mktemp -d)"
export HTECL_DB_NAME=htecl_test HTECL_DB_USER=htecl_test
export HTECL_DB_PASSWORD=local-test-only HTECL_DB_HOST=127.0.0.1:3307
./scripts/setup-test-wp.sh
HTECL_TEST_ALLOW_RESET=1 vendor/bin/phpunit --log-junit /tmp/htecl-junit.xml
vendor/bin/phpcs
vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.1- --extensions=php htech-leadflow.php uninstall.php src
./scripts/package.sh
```

For a different compatibility target, set `WP_VERSION` and optionally `WITH_WOO=true` before creating a new disposable site/database. The test bootstrap requires explicit reset permission because it deletes LeadFlow fixture tables' contents. GitHub Actions definitions in `.github/workflows/verify.yml` do not deploy or create a release. A failing strict WPCS check is a release blocker.

For browser verification, use a **fresh** disposable site. Set `HTECL_UPLOAD_QA=1` before setup if testing ZIP upload. Set `HTECL_QA_URL`, `HTECL_QA_PASSWORD` and optionally `HTECL_QA_ZIP` (absolute ZIP path). Use a local web server pointing at the disposable site; the PHP development server supports the fixture for local tests. Install Playwright in a disposable Node project and make it available to this script, e.g. via `NODE_PATH`, then run `node tests/browser-qa.cjs`. Do not use real customer information or publish QA credentials.

## Publish the source repository

No remote was configured in the workspace. Use the existing workspace repository, or clone the Git bundle with `git clone -b main /path/to/htech-leadflow-0.9.0.bundle htech-leadflow`. If you cloned from the bundle, remove the clone’s bundle origin with `git remote remove origin` before adding the GitHub origin. Authenticate using your HTECH-authorized GitHub account, then run from the repository:

```sh
gh auth login
gh repo create YOUR_ORG/htech-leadflow --private --source=. --remote=origin --push \
  --description 'Self-hosted WordPress lead capture, sales pipeline, follow-ups, WhatsApp and analytics.'
```

Replace `YOUR_ORG` with the real owner. If a repository already exists, add its real Git URL with `git remote add origin <actual-URL>` and run `git push -u origin main`. Review the first Actions run. Do not publish a stable tag or hand the ZIP to paying clients until the release gates pass.
