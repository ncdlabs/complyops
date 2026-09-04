<?php
/**
 * Plugin Name:       ComplyOps
 * Plugin URI:        https://ncdlabs.com/products/complyops/
 * Description:       Continuous technical compliance for WordPress: discover, enforce, monitor, remediate, verify, and report.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Author:            Lou Grossi
 * Author URI:        https://ncdlabs.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       complyops
 *
 * @package ComplyOps
 */

declare(strict_types=1);

namespace ComplyOps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COMPLYOPS_VERSION', '0.1.0' );
define( 'COMPLYOPS_PLUGIN_FILE', __FILE__ );
define( 'COMPLYOPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COMPLYOPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'COMPLYOPS_REST_NAMESPACE', 'complyops/v1' );

$complyops_autoload = COMPLYOPS_PLUGIN_DIR . 'vendor/autoload.php';

if ( is_readable( $complyops_autoload ) ) {
	require_once $complyops_autoload;
} else {
	// Allow development without Composer until vendor/ is installed.
	require_once COMPLYOPS_PLUGIN_DIR . 'src/Support/Autoloader.php';
	Support\Autoloader::register( COMPLYOPS_PLUGIN_DIR . 'src/' );
}

Plugin::instance()->boot();
