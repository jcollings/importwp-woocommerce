<?php

/**
 * PHPUnit bootstrap for Import WP WooCommerce addon.
 *
 * Expects sibling plugins mounted by iwp-dev wp-env:
 * - importwp
 * - importwp-pro (optional)
 * - woocommerce
 *
 * @package ImportWPAddon\WooCommerce
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you started iwp-dev wp-env?" . PHP_EOL;
	exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

define( 'IWP_WC_TEST_ROOT', __DIR__ );
define( 'IWP_WC_PLUGIN_ROOT', dirname( __DIR__ ) );
define( 'IWP_WC_PLUGINS_DIR', dirname( IWP_WC_PLUGIN_ROOT ) );

$autoload = IWP_WC_PLUGIN_ROOT . '/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	echo "Composer dependencies missing. From iwp-dev run: npm run test:woocommerce:install" . PHP_EOL;
	exit( 1 );
}
require_once $autoload;

if ( ! getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	putenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH=' . IWP_WC_PLUGIN_ROOT . '/vendor/yoast/phpunit-polyfills' );
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', IWP_WC_PLUGIN_ROOT . '/vendor/yoast/phpunit-polyfills' );
}

require_once __DIR__ . '/autoload.php';

/**
 * Resolve a sibling plugin main file under wp-content/plugins.
 *
 * Prefers an exact directory name, then a directory whose name starts with the
 * preferred name (wp-env names zip installs after the zip filename, e.g.
 * woocommerce.latest-stable). Never returns this addon's own main file when
 * searching for WooCommerce core.
 *
 * @param string      $main_file Main PHP file inside the plugin directory.
 * @param string|null $preferred_directory Preferred plugin directory name.
 * @return string
 */
function iwp_wc_tests_plugin_file( $main_file, $preferred_directory = null ) {
	if ( $preferred_directory ) {
		$path = IWP_WC_PLUGINS_DIR . '/' . $preferred_directory . '/' . $main_file;
		if ( file_exists( $path ) ) {
			return $path;
		}
	}

	$matches = glob( IWP_WC_PLUGINS_DIR . '/*/' . $main_file );
	if ( empty( $matches ) ) {
		$hint = $preferred_directory ? IWP_WC_PLUGINS_DIR . '/' . $preferred_directory . '/' . $main_file : $main_file;
		echo "Required plugin not found: {$hint}" . PHP_EOL;
		echo 'Start iwp-dev with: npm run test:start' . PHP_EOL;
		exit( 1 );
	}

	$candidates = [];
	foreach ( $matches as $path ) {
		$dir = basename( dirname( $path ) );
		if ( 'importwp-woocommerce' === $dir ) {
			continue;
		}
		$candidates[] = [ 'dir' => $dir, 'path' => $path ];
	}

	if ( $preferred_directory ) {
		foreach ( $candidates as $candidate ) {
			if ( 0 === strpos( $candidate['dir'], $preferred_directory ) ) {
				return $candidate['path'];
			}
		}
	}

	if ( ! empty( $candidates ) ) {
		return $candidates[0]['path'];
	}

	$hint = $preferred_directory ? IWP_WC_PLUGINS_DIR . '/' . $preferred_directory . '/' . $main_file : $main_file;
	echo "Required plugin not found: {$hint}" . PHP_EOL;
	echo 'Start iwp-dev with: npm run test:start' . PHP_EOL;
	exit( 1 );
}

/**
 * Manually load WooCommerce, ImportWP, optional Pro, then this addon.
 */
function _manually_load_iwp_woocommerce_plugins() {
	require iwp_wc_tests_plugin_file( 'woocommerce.php', 'woocommerce' );
	require iwp_wc_tests_plugin_file( 'jc-importer.php', 'importwp' );

	$pro = IWP_WC_PLUGINS_DIR . '/importwp-pro/importwp-pro.php';
	if ( file_exists( $pro ) ) {
		require $pro;
	}

	require IWP_WC_PLUGIN_ROOT . '/woocommerce.php';

	if ( class_exists( '\ImportWP\Common\Migration\Migrations' ) ) {
		$migration = new \ImportWP\Common\Migration\Migrations();
		$migration->migrate();
	}
}

tests_add_filter( 'muplugins_loaded', '_manually_load_iwp_woocommerce_plugins' );

require "{$_tests_dir}/includes/bootstrap.php";
