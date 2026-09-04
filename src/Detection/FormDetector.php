<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects form plugins and published forms inventory.
 */
final class FormDetector {

	public function __construct(
		private readonly FormInventoryService $inventory = new FormInventoryService(),
	) {
	}

	/**
	 * @param list<string> $active_plugin_slugs
	 * @return array<string, mixed>
	 */
	public function detect( array $active_plugin_slugs ): array {
		$plugins = array();

		foreach ( Signatures::form_plugins() as $id => $definition ) {
			$active = $this->has_active_plugin( $active_plugin_slugs, $definition['plugin_slugs'] );
			$count  = 0;

			if ( $active ) {
				$count = $this->count_forms( $id );
			}

			$plugins[ $id ] = array(
				'detected'   => $active,
				'label'      => $definition['label'],
				'form_count' => $count,
			);
		}

		$detected  = array_filter(
			$plugins,
			static fn ( array $plugin ): bool => $plugin['detected']
		);
		$inventory = $this->inventory->inventory( $active_plugin_slugs );

		return array(
			'plugins'       => $plugins,
			'detected_any'  => array() !== $detected,
			'detected_ids'  => array_keys( $detected ),
			'total_forms'   => (int) ( $inventory['summary']['total_forms'] ?? array_sum( array_column( $detected, 'form_count' ) ) ),
			'inventory'     => $inventory,
		);
	}

	/**
	 * @param list<string> $active_plugin_slugs
	 * @param list<string> $plugin_slugs
	 */
	private function has_active_plugin( array $active_plugin_slugs, array $plugin_slugs ): bool {
		foreach ( $plugin_slugs as $slug ) {
			if ( in_array( $slug, $active_plugin_slugs, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function count_forms( string $plugin_id ): int {
		return match ( $plugin_id ) {
			'contact_form_7' => $this->count_posts( 'wpcf7_contact_form' ),
			'wpforms'        => $this->count_posts( 'wpforms' ),
			'gravity_forms'  => $this->count_posts( 'rg_form' ) > 0
				? $this->count_posts( 'rg_form' )
				: $this->count_gravity_forms(),
			'elementor_forms'=> $this->count_elementor_forms(),
			default          => 0,
		};
	}

	private function count_posts( string $post_type ): int {
		$counts = wp_count_posts( $post_type );

		if ( ! is_object( $counts ) ) {
			return 0;
		}

		return (int) ( $counts->publish ?? 0 ) + (int) ( $counts->draft ?? 0 );
	}

	private function count_gravity_forms(): int {
		global $wpdb;

		$table = $wpdb->prefix . 'gf_form';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $exists !== $table ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i',
				$table
			)
		);
	}

	private function count_elementor_forms(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE '%\"widgetType\":\"form\"%'"
		);
	}
}
