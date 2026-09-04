=== ComplyOps ===
Contributors: ncdlabs
Tags: gdpr, compliance, privacy, consent, audit
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Continuous technical compliance for WordPress: discover, enforce, monitor, remediate, verify, and report.

== Description ==

ComplyOps helps WordPress site owners and operators run **technical** GDPR readiness work inside wp-admin: site discovery, control evaluation, consent management, script enforcement, evidence collection, remediation helpers, and audit reporting.

ComplyOps verifies controls it can observe on your site, records evidence, and flags items that need manual review. **It does not replace legal counsel and does not certify legal compliance.**

= Free frameworks (included) =

* Built-in GDPR control catalog (47 technical controls across consent, analytics, forms, embeds, and WordPress configuration)
* Built-in OWASP Top 10 control catalog (24 WordPress-focused security controls mapped to OWASP Top 10 2021 categories)
* Built-in NIST CSF 2.0 control catalog (15 WordPress-focused cybersecurity readiness controls)
* Site discovery for plugins, scripts, iframes, forms, and third-party services
* Native consent banner and preference center (defers to an active third-party CMP when one is detected)
* Google Consent Mode v2 defaults, optional GA/GTM deferral, and script blocking before consent
* YouTube embed gating until External Media consent is granted
* Scheduled monitoring and configuration drift detection
* Audit runs with scored results, findings, history, and exportable reports
* Evidence log with JSON, CSV, and PDF export
* One-click remediation helpers for supported controls

= Optional compliance packs (sold separately, not included in this plugin) =

