<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation\Tests;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\Evaluation\EvaluationContext;

/**
 * Pack-specific checks for CCPA, PCI, WCAG, and COPPA catalogs.
 */
final class CompliancePackTests extends AbstractSharedTests {

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'privacy.do_not_sell_link_discoverable' => array( $this, 'do_not_sell_link_discoverable' ),
			'privacy.opt_out_network_verified'      => array( $this, 'opt_out_network_verified' ),
			'payment.checkout_scripts_inventory'    => array( $this, 'checkout_scripts_inventory' ),
			'accessibility.statement_present'       => array( $this, 'accessibility_statement_present' ),
			'accessibility.axe_scan'                => array( $this, 'accessibility_axe_scan' ),
			'privacy.age_verification_review'       => array( $this, 'age_verification_review' ),
		);
	}

	public function do_not_sell_link_discoverable( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$needles = array( 'do not sell', 'do-not-sell', 'opt-out of sale', 'opt out of sale', 'your privacy choices' );
		$found   = $this->site_contains_phrase( $needles );

		if ( $found ) {
			return $this->pass(
				$definition,
				__( 'A Do Not Sell or equivalent opt-out link appears discoverable on the site.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'No Do Not Sell or equivalent opt-out link was detected in published pages or menus.', 'complyops' ),
			$definition->recommended_value ?? __( 'Provide a clear Do Not Sell or Share link where CPRA applies.', 'complyops' ),
		);
	}

	public function checkout_scripts_inventory( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( $context->browser_verification_available() ) {
			$scan = $context->browser_scenario( 'checkout_scan' );

			if ( is_array( $scan ) && isset( $scan['error'] ) && is_string( $scan['error'] ) ) {
				return $this->unknown(
					$definition,
					sprintf(
						/* translators: %s: browser error message */
						__( 'Checkout browser scan failed: %s', 'complyops' ),
						$scan['error']
					),
					$definition->recommended_value,
				);
			}

			if ( is_array( $scan ) && isset( $scan['payment_script_count'] ) ) {
				$count   = (int) $scan['payment_script_count'];
				$scripts = $scan['payment_scripts'] ?? array();
				$scripts = is_array( $scripts ) ? array_values( array_filter( $scripts, 'is_string' ) ) : array();

				if ( 0 === $count && empty( $scripts ) ) {
					return $this->unknown(
						$definition,
						__( 'Checkout scan completed but no payment scripts were detected on the homepage or checkout path.', 'complyops' ),
						$definition->recommended_value ?? __( 'Inventory third-party payment scripts and confirm PCI scope.', 'complyops' ),
					);
				}

				return $this->unknown(
					$definition,
					sprintf(
						/* translators: 1: payment script count, 2: comma-separated script URLs */
						__( 'Browser checkout scan found %1$d payment script(s): %2$s. Review PCI scope and script inventory.', 'complyops' ),
						$count,
						implode( ', ', array_slice( $scripts, 0, 5 ) )
					),
					$definition->recommended_value,
				);
			}
		}

		$integrations = $context->discovery['integrations'] ?? array();
		$payment      = array();

		foreach ( array( 'woocommerce', 'stripe', 'paypal', 'square' ) as $slug ) {
			if ( ! empty( $integrations[ $slug ]['detected'] ) ) {
				$payment[] = $slug;
			}
		}

		if ( array() === $payment ) {
			return $this->not_applicable(
				$definition,
				__( 'No supported payment or checkout integrations were detected.', 'complyops' ),
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: %s: comma-separated integration slugs */
				__( 'Payment-related integrations detected: %s. Run hosted browser verification for checkout script inventory.', 'complyops' ),
				implode( ', ', $payment )
			),
			$definition->recommended_value,
		);
	}

	public function accessibility_axe_scan( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->browser_verification_available() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'Automated WCAG scanning requires browser verification.', 'complyops' )
			);
		}

		$scan = $context->browser_scenario( 'accessibility_scan' );

		if ( ! is_array( $scan ) ) {
			return $this->unknown(
				$definition,
				__( 'Accessibility scan results were not available.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( isset( $scan['error'] ) && is_string( $scan['error'] ) ) {
			return $this->unknown(
				$definition,
				sprintf(
					/* translators: %s: browser error message */
					__( 'Accessibility scan failed: %s', 'complyops' ),
					$scan['error']
				),
				$definition->recommended_value,
			);
		}

		if ( empty( $scan['scan_available'] ) ) {
			return $this->unknown(
				$definition,
				__( 'Accessibility scan did not complete successfully.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		$critical = (int) ( $scan['violations']['critical'] ?? 0 );
		$serious  = (int) ( $scan['violations']['serious'] ?? 0 );
		$total    = (int) ( $scan['violation_total'] ?? ( $critical + $serious ) );

		if ( 0 === $critical && 0 === $serious ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %d: number of passing axe checks */
					__( 'Automated axe scan found no critical or serious WCAG violations (%d checks passed).', 'complyops' ),
					(int) ( $scan['passes'] ?? 0 )
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: 1: total violations, 2: critical count, 3: serious count */
				__( 'Automated axe scan found %1$d violation(s) (%2$d critical, %3$d serious). Manual review required.', 'complyops' ),
				$total,
				$critical,
				$serious
			),
			$definition->recommended_value ?? __( 'Remediate critical and serious accessibility findings.', 'complyops' ),
		);
	}

	public function opt_out_network_verified( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->browser_verification_available() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'Live opt-out network verification requires browser verification.', 'complyops' )
			);
		}

		$gpc = $context->browser_scenario( 'gpc_signal' );

		if ( ! is_array( $gpc ) ) {
			return $this->unknown(
				$definition,
				__( 'Global Privacy Control network verification results were not available.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( isset( $gpc['error'] ) && is_string( $gpc['error'] ) ) {
			return $this->unknown(
				$definition,
				sprintf(
					/* translators: %s: browser error message */
					__( 'Opt-out network verification failed: %s', 'complyops' ),
					$gpc['error']
				),
				$definition->recommended_value,
			);
		}

		$request_count = isset( $gpc['tracking_request_count'] ) ? (int) $gpc['tracking_request_count'] : null;
		$honored       = ! empty( $gpc['opt_out_honored'] ) || ! empty( $gpc['gpc_honored'] );

		if ( null !== $request_count && 0 === $request_count && $honored ) {
			return $this->pass(
				$definition,
				__( 'Browser verification detected no tracking network requests when Global Privacy Control was enabled.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( null !== $request_count && $request_count > 0 ) {
			$hosts = $gpc['tracking_request_hosts'] ?? array();
			$hosts = is_array( $hosts ) ? array_values( array_filter( $hosts, 'is_string' ) ) : array();

			return $this->unknown(
				$definition,
				sprintf(
					/* translators: 1: request count, 2: comma-separated hostnames */
					__( 'Global Privacy Control scan observed %1$d tracking request(s) from: %2$s.', 'complyops' ),
					$request_count,
					implode( ', ', array_slice( $hosts, 0, 5 ) )
				),
				$definition->recommended_value ?? __( 'Honor GPC and block non-essential tracking requests.', 'complyops' ),
			);
		}

		if ( $honored ) {
			return $this->pass(
				$definition,
				__( 'Browser verification indicates Global Privacy Control is honored.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Global Privacy Control and opt-out network behavior could not be confirmed.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function accessibility_statement_present( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$needles = array( 'accessibility statement', 'accessibility', 'wcag' );
		$found   = $this->site_contains_phrase( $needles );

		if ( $found ) {
			return $this->unknown(
				$definition,
				__( 'Accessibility-related content was found; verify it meets WCAG 2.2 AA expectations.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'No accessibility statement or WCAG reference was detected in published content.', 'complyops' ),
			$definition->recommended_value ?? __( 'Publish an accessibility statement describing conformance targets.', 'complyops' ),
		);
	}

	public function age_verification_review( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );

		return $this->unknown(
			$definition,
			__( 'Age verification and parental consent require manual review for sites directed to children.', 'complyops' ),
			$definition->recommended_value ?? __( 'Document age gates and parental consent workflows.', 'complyops' ),
		);
	}

	/**
	 * @param list<string> $needles
	 */
	private function site_contains_phrase( array $needles ): bool {
		$pages = get_posts(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'orderby'                => 'menu_order title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $pages as $page ) {
			$haystack = strtolower( $page->post_title . ' ' . wp_strip_all_tags( (string) $page->post_content ) );

			foreach ( $needles as $needle ) {
				if ( str_contains( $haystack, strtolower( $needle ) ) ) {
					return true;
				}
			}
		}

		$menus = wp_get_nav_menus();

		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( (int) $menu->term_id );

			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				$title = strtolower( (string) ( $item->title ?? '' ) );

				foreach ( $needles as $needle ) {
					if ( str_contains( $title, strtolower( $needle ) ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
