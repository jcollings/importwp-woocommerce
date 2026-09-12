<?php

namespace ImportWPAddon\WooCommerce\Tests\Bootstrap;

/**
 * Smoke tests that the shared iwp-dev stack loaded correctly.
 *
 * @group Bootstrap
 */
class DependenciesTest extends \WP_UnitTestCase {

	public function test_woocommerce_is_available() {
		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce should be loaded by the test bootstrap.' );
		$this->assertTrue( function_exists( 'wc_get_product_types' ) );
	}

	public function test_importwp_core_is_available() {
		$this->assertTrue( defined( 'IWP_VERSION' ) );
		$this->assertTrue( function_exists( 'import_wp' ) || function_exists( 'import_wp_pro' ) );
		$this->assertTrue( class_exists( '\ImportWP\Container' ) );
	}

	public function test_woocommerce_addon_is_loaded() {
		$this->assertTrue( function_exists( 'iwp_woocommerce_setup' ) );
		$this->assertTrue( class_exists( '\ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate' ) );
		$this->assertTrue( class_exists( '\ImportWPAddon\WooCommerce\Importer\Mapper\ProductMapper' ) );
		$this->assertTrue( class_exists( '\ImportWPAddon\WooCommerce\Importer\Template\CustomerTemplate' ) );
		$this->assertTrue( class_exists( '\ImportWPAddon\WooCommerce\Importer\Mapper\CustomerMapper' ) );
		$this->assertTrue( class_exists( '\ImportWPAddon\WooCommerce\Importer\Template\OrderTemplate' ) );
		$this->assertTrue( class_exists( '\ImportWPAddon\WooCommerce\Importer\Mapper\OrderMapper' ) );
	}
}
