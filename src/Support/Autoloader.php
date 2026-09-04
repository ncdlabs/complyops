<?php

declare(strict_types=1);

namespace ComplyOps\Support;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal PSR-4 autoloader for development without Composer.
 */
final class Autoloader {

	/**
	 * @var string
	 */
	private static string $base_dir = '';

	public static function register( string $base_dir ): void {
		self::$base_dir = rtrim( $base_dir, '/' ) . '/';
		spl_autoload_register( array( self::class, 'load' ) );
	}

	public static function load( string $class ): void {
		$prefix = 'ComplyOps\\';

		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = self::$base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