HIPAA, SOC 2, CCPA, WCAG, and other framework catalogs are **not bundled** in the WordPress.org plugin. Purchase a yearly subscription through Stripe Checkout at [ncdLabs ComplyOps](https://ncdlabs.com/products/complyops/store/), download the encrypted `.complyops-pack` file from your order confirmation, then import it from **ComplyOps → Controls → Install framework pack** with your unlock key. **Packs are not required for GDPR, OWASP, or NIST CSF functionality.**

= Who this is for =

* WordPress admins responsible for privacy-related technical controls
* Agencies operating client sites who need repeatable evidence and audit history
* Teams preparing for GDPR-related technical reviews (not a substitute for legal advice)

= External services =

ComplyOps connects to external services only in the cases below.

**Pack activation (ncdlabs.com)** — When you import a purchased compliance pack, ComplyOps sends your pack unlock key, framework identifier, and this site's URL to the ncdLabs activation API to verify the Stripe purchase and bind the license to one site:

* Endpoint: `https://ncdlabs.com/products/complyops/api/activate`
* Data sent: unlock key, framework ID, site URL
* When: only when you preview or install an encrypted pack you purchased and downloaded from Stripe Checkout
* Terms of use: [https://ncdlabs.com/products/complyops/](https://ncdlabs.com/products/complyops/)
* Privacy policy: [https://ncdlabs.com/privacy/](https://ncdlabs.com/privacy/); product: [https://ncdlabs.com/products/complyops/privacy/](https://ncdlabs.com/products/complyops/privacy/)

**Browser verification (ncdlabs.com, optional)** — When you import a purchased compliance pack, ComplyOps may call the ncdLabs provisioning API to enable hosted browser verification for consent and analytics checks. If configured, audit and discovery may also send scan targets to your configured browser verification service:

* Provision endpoint: `https://ncdlabs.com/products/complyops/api/browser-verification/provision`
* Default service: `https://browser-verify.ncdlabs.com`
* Data sent: pack unlock key, site URL (provision); scan target URL and verification token (when the hosted service is enabled)
* When: pack import provisioning; during audits/discovery when hosted browser verification is enabled in **Manage → Integrations**
* Security: verification endpoints must use HTTPS. Self-hosted endpoint domains must be explicitly allowed with the `complyops_browser_verification_allowed_hosts` filter.
* Terms of use: [https://ncdlabs.com/products/complyops/](https://ncdlabs.com/products/complyops/)
* Privacy policy: [https://ncdlabs.com/privacy/](https://ncdlabs.com/privacy/); product: [https://ncdlabs.com/products/complyops/privacy/](https://ncdlabs.com/products/complyops/privacy/)

**Google Analytics OAuth (Google + ncdlabs.com, optional)** — When you connect Google Analytics from **Manage → Integrations**, ComplyOps may use Google OAuth and the Google Analytics Admin API. If you have not configured your own Google OAuth client credentials, ComplyOps uses an ncdLabs OAuth proxy:

* Proxy endpoints: `https://ncdlabs.com/products/complyops/api/google/oauth/start` and `.../exchange`
* Google endpoints: `accounts.google.com`, `oauth2.googleapis.com`, `www.googleapis.com`, `analyticsadmin.googleapis.com`
* Data sent: OAuth state, authorization code, and Google Analytics account/property metadata needed to verify GA configuration
* When: only when an administrator starts or completes Google Analytics connection
* Terms of use: [https://ncdlabs.com/products/complyops/](https://ncdlabs.com/products/complyops/); Google: [https://policies.google.com/terms](https://policies.google.com/terms)
* Privacy policy: [https://ncdlabs.com/privacy/](https://ncdlabs.com/privacy/); Google: [https://policies.google.com/privacy](https://policies.google.com/privacy)

**Site self-scan (your own WordPress site)** — During discovery and audits, ComplyOps may request your site's public homepage and REST API to detect scripts, embeds, forms, and integrations. These requests stay on your site; ComplyOps does not send discovery results to ncdLabs.

No usage telemetry or analytics are sent to ncdLabs by the plugin.

= Source code for built assets =

Admin and front-end JavaScript and CSS are built with `@wordpress/scripts` (`package.json` and `webpack.config.js`). Human-readable sources ship in the plugin under `assets/src/`. Production builds ship in `build/`.

= Third-party libraries =

Composer production dependencies are MIT-licensed and GPL-compatible:

* chrome-php/chrome, chrome-php/wrench
* evenement/evenement
* monolog/monolog
* psr/log
* symfony/filesystem, symfony/process, symfony/polyfill-ctype, symfony/polyfill-mbstring, symfony/polyfill-php80

See each package's LICENSE file under `vendor/` for copyright notices.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/complyops/` or install through the WordPress Plugins screen.
2. Activate **ComplyOps** through the **Plugins** menu.
3. Open **ComplyOps** in the admin sidebar.
4. Run **Assure → Audits → Run audit** to generate your first GDPR technical readiness snapshot.
5. Configure **Manage → Consent** and **Manage → Integrations** as needed for your stack.

= Development build =

If you clone the repository, run `npm install && npm run build` before activating so `build/` assets exist.

== Frequently Asked Questions ==

= Do I need a paid pack to use ComplyOps? =

No. The free plugin includes complete GDPR, OWASP Top 10, and NIST CSF 2.0 technical control catalogs, consent manager, enforcement tools, audits, evidence, and reporting. Paid yearly packs add optional frameworks such as HIPAA, SOC 2, CCPA, and WCAG.

= Does ComplyOps make my site legally compliant? =

No. ComplyOps documents **technical** observations and helps you operate controls on your WordPress site. Legal compliance depends on your organization, data processing, policies, and jurisdiction. Consult qualified counsel.

= How do compliance packs work? =

Purchase a pack at [ncdlabs.com](https://ncdlabs.com/products/complyops/store/) via Stripe Checkout, download the encrypted `.complyops-pack` from your order confirmation, then use **Controls → Install framework pack** and enter your unlock key. Activation binds the pack to the current site URL. Paid pack files are never included in the free WordPress.org download.

= Does ComplyOps work with Complianz or other CMPs? =

Yes. When a supported third-party consent plugin is active, ComplyOps defers to it and disables the native consent banner to avoid conflicts.

= What data does ComplyOps store? =

Audit results, evidence, activity log entries, and settings are stored in your WordPress database. Installed framework pack catalogs are stored under your uploads directory at `wp-content/uploads/complyops/frameworks/`. Consent preferences are stored in the visitor's browser (localStorage) when using the native banner.

= What happens when I uninstall ComplyOps? =

Uninstalling deletes ComplyOps database tables, plugin settings, scheduled monitoring events, and uploaded framework pack files under `wp-content/uploads/complyops/`. This cannot be undone.

== Screenshots ==

1. ComplyOps dashboard with readiness score and control summary
2. Controls list with GDPR, OWASP Top 10, and pack management
3. Consent banner configuration and preview
4. Findings view with remediation actions

== Changelog ==

= 0.1.0 =
* Initial release: GDPR control catalog, discovery, audits, consent manager, enforcement, YouTube gating, evidence export, remediation helpers, and optional encrypted compliance pack import.

== Upgrade Notice ==

= 0.1.0 =
Initial public release.
