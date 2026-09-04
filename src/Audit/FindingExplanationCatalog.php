<?php

declare(strict_types=1);

namespace ComplyOps\Audit;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;

/**
 * Plain-language explanations for audit findings shown in the admin UI.
 */
final class FindingExplanationCatalog {

	/**
	 * @param array<string, mixed> $finding
	 */
	public function explain( array $finding, ?ControlDefinition $definition = null, bool $include_context = true ): string {
		$summary = $this->describe_control( $definition );

		if ( ! $include_context ) {
			return $summary;
		}

		$failure = $this->explain_failure( $finding, $definition );

		if ( null !== $failure && '' !== $failure ) {
			$summary .= ' ' . $failure;
		}

		return $this->append_finding_context( $summary, $finding );
	}

	public function describe_control( ?ControlDefinition $definition ): string {
		if ( null === $definition ) {
			return __(
				'This control checks a technical requirement on your WordPress site.',
				'complyops'
			);
		}

		$parts = array(
			$definition->description,
			$definition->rationale,
		);

		if ( null !== $definition->recommended_value && '' !== $definition->recommended_value ) {
			$parts[] = sprintf(
				/* translators: %s: recommended control state */
				__( 'Recommended state: %s', 'complyops' ),
				$definition->recommended_value
			);
		}

		if ( null !== $definition->manual_review_instructions && '' !== $definition->manual_review_instructions ) {
			$parts[] = sprintf(
				/* translators: %s: manual review steps */
				__( 'Manual review: %s', 'complyops' ),
				$definition->manual_review_instructions
			);
		}

		return trim( implode( ' ', array_filter( $parts ) ) );
	}

	/**
	 * @param array<string, mixed> $finding
	 */
	public function explain_failure( array $finding, ?ControlDefinition $definition = null ): ?string {
		$status = strtoupper( (string) ( $finding['status'] ?? '' ) );

		if ( ! in_array( $status, array( 'FAIL', 'WARNING', 'UNKNOWN' ), true ) ) {
			return null;
		}

		$control_id = (string) ( $finding['control_id'] ?? '' );
		$custom     = $this->custom_explanation( $control_id );

		if ( null !== $custom ) {
			return $custom;
		}

		if ( null === $definition ) {
			return match ( $status ) {
				'FAIL' => __(
					'This control failed verification during the latest audit.',
					'complyops'
				),
				'WARNING' => __(
					'This control partially met verification during the latest audit.',
					'complyops'
				),
				default => __(
					'This control could not be fully verified during the latest audit.',
					'complyops'
				),
			};
		}

		return match ( $status ) {
			'FAIL' => __(
				'This control failed verification during the latest audit. Review the observed state and update your site configuration or documentation as needed.',
				'complyops'
			),
			'WARNING' => __(
				'This control partially met verification during the latest audit. Review the observed state and address any gaps.',
				'complyops'
			),
			default => __(
				'ComplyOps could not fully verify this control automatically. Review the observed state and confirm configuration manually.',
				'complyops'
			),
		};
	}

	/**
	 * @param array<string, mixed> $finding
	 */
	public function explain_summary( array $finding, ?ControlDefinition $definition = null ): string {
		return $this->describe_control( $definition );
	}

	private function custom_explanation( string $control_id ): ?string {
		$explanations = $this->gdpr_explanations();

		return $explanations[ $control_id ] ?? null;
	}

