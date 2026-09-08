<?php

namespace ImportWPAddon\WooCommerce\Tests\Importer\Mapper;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\Template\Template;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Importer\Mapper\ProductMapper;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;
use ImportWPAddon\WooCommerce\Tests\Utils\ProductTestTrait;
use ImportWPAddon\WooCommerce\Tests\Utils\ProtectedPropertyTrait;

/**
 * @group Importer
 * @group Mapper
 */
class ProductMapperTest extends \WP_UnitTestCase {

	use ProtectedPropertyTrait;
	use ProductTestTrait;

	public function tear_down() {
		$this->tearDownProductMocks();
		parent::tear_down();
	}

	/**
	 * @return ProductMapper
	 */
	private function make_mapper() {
		$importer = $this->createMock( ImporterModel::class );
		$importer->method( 'getSetting' )->willReturnCallback(
			function ( $key ) {
				return 'post_type' === $key ? [ 'product', 'product_variation' ] : null;
			}
		);

		$template = new ProductTemplate( new EventHandler() );

		return new ProductMapper( $importer, $template );
	}

	/**
	 * @return ParsedData|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function mock_create_data( array $post_values ) {
		$parsed_data = $this->createMock( ParsedData::class );
		$parsed_data->method( 'permission' )->willReturn( null );
		$parsed_data->method( 'getValue' )->willReturnCallback(
			function ( $field, $group = null ) use ( $post_values ) {
				if ( 'post' !== $group ) {
					return null;
				}
				$key = preg_replace( '/^post\./', '', $field );
				return array_key_exists( $key, $post_values ) ? $post_values[ $key ] : null;
			}
		);

		return $parsed_data;
	}

	public function test_create_simple_product() {
		$mapper = $this->make_mapper();
		$data   = $this->mock_create_data(
			[
				'product_type' => 'simple',
				'post_title'   => 'Simple Widget',
				'post_content' => 'A simple product',
				'post_excerpt' => 'Short copy',
				'post_status'  => 'publish',
				'post_name'    => 'simple-widget',
			]
		);

		$product_id = $mapper->create_post( [], $data );
		$this->assertTrue( is_int( $product_id ) );

		$product = wc_get_product( $product_id );
		$this->assertInstanceOf( \WC_Product_Simple::class, $product );
		$this->assertEquals( 'Simple Widget', $product->get_name() );
		$this->assertEquals( 'A simple product', $product->get_description() );
		$this->assertEquals( 'Short copy', $product->get_short_description() );
		$this->assertEquals( 'publish', $product->get_status() );
		$this->assertEquals( 'simple-widget', $product->get_slug() );

		$this->mock_products[] = $product;
	}

	public function test_create_variable_product() {
		$mapper = $this->make_mapper();
		$data   = $this->mock_create_data(
			[
				'product_type' => 'variable',
				'post_title'   => 'Variable Shirt',
				'post_status'  => 'publish',
			]
		);

		$product_id = $mapper->create_post( [], $data );
		$product    = wc_get_product( $product_id );

		$this->assertInstanceOf( \WC_Product_Variable::class, $product );
		$this->assertEquals( 'Variable Shirt', $product->get_name() );

		$this->mock_products[] = $product;
	}

	public function test_create_product_with_comma_separated_types_picks_first_valid() {
		$mapper = $this->make_mapper();
		$data   = $this->mock_create_data(
			[
				'product_type' => 'not-a-type, variable, simple',
				'post_title'   => 'Typed Product',
				'post_status'  => 'publish',
			]
		);

		$product_id = $mapper->create_post( [], $data );
		$product    = wc_get_product( $product_id );

		$this->assertInstanceOf( \WC_Product_Variable::class, $product );
		$this->mock_products[] = $product;
	}

	public function test_get_product_object_rejects_invalid_type() {
		$mapper = $this->make_mapper();
		$result = $mapper->get_product_object( [ 'type' => 'not-real' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'woocommerce_product_importer_invalid_type', $result->get_error_code() );
	}
}
