<?php

namespace ImportWPAddon\WooCommerce\Tests\Exporter\Mapper;

use ImportWPAddon\WooCommerce\Exporter\Mapper\ProductMapper;
use ImportWPAddon\WooCommerce\Tests\Utils\ProductTestTrait;
use ImportWPAddon\WooCommerce\Tests\Utils\ProtectedPropertyTrait;

/**
 * @group Exporter
 * @group Mapper
 * @group Product
 */
class ProductMapperTest extends \WP_UnitTestCase {

	use ProtectedPropertyTrait;
	use ProductTestTrait;

	public function tear_down() {
		$this->tearDownProductMocks();
		parent::tear_down();
	}

	public function test_get_fields_includes_woocommerce_groups() {
		$mapper = new ProductMapper( [ 'product', 'product_variation' ] );
		$fields = $mapper->get_fields();

		$this->assertSame( 'main', $fields['key'] );
		$this->assertContains( 'sku', $fields['fields'] );
		$this->assertArrayHasKey( 'woocommerce', $fields['children'] );
		$this->assertArrayHasKey( 'product_attributes', $fields['children'] );
		$this->assertArrayHasKey( 'linked_products', $fields['children'] );
		$this->assertArrayHasKey( 'product_gallery', $fields['children'] );
		$this->assertArrayHasKey( 'downloadable_files', $fields['children'] );
		$this->assertContains( 'regular_price', $fields['children']['woocommerce']['fields'] );
		$this->assertContains( 'sku', $fields['children']['parent']['fields'] );
	}

	public function test_setup_populates_woocommerce_and_sku() {
		$product = $this->mock_product( 'simple' );
		$product->set_name( 'Export Product' );
		$product->set_sku( 'export-sku-1' );
		$product->set_regular_price( '12.50' );
		$product->save();

		$mapper = new ProductMapper( [ 'product', 'product_variation' ] );
		$mapper->set_records( [ $product->get_id() ] );

		$this->assertTrue( $mapper->setup( 0 ) );
		$record = $mapper->record();

		$this->assertSame( 'export-sku-1', $record['sku'] );
		$this->assertSame( 'simple', $record['woocommerce']['product_type'] );
		$this->assertEquals( '12.50', $record['woocommerce']['regular_price'] );
	}

	public function test_remove_custom_fields_filters_wc_meta() {
		$mapper = new ProductMapper( [ 'product', 'product_variation' ] );
		$fields = $mapper->remove_custom_fields(
			[ '_sku', '_regular_price', 'my_custom_field', '_stock' ],
			[ 'product', 'product_variation' ]
		);

		$this->assertSame( [ 'my_custom_field' ], $fields );
	}
}
