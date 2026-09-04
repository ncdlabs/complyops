<?php

declare(strict_types=1);

namespace ComplyOps\PublicStatus;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Security\Capabilities;

/**
 * Renders the optional public HTML compliance status page.
 */
final class PublicStatusPage {

	public const QUERY_VAR = 'complyops_public_status';

	public function register(): void {
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
	}

	public function register_rewrite(): void {
		add_rewrite_rule(
			'^compliance-status/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * @param list<string> $vars
	 * @return list<string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public function maybe_render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public page; no state change.
		if ( '1' !== get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$settings   = new PublicStatusSettings();
		$is_preview = $this->is_authorized_preview();

		if ( ! $settings->page_enabled() && ! $is_preview ) {
			status_header( 404 );
			nocache_headers();
			wp_die(
				esc_html__( 'Compliance status is not published.', 'complyops' ),
				esc_html__( 'Not found', 'complyops' ),
				array( 'response' => 404 )
			);
		}

		$payload = ( new PublicStatusService( $settings ) )->payload( $is_preview );

		if ( null === $payload || array() === $payload ) {
			status_header( 503 );
			nocache_headers();
			wp_die(
				esc_html__( 'Compliance status is temporarily unavailable.', 'complyops' ),
				esc_html__( 'Unavailable', 'complyops' ),
				array( 'response' => 503 )
			);
		}

		nocache_headers();
		status_header( 200 );

		$title = sanitize_text_field( (string) ( $settings->all()['page_title'] ?? __( 'Compliance Status', 'complyops' ) ) );

		$this->render_html( $title, $payload );
		exit;
	}

	public static function public_url(): string {
		return home_url( '/compliance-status/' );
	}

	public static function preview_url(): string {
		return add_query_arg(
			array(
				'complyops_preview' => '1',
				'_wpnonce'          => wp_create_nonce( 'complyops_public_status_preview' ),
			),
			self::public_url()
		);
	}

	private function is_authorized_preview(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified immediately below for an administrator-only preview.
		$is_preview = isset( $_GET['complyops_preview'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['complyops_preview'] ) );

		if ( ! $is_preview || ! current_user_can( Capabilities::MANAGE ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is explicitly verified here.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		return 1 === wp_verify_nonce( $nonce, 'complyops_public_status_preview' );
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function render_html( string $title, array $payload ): void {
		$labels = array(
			'framework'         => __( 'Framework', 'complyops' ),
			'technical_status'  => __( 'Technical controls', 'complyops' ),
			'score'             => __( 'Technical readiness', 'complyops' ),
			'monitoring'        => __( 'Monitoring', 'complyops' ),
			'last_verified'     => __( 'Last verified', 'complyops' ),
			'critical_findings' => __( 'Critical findings', 'complyops' ),
			'high_findings'     => __( 'High findings', 'complyops' ),
		);

		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #f6f7f7; color: #1d2327; }
		main { max-width: 640px; margin: 48px auto; padding: 0 16px; }
		.card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 24px; }
		h1 { margin: 0 0 8px; font-size: 1.5rem; }
		.disclaimer { margin: 0 0 24px; color: #50575e; font-size: 0.875rem; line-height: 1.5; }
		dl { margin: 0; display: grid; gap: 12px; }
		.row { display: flex; justify-content: space-between; gap: 16px; border-bottom: 1px solid #f0f0f1; padding-bottom: 12px; }
		.row:last-child { border-bottom: 0; padding-bottom: 0; }
		dt { margin: 0; color: #50575e; }
		dd { margin: 0; font-weight: 600; text-align: right; }
	</style>
</head>
<body>
<main>
	<div class="card">
		<h1><?php echo esc_html( $title ); ?></h1>
		<p class="disclaimer"><?php echo esc_html__( 'This page shows intentionally published technical control posture only. It is not legal certification or legal advice.', 'complyops' ); ?></p>
		<dl>
			<?php foreach ( $payload as $key => $value ) : ?>
				<div class="row">
					<dt><?php echo esc_html( $labels[ $key ] ?? ucwords( str_replace( '_', ' ', (string) $key ) ) ); ?></dt>
					<dd><?php echo esc_html( $this->format_value( $key, $value ) ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</div>
</main>
</body>
</html>
		<?php
	}

	private function format_value( string $key, mixed $value ): string {
		if ( null === $value || '' === $value ) {
			return '—';
		}

		if ( 'score' === $key && is_numeric( $value ) ) {
			return sprintf(
				/* translators: %d: readiness score */
				__( '%d%%', 'complyops' ),
				(int) $value
			);
		}

		if ( 'technical_status' === $key ) {
			return 'passing' === $value
				? __( 'Passing', 'complyops' )
				: __( 'Attention required', 'complyops' );
		}

		if ( 'monitoring' === $key ) {
			return 'active' === $value
				? __( 'Active', 'complyops' )
				: __( 'Inactive', 'complyops' );
		}

		if ( 'last_verified' === $key && is_string( $value ) ) {
			$unix = strtotime( $value );

			if ( false !== $unix ) {
				return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $unix );
			}
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
