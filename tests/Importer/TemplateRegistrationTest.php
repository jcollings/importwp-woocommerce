<?php

namespace ImportWPAddon\WooCommerce\Tests\Importer;

use ImportWP\Container;
use ImportWPAddon\WooCommerce\Importer\Mapper\ProductMapper;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;

/**
 * Example integration tests for addon registration against ImportWP.
 *
 * @group Importer
 */
class TemplateRegistrationTest extends \WP_UnitTestCase {

	public function test_woocommerce_product_template_is_registered() {
		$manager   = Container::getInstance()->get( 'importer_manager' );
		$templates = $manager->get_templates();

		$this->assertArrayHasKey( 'woocommerce-product', $templates );
		$this->assertSame( ProductTemplate::class, $templates['woocommerce-product'] );
	}

	public function test_woocommerce_product_mapper_is_registered() {
		$manager = Container::getInstance()->get( 'importer_manager' );
		$mappers = $manager->get_mappers();

		$this->assertArrayHasKey( 'woocommerce-product', $mappers );
		$this->assertSame( ProductMapper::class, $mappers['woocommerce-product'] );
	}

	public function test_unique_fields_for_woocommerce_product_mapper() {
		$fields = apply_filters( 'iwp/mapper/unique_fields', [ 'ID' ], 'woocommerce-product' );

		$this->assertSame( [ 'ID', '_sku', 'post_name' ], $fields );
	}

	public function test_unique_fields_unchanged_for_other_mappers() {
		$fields = apply_filters( 'iwp/mapper/unique_fields', [ 'ID', 'post_name' ], 'post' );

		$this->assertSame( [ 'ID', 'post_name' ], $fields );
	}

	public function test_woocommerce_is_on_compat_whitelist() {
		$plugins = apply_filters( 'iwp/compat/whitelist', [] );

		$this->assertContains( 'woocommerce/woocommerce.php', $plugins );
	}
}
