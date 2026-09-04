<?php

declare(strict_types=1);

namespace ComplyOps\Admin;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Security\Capabilities;

/**
 * Registers ComplyOps admin menu pages and the right-side navigation rail.
 */
final class AdminMenu {

	public const EXIT_DASHBOARD_ACTION = 'complyops_exit_dashboard';

	/** @var array<string, string> */
	private const PAGES = array(
		'complyops'              => 'dashboard',
		'complyops-audit'        => 'audit',
		'complyops-controls'     => 'controls',
		'complyops-integrations' => 'integrations',
		'complyops-consent'      => 'consent',
		'complyops-evidence'     => 'evidence',
		'complyops-reports'      => 'reports',
	);

	public const HIDDEN_ITEM_CLASS = 'complyops-hidden-menu-item';

	private const ASSET_CSS_HIDE = 'assets/admin-menu/admin-menu-hide.css';

	private const ASSET_CSS = 'assets/admin-menu/admin-menu.css';

	private const ASSET_JS = 'assets/admin-menu/admin-menu.js';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_menu', array( $this, 'hide_submenu_rows' ), 999 );
		add_action( 'admin_init', array( $this, 'redirect_legacy_status_page' ), 1 );
		add_action( 'admin_init', array( $this, 'redirect_legacy_audit_tabs' ), 1 );
		add_action( 'admin_init', array( $this, 'handle_wordpress_dashboard_exit' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_menu_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'admin_footer', array( $this, 'render_right_admin_menu' ) );
	}