	/**
	 * @return array<string, string>
	 */
	private function gdpr_explanations(): array {
		return array(
			'GDPR-CONSENT-001' => __(
				'ComplyOps could not confirm that visitors can grant or withhold consent for nonessential processing. Without a working consent mechanism, analytics, marketing tags, and embedded media may run before visitors have made a choice. Enable the ComplyOps consent manager or connect a supported third-party CMP, then re-run the audit.',
				'complyops'
			),
			'GDPR-CONSENT-002' => __(
				'Nonessential scripts or tags appear to run before consent is granted. That can send personal data to third parties without a clear lawful basis. Apply fix turns on the ComplyOps consent manager and blocks nonessential scripts until the visitor chooses analytics or marketing categories.',
				'complyops'
			),
			'GDPR-CONSENT-003' => __(
				'Analytics consent does not default to denied. Visitors should opt in to analytics rather than having measurement enabled automatically. Apply fix enables the native consent manager with analytics storage denied until the visitor explicitly allows analytics.',
				'complyops'
			),
			'GDPR-CONSENT-004' => __(
				'Marketing or advertising consent does not default to denied. Advertising signals should stay off until the visitor opts in. Apply fix enables the native consent manager with marketing and advertising categories denied by default.',
				'complyops'
			),
			'GDPR-CONSENT-005' => __(
				'Visitors may not be able to reopen consent settings and withdraw a previous choice. Consent should be as easy to withdraw as it is to give. Confirm your banner exposes a privacy or cookie settings link and that changing preferences updates stored consent immediately.',
				'complyops'
			),
			'GDPR-CONSENT-006' => __(
				'Consent choices may not persist reliably across page loads, which can cause repeated prompts or inconsistent enforcement. Check that consent storage is available in the browser and that no conflicting CMP clears ComplyOps consent state.',
				'complyops'
			),
			'GDPR-CONSENT-007' => __(
				'The active consent policy version is not recorded with visitor choices. When your banner text or categories change, you need traceability of which version applied. Publish a new consent banner version in ComplyOps after material policy changes.',
				'complyops'
			),
			'GDPR-CONSENT-008' => __(
				'Consent categories do not appear to be clearly separated. Bundled consent prevents visitors from allowing necessary cookies while refusing analytics or marketing. Review your banner categories and ensure analytics, marketing, and external media can be chosen independently.',
				'complyops'
			),
			'GDPR-TRACKING-001' => __(
				'Google Analytics may load or send data before analytics consent is granted. That can place measurement cookies and transmit page-view data prematurely. Apply fix enables GA/GTM enforcement so Analytics stays blocked until the visitor grants analytics consent.',
				'complyops'
			),
			'GDPR-TRACKING-002' => __(
				'Google Tag Manager may fire tags before consent state is established. GTM can load multiple vendors from a single container, so missing consent gating has broad impact. Apply fix enables enforcement and Consent Mode defaults before applicable GTM tags run.',
				'complyops'
			),
			'GDPR-TRACKING-003' => __(
				'ComplyOps detected third-party scripts that are not mapped to a known integration. Unknown trackers cannot be governed until you identify what they do and whether they need consent. Review the integration discovery report and document or remove unrecognized scripts.',
				'complyops'
			),
			'GDPR-TRACKING-004' => __(
				'Third-party cookies were observed and should be reviewed for transparency and lawful processing. This finding is informational: use the cookie inventory to understand which vendors set cookies and whether they align with your privacy policy.',
				'complyops'
			),
			'GDPR-TRACKING-005' => __(
				'Advertising or marketing tags may be enabled before explicit consent. Ad storage and personalization signals should remain denied by default. Apply fix configures Consent Mode so ad_storage, ad_user_data, and ad_personalization default to denied until marketing consent is granted.',
				'complyops'
			),
			'GDPR-GA-001' => __(
				'Google Consent Mode v2 is not fully configured while Google tags are present. Consent Mode tells Google tags whether analytics and ads storage are allowed. Apply fix injects Consent Mode v2 defaults that deny nonessential signals until consent is updated.',
				'complyops'
			),
			'GDPR-GA-002' => __(
				'analytics_storage does not default to denied. GA4 can store analytics cookies before a visitor opts in. Apply fix sets analytics_storage to denied until analytics consent is granted through your consent mechanism.',
				'complyops'
			),
			'GDPR-GA-003' => __(
				'ad_storage does not default to denied. Advertising cookies should not be set before marketing consent. Apply fix denies ad_storage by default and only updates it after the visitor allows marketing or advertising categories.',
				'complyops'
			),
			'GDPR-GA-004' => __(
				'ad_user_data does not default to denied. Sending user data for ads requires explicit consent under Consent Mode v2. Apply fix keeps ad_user_data denied until marketing consent is granted.',
				'complyops'
			),
			'GDPR-GA-005' => __(
				'ad_personalization does not default to denied. Personalized advertising requires explicit consent under Consent Mode v2. Apply fix keeps ad_personalization denied until the visitor opts in to marketing.',
				'complyops'
			),
			'GDPR-GA-006' => __(
				'GA4 event data retention should be reviewed against your data-minimization practices. Long retention increases privacy risk if identifiers or events are later combined with other data. Open GA4 Admin → Data retention, choose an appropriate period (often 14 months or less), and document the decision.',
				'complyops'
			),
			'GDPR-GA-007' => __(
				'Google Signals settings should be reviewed because they can combine data across devices and sessions. Open GA4 Admin → Data collection → Google signals, confirm whether cross-device reporting is required, and document your decision in your privacy records.',
				'complyops'
			),
			'GDPR-GA-008' => __(
				'Links between GA4 and Google Ads can expand how analytics data is used for advertising. Review linked Ads accounts in GA4 Admin and confirm each link is required for your documented purposes.',
				'complyops'
			),
			'GDPR-GA-009' => __(
				'Obvious personally identifiable information may be sent to Google Analytics in page URLs, events, or custom dimensions. Analytics should not process direct identifiers such as email addresses or authentication tokens. Review tag configuration and enable PII filtering where URL parameters are involved.',
				'complyops'
			),
			'GDPR-GA-010' => __(
				'Multiple GA4 implementations may be active at the same time. Duplicate tags inflate metrics and can bypass consent gating on one implementation while another still fires. Remove redundant GA snippets so only one implementation remains, coordinated with your consent and enforcement settings.',
				'complyops'
			),
			'GDPR-EMBED-001' => __(
				'YouTube embeds may load before External Media consent, which can set third-party cookies and transmit viewing data on page load. Apply fix replaces YouTube iframes with placeholders until the visitor grants External Media consent, and prefers the youtube-nocookie domain when playback starts.',
				'complyops'
			),
			'GDPR-EMBED-002' => __(
				'Third-party iframe embeds were inventoried for review. Embedded players and widgets can transfer data to vendors even when visitors do not interact with them. Use the inventory to confirm each embed is disclosed in your privacy notice and gated where required.',
				'complyops'
			),
			'GDPR-FORM-001' => __(
				'Forms that collect personal data should be visible to administrators responsible for privacy governance. This informational finding lists detected form plugins and surfaces so you can map data collection to your records of processing.',
				'complyops'
			),
			'GDPR-FORM-002' => __(
				'Marketing consent may be bundled with consent required to submit a form. Visitors should be able to submit a form without agreeing to unrelated marketing. Review form checkboxes and separate optional marketing opt-in from necessary processing notices.',
				'complyops'
			),
			'GDPR-FORM-003' => __(
				'Forms collecting personal data should link to or include a privacy notice at the point of collection. Confirm each high-risk form references your site privacy policy or an equivalent notice near the submit action.',
				'complyops'
			),
			'GDPR-FORM-004' => __(
				'Sensitive or high-risk form fields were detected and should be reviewed for additional safeguards. Free-text fields that accept health, financial, or other special-category data may need stronger controls than standard contact forms.',
				'complyops'
			),
			'GDPR-FORM-005' => __(
				'Form entry retention settings should be reviewed where your form plugins support them. Data should not be kept longer than necessary for the purpose collected. Document retention periods in your form plugins and align them with your privacy policy.',
				'complyops'
			),
			'GDPR-WP-001' => __(
				'WordPress does not have a published Privacy Policy page assigned under Settings → Privacy. Visitors need a stable location for privacy information. Create or assign a privacy policy page and link to it from your site footer and consent banner.',
				'complyops'
			),
			'GDPR-WP-002' => __(
				'WordPress personal data export tools are not available or not reachable. Data subjects may request a copy of their personal data, and administrators need the core export workflow. Confirm Tools → Export Personal Data is available to privileged users.',
				'complyops'
			),
			'GDPR-WP-003' => __(
				'WordPress personal data erasure tools are not available or not reachable. Data subjects may request deletion, and administrators need the core erasure workflow. Confirm Tools → Erase Personal Data is available to privileged users.',
				'complyops'
			),
			'GDPR-WP-004' => __(
				'Open user registration or related privacy settings should be reviewed because new accounts create additional personal data processing. Confirm registration is intentionally enabled and documented in your privacy notice if public signup is not required.',
				'complyops'
			),
			'GDPR-WP-005' => __(
				'Comment settings and related data collection should be reviewed. Comments can publish personal data publicly and set cookies when visitors participate. Review discussion settings, comment cookies, and Gravatar usage, then document your choices.',
				'complyops'
			),
			'GDPR-WP-006' => __(
				'WordPress REST API endpoints may expose more personal information than necessary, such as user lists or author details on public routes. Review exposed endpoints and restrict or filter responses where user data is not required for the feature.',
				'complyops'
			),
			'GDPR-PII-001' => __(
				'Sensitive URL query parameters may be included in analytics page-location data. Parameters such as email, name, or internal IDs in URLs can leak into GA4 reports. Apply fix enables PII filtering with a recommended blocklist before analytics payloads are sent.',
				'complyops'
			),
			'GDPR-PII-002' => __(
				'Authentication tokens or session identifiers may appear in URLs tracked by analytics. Tokens in analytics create both security and privacy risk. Apply fix enables PII filtering that strips common token and session parameter names from analytics data.',
				'complyops'
			),
			'GDPR-PII-003' => __(
				'Sensitive query parameters are present in site URLs and should be inventoried before exclusion can be verified. Discovery identifies parameter names without logging values so you can decide which must be stripped or avoided in links.',
				'complyops'
			),
			'GDPR-PII-004' => __(
				'Email addresses or similar direct identifiers may be used as a Google Analytics User-ID. Direct identifiers should not be sent to analytics providers. Remove email-based User-ID configuration and use pseudonymous identifiers if cross-session measurement is required.',
				'complyops'
			),
			'GDPR-RETENTION-001' => __(
				'Analytics data retention periods should be reviewed against data-minimization practices. Retain GA4 events only as long as needed for your documented purpose. Review GA4 Admin retention settings and record the chosen period.',
				'complyops'
			),
			'GDPR-RETENTION-002' => __(
				'Form entry retention should be reviewed in supported plugins. Holding submissions indefinitely increases risk if fields contain personal or sensitive data. Configure automatic deletion or export workflows aligned with your retention policy.',
				'complyops'
			),
			'GDPR-RETENTION-003' => __(
				'ComplyOps evidence and consent retention should be configured so the compliance tool does not keep records longer than necessary. Set an evidence retention period under ComplyOps settings and document it alongside your wider retention schedule.',
				'complyops'
			),
			'GDPR-SEC-001' => __(
				'The site is not consistently served over HTTPS. Transport encryption protects personal data in transit between visitors and your server. Update WordPress Address and Site Address to HTTPS URLs and enforce TLS across the site.',
				'complyops'
			),
			'GDPR-SEC-002' => __(
				'WordPress core is below the supported minimum version for this control profile. Unsupported core may lack security fixes that protect personal data. Plan an upgrade to a supported WordPress release and test plugins before production deployment.',
				'complyops'
			),
			'GDPR-SEC-003' => __(
				'Plugins with privacy or security impact may be outdated. Vulnerable or unmaintained plugins can expose personal data processed by your site. This informational finding highlights update posture; review plugin versions and apply updates on a regular schedule.',
				'complyops'
			),
			'GDPR-SEC-004' => __(
				'Administrative accounts and privileges should follow least-privilege practices. Excessive admin access increases the risk of unauthorized personal data exposure. Review administrator users, shared accounts, and role assignments, then remove unnecessary privileges.',
				'complyops'
			),
		);
	}

	/**
	 * @param array<string, mixed> $finding
	 */
	private function append_finding_context( string $explanation, array $finding ): string {
		$observed = trim( (string) ( $finding['observed'] ?? '' ) );
		$expected = trim( (string) ( $finding['expected'] ?? '' ) );

		if ( '' !== $observed ) {
			$explanation .= ' ' . sprintf(
				/* translators: %s: observed site state */
				__( 'Observed on this site: %s', 'complyops' ),
				$observed
			);
		}

		if ( '' !== $expected ) {
			$explanation .= ' ' . sprintf(
				/* translators: %s: expected control state */
				__( 'Expected: %s', 'complyops' ),
				$expected
			);
		}

		return trim( $explanation );
	}
}