	public function add_menus(): void {
		add_menu_page(
			__( 'ComplyOps', 'complyops' ),
			__( 'ComplyOps', 'complyops' ),
			Capabilities::VIEW,
			'complyops',
			array( $this, 'render_page' ),
			'dashicons-shield-alt',
			58
		);

		add_submenu_page(
			'complyops',
			__( 'Dashboard', 'complyops' ),
			__( 'Dashboard', 'complyops' ),
			Capabilities::VIEW,
			'complyops',
			array( $this, 'render_page' )
		);

		foreach ( self::menu_labels() as $slug => $label ) {
			if ( 'complyops' === $slug ) {
				continue;
			}

			add_submenu_page(
				'complyops',
				$label,
				$label,
				Capabilities::VIEW,
				$slug,
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * Hide registered submenu rows; navigation lives in the right-side menu.
	 */
	public function hide_submenu_rows(): void {
		global $submenu;

		$parent = 'complyops';
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}

		$organized = array();
		foreach ( $submenu[ $parent ] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$item[4]     = trim( ( (string) ( $item[4] ?? '' ) ) . ' ' . self::HIDDEN_ITEM_CLASS );
			$organized[] = $item;
		}

		$submenu[ $parent ] = $organized; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering this plugin's own submenu is the purpose of this callback.
	}

	public function render_page(): void {
		if ( ! current_user_can( Capabilities::VIEW ) ) {
			wp_die( esc_html__( 'You do not have permission to access ComplyOps.', 'complyops' ) );
		}

		echo '<div id="complyops-admin-root" class="complyops-admin-wrap"></div>';
	}

	public static function current_page(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : 'complyops';

		return self::PAGES[ $slug ] ?? 'dashboard';
	}

	/**
	 * Slug used to highlight the active right-menu item.
	 */
	public static function current_nav_slug(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : 'complyops';

		return '' !== $page ? $page : 'complyops';
	}

	/**
	 * @return list<string>
	 */
	public static function page_slugs(): array {
		return array_keys( self::PAGES );
	}

	/**
	 * @return array<int, array{slug: string, label: string, href: string, icon: string, section: string}>
	 */
	public static function menu_items(): array {
		$items = array();
		foreach ( self::menu_labels() as $slug => $label ) {
			$items[] = array(
				'slug'  => $slug,
				'label' => $label,
				'href'  => admin_url( 'admin.php?page=' . $slug ),
				'icon'  => self::menu_icon( $slug ),
				'section' => self::menu_section( $slug ),
			);
		}

		return $items;
	}

	/**
	 * Whether the current admin request belongs to ComplyOps.
	 */
	public static function is_complyops_admin_screen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( '' !== $page && isset( self::PAGES[ $page ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Redirect the removed Status screen to Dashboard.
	 */
	public function redirect_legacy_status_page(): void {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( 'complyops-status' !== $page ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=complyops' ) );
		exit;
	}

	/**
	 * Redirect removed Findings and History screens to the Audits hub tabs.
	 */
	public function redirect_legacy_audit_tabs(): void {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		$tabs = array(
			'complyops-findings' => 'findings',
			'complyops-history'  => 'history',
		);

		if ( ! isset( $tabs[ $page ] ) ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'complyops-audit',
					'tab'  => $tabs[ $page ],
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Restore the core wp-admin menu after leaving ComplyOps for the dashboard.
	 */
	public function handle_wordpress_dashboard_exit(): void {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below with check_admin_referer.
		$exit = isset( $_GET['complyops_exit'] ) ? sanitize_key( wp_unslash( (string) $_GET['complyops_exit'] ) ) : '';
		if ( '1' !== $exit ) {
			return;
		}

		check_admin_referer( self::EXIT_DASHBOARD_ACTION );

		global $pagenow;
		if ( 'index.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( '' !== $page ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 || ! current_user_can( Capabilities::VIEW ) ) {
			return;
		}

		// Fold is applied only via admin body class on ComplyOps screens; do not persist core mfold.
		wp_safe_redirect( remove_query_arg( array( 'complyops_exit', '_wpnonce' ) ) );
		exit;
	}

	public function enqueue_admin_menu_assets(): void {
		if ( ! current_user_can( Capabilities::VIEW ) ) {
			return;
		}

		// Hide duplicate left-menu rows on every admin screen; full chrome only on ComplyOps pages.
		$hide_path = COMPLYOPS_PLUGIN_DIR . self::ASSET_CSS_HIDE;
		if ( is_readable( $hide_path ) ) {
			wp_enqueue_style(
				'complyops-admin-menu-hide',
				COMPLYOPS_PLUGIN_URL . self::ASSET_CSS_HIDE,
				array(),
				COMPLYOPS_VERSION . '.' . (string) filemtime( $hide_path )
			);
		}

		if ( ! self::is_complyops_admin_screen() ) {
			return;
		}

		$css_path = COMPLYOPS_PLUGIN_DIR . self::ASSET_CSS;
		if ( is_readable( $css_path ) ) {
			wp_enqueue_style(
				'complyops-admin-menu',
				COMPLYOPS_PLUGIN_URL . self::ASSET_CSS,
				array( 'complyops-admin-menu-hide' ),
				COMPLYOPS_VERSION . '.' . (string) filemtime( $css_path )
			);
		}

		$js_path = COMPLYOPS_PLUGIN_DIR . self::ASSET_JS;
		if ( ! is_readable( $js_path ) ) {
			return;
		}

		wp_enqueue_script(
			'complyops-admin-menu',
			COMPLYOPS_PLUGIN_URL . self::ASSET_JS,
			array(),
			COMPLYOPS_VERSION . '.' . (string) filemtime( $js_path ),
			true
		);
	}

	/**
	 * Collapse the core wp-admin menu and reserve space for the right plugin menu.
	 *
	 * @param string $classes Space-separated admin body classes.
	 */
	public function admin_body_class( string $classes ): string {
		if ( ! self::is_complyops_admin_screen() ) {
			return $classes;
		}

		if ( ! str_contains( ' ' . $classes . ' ', ' folded ' ) ) {
			$classes = trim( $classes . ' folded' );
		}

		return trim( $classes . ' complyops-admin-screen complyops-right-menu-expanded' );
	}

	/**
	 * Render the collapsible right-side plugin navigation.
	 */
	public function render_right_admin_menu(): void {
		if ( ! current_user_can( Capabilities::VIEW ) || ! self::is_complyops_admin_screen() ) {
			return;
		}

		$items        = self::menu_items();
		$current_slug = self::current_nav_slug();

		echo '<button type="button" id="complyops-right-menu-backdrop" class="complyops-right-menu__backdrop" aria-label="' . esc_attr__( 'Close ComplyOps menu', 'complyops' ) . '"></button>';
		echo '<nav id="complyops-right-menu" class="complyops-right-menu" aria-label="' . esc_attr__( 'ComplyOps', 'complyops' ) . '">';
		echo '<div id="complyops-right-menu-panel" class="complyops-right-menu__inner">';
		echo '<div class="complyops-right-menu__body">';
		echo '<p class="complyops-right-menu__brand">';
		echo '<img src="' . esc_url( COMPLYOPS_PLUGIN_URL . 'assets/brand/complyops-mark.svg' ) . '" alt="" aria-hidden="true">';
		echo '<span>Comply<span>Ops</span></span>';
		echo '</p>';
		echo '<ul class="complyops-right-menu__list">';

		$section = '';
		foreach ( $items as $item ) {
			$slug    = (string) $item['slug'];
			$item_section = (string) $item['section'];
			if ( $item_section !== $section ) {
				$section = $item_section;
				echo '<li class="complyops-right-menu__section" data-complyops-nav-section="' . esc_attr( $section ) . '">' . esc_html( strtoupper( $section ) ) . '</li>';
			}
			$current = $current_slug === $slug;
			echo '<li class="complyops-right-menu__item">';
			$this->render_right_menu_link(
				(string) $item['label'],
				(string) $item['href'],
				$current,
				(string) $item['icon'],
				'',
				$slug
			);
			echo '</li>';
		}

		echo '</ul></div>';
		echo '<div class="complyops-right-menu__footer">';
		echo '<button type="button" id="complyops-right-menu-toggle" class="complyops-right-menu__toggle" aria-expanded="true" aria-controls="complyops-right-menu-panel"';
		echo ' title="' . esc_attr__( 'Collapse menu', 'complyops' ) . '"';
		echo ' data-collapse-label="' . esc_attr__( 'Collapse menu', 'complyops' ) . '"';
		echo ' data-expand-label="' . esc_attr__( 'Expand menu', 'complyops' ) . '">';
		echo '<span class="collapse-button-icon" aria-hidden="true"></span>';
		echo '<span class="complyops-right-menu__toggle-label">' . esc_html__( 'Collapse menu', 'complyops' ) . '</span>';
		echo '</button>';
		$this->render_right_menu_link(
			__( 'Exit to WordPress', 'complyops' ),
			$this->wordpress_dashboard_exit_url(),
			false,
			'dashicons dashicons-wordpress',
			'complyops-right-menu__exit'
		);
		echo '</div></div></nav>';
	}

	private function render_right_menu_link( string $label, string $url, bool $current, string $icon, string $extra_class = '', string $nav_slug = '' ): void {
		$classes = trim( 'complyops-right-menu__link ' . $extra_class );
		if ( $current ) {
			$classes .= ' is-current';
		}

		echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $url ) . '" title="' . esc_attr( $label ) . '"';
		if ( '' !== $nav_slug ) {
			echo ' data-complyops-nav="' . esc_attr( $nav_slug ) . '"';
		}
		if ( $current ) {
			echo ' aria-current="page"';
		}
		echo '>';
		if ( '' !== $icon ) {
			echo '<span class="complyops-right-menu__icon ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
		}
		echo '<span class="complyops-right-menu__label">' . esc_html( $label ) . '</span>';
		echo '</a>';
	}

	private function wordpress_dashboard_exit_url(): string {
		$user_id = get_current_user_id();
		$url     = $user_id > 0 ? get_dashboard_url( $user_id ) : admin_url( 'index.php' );
		$url     = add_query_arg( 'complyops_exit', '1', $url );

		return wp_nonce_url( $url, self::EXIT_DASHBOARD_ACTION );
	}

	/**
	 * @return array<string, string>
	 */
	private static function menu_labels(): array {
		return array(
			'complyops'              => __( 'Dashboard', 'complyops' ),
			'complyops-controls'     => __( 'Controls', 'complyops' ),
			'complyops-integrations' => __( 'Integrations', 'complyops' ),
			'complyops-consent'      => __( 'Consent', 'complyops' ),
			'complyops-evidence'     => __( 'Evidence', 'complyops' ),
			'complyops-audit'        => __( 'Audits', 'complyops' ),
			'complyops-reports'      => __( 'Reports', 'complyops' ),
		);
	}

	private static function menu_icon( string $slug ): string {
		$icons = array(
			'complyops'              => 'dashicons dashicons-dashboard',
			'complyops-audit'        => 'dashicons dashicons-search',
			'complyops-controls'     => 'dashicons dashicons-list-view',
			'complyops-integrations' => 'dashicons dashicons-admin-plugins',
			'complyops-consent'      => 'dashicons dashicons-privacy',
			'complyops-evidence'     => 'dashicons dashicons-media-document',
			'complyops-reports'      => 'dashicons dashicons-media-spreadsheet',
		);

		return $icons[ $slug ] ?? 'dashicons dashicons-shield-alt';
	}

	private static function menu_section( string $slug ): string {
		if ( 'complyops' === $slug ) {
			return 'monitor';
		}
		if ( in_array( $slug, array( 'complyops-controls', 'complyops-integrations', 'complyops-consent', 'complyops-evidence' ), true ) ) {
			return 'manage';
		}
		if ( in_array( $slug, array( 'complyops-audit', 'complyops-reports' ), true ) ) {
			return 'assure';
		}

		return 'monitor';
	}
}
