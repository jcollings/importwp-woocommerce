<?php

namespace ImportWPAddon\WooCommerce\Tests\Importer\Template;

use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;
use ImportWPAddon\WooCommerce\Tests\Utils\ProductTestTrait;
use ImportWPAddon\WooCommerce\Tests\Utils\ProtectedPropertyTrait;

/**
 * Ported / extended from the historical ProductTemplateTest suite.
 *
 * @group Importer
 * @group Template
 */
class ProductTemplateTest extends \WP_UnitTestCase {

	use ProtectedPropertyTrait;
	use ProductTestTrait;

	public function set_up() {
		parent::set_up();
	}

	public function tear_down() {
		$this->tearDownProductMocks();
		parent::tear_down();
	}

	public function test_set_variation_data_custom_attribute() {
		$parent = $this->mock_product( 'variable' );
		$parent->set_sku( 'ASD123' );
		$parent->save();

		$variation = $this->mock_product( 'variation' );

		$parsed_data = $this->mock_parsed_data(
			[
				'attributes._index'         => 3,
				'attributes.0.name'         => 'Color',
				'attributes.0.terms'        => 'red',
				'attributes.0.global'       => 'no',
				'attributes.0.visible'      => 'yes',
				'attributes.0.variation'    => 'yes',
				'attributes.1.name'         => 'Size',
				'attributes.1.terms'        => 'sm',
				'attributes.1.global'       => 'no',
				'attributes.1.visible'      => 'no',
				'attributes.1.variation'    => '',
				'attributes.2.name'         => 'Shape',
				'attributes.2.terms'        => 'square, circle',
				'attributes.2.global'       => 'no',
				'attributes.2.visible'      => 'yes',
				'attributes.2.variation'    => 'no',
			],
			[
				'post_parent|advanced' => $parent->get_id(),
			]
		);

		$product_template = new ProductTemplate( new EventHandler() );
		$product_template->set_variation_data( $variation, $parsed_data );

		$variation->set_price( 10 );
		$variation->save();

		$final_parent = wc_get_product( $parent->get_id() );
		$final_child  = wc_get_product( $variation->get_id() );

		$this->assertEquals( $final_parent->get_id(), $final_child->get_parent_id() );

		$parent_attributes = $final_parent->get_attributes();

		$this->assertEquals( 'Color', $parent_attributes['color']->get_name() );
		$this->assertEquals( [ 'red' ], $parent_attributes['color']->get_options() );
		$this->assertFalse( $parent_attributes['color']->is_taxonomy() );
		$this->assertTrue( $parent_attributes['color']->get_variation() );
		$this->assertTrue( $parent_attributes['color']->get_visible() );

		$this->assertEquals( 'Size', $parent_attributes['size']->get_name() );
		$this->assertEquals( [ 'sm' ], $parent_attributes['size']->get_options() );
		$this->assertFalse( $parent_attributes['size']->is_taxonomy() );
		$this->assertTrue( $parent_attributes['size']->get_variation() );
		$this->assertFalse( $parent_attributes['size']->get_visible() );

		$this->assertArrayNotHasKey( 'shape', $parent_attributes );
		$this->assertCount( 2, $parent_attributes );

		$variation_attributes = $final_parent->get_variation_attributes();
		$this->assertEquals( [ 'red' ], $variation_attributes['Color'] );
		$this->assertEquals( [ 'sm' ], $variation_attributes['Size'] );
		$this->assertCount( 2, $variation_attributes );
	}

	public function test_set_variation_data_global_attribute() {
		$parent = $this->mock_product( 'variable' );
		$parent->set_sku( 'ASD123' );
		$parent->save();

		$variation = $this->mock_product( 'variation' );

		$parsed_data = $this->mock_parsed_data(
			[
				'attributes._index'         => 3,
				'attributes.0.name'         => 'Color',
				'attributes.0.terms'        => 'red',
				'attributes.0.global'       => 'yes',
				'attributes.0.visible'      => 'yes',
				'attributes.0.variation'    => 'yes',
				'attributes.1.name'         => 'Size',
				'attributes.1.terms'        => 'sm',
				'attributes.1.global'       => 'yes',
				'attributes.1.visible'      => 'no',
				'attributes.1.variation'    => '',
				'attributes.2.name'         => 'Shape',
				'attributes.2.terms'        => 'square, circle',
				'attributes.2.global'       => 'yes',
				'attributes.2.visible'      => 'yes',
				'attributes.2.variation'    => 'no',
			],
			[
				'post_parent|advanced' => $parent->get_id(),
			]
		);

		$product_template = new ProductTemplate( new EventHandler() );
		$product_template->set_variation_data( $variation, $parsed_data );

		$variation->set_price( 10 );
		$variation->save();

		$final_parent = wc_get_product( $parent->get_id() );
		$final_child  = wc_get_product( $variation->get_id() );

		$this->assertEquals( $final_parent->get_id(), $final_child->get_parent_id() );

		$parent_attributes = $final_parent->get_attributes();

		$this->assertEquals( 'pa_color', $parent_attributes['pa_color']->get_name() );
		$this->assertGreaterThan( 0, $parent_attributes['pa_color']->get_options()[0] );
		$this->assertTrue( $parent_attributes['pa_color']->is_taxonomy() );
		$this->assertTrue( $parent_attributes['pa_color']->get_variation() );

		$this->assertEquals( 'pa_size', $parent_attributes['pa_size']->get_name() );
		$this->assertGreaterThan( 0, $parent_attributes['pa_size']->get_options()[0] );
		$this->assertTrue( $parent_attributes['pa_size']->is_taxonomy() );
		$this->assertTrue( $parent_attributes['pa_size']->get_variation() );

		$this->assertArrayNotHasKey( 'pa_shape', $parent_attributes );
		$this->assertCount( 2, $parent_attributes );

		$variation_attributes = $final_parent->get_variation_attributes();
		$this->assertEquals( [ 'red' ], $variation_attributes['pa_color'] );
		$this->assertEquals( [ 'sm' ], $variation_attributes['pa_size'] );
		$this->assertCount( 2, $variation_attributes );
	}

	public function test_set_variation_data_throws_without_parent_on_insert() {
		$variation   = $this->mock_product( 'variation' );
		$parsed_data = $this->mock_parsed_data(
			[ 'attributes._index' => 0 ],
			[ 'post_parent|advanced' => 0 ]
		);

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Missing parent ID' );

		$product_template = new ProductTemplate( new EventHandler() );
		$product_template->set_variation_data( $variation, $parsed_data );
	}

	/**
	 * Sample products.csv: Parent={6} (e.g. variable-one), SKU={2}.
	 * Parent Field Type = Sku with Parent={6} should resolve the variable product.
	 */
	public function test_pre_process_connects_variation_parent_by_sku() {
		$parent = $this->mock_product( 'variable' );
		$parent->set_name( 'Variable Product 1' );
		$parent->set_sku( 'variable-one' );
		$parent->save();

		$template = $this->make_parent_enabled_product_template();
		$data     = $this->make_parsed_data(
			[
				'advanced._parent.parent'      => 'variable-one',
				'advanced._parent._parent_type' => 'sku',
				'inventory._sku'               => 'variation-one',
			]
		);

		$result = $template->pre_process( $data );

		$this->assertEquals( $parent->get_id(), $result->getValue( 'post_parent', 'advanced' ) );
	}

	/**
	 * Sample products.csv: Parent={6} (e.g. variable-one), SKU={2}.
	 * Parent Field Type = Reference Column with Parent Reference Column = SKU column
	 * should store _iwp_ref_post_parent on the variable product and resolve variations.
	 */
	public function test_pre_process_connects_variation_parent_by_reference_column() {
		$template = $this->make_parent_enabled_product_template();

		// Importer map mirrors UI: Parent={6}, type=Reference Column, ref column={2} (SKU).
		$map = $template->field_map(
			[
				'advanced._parent.parent'       => '{6}',
				'advanced._parent._parent_type' => 'column',
				'advanced._parent._parent_ref'  => '{2}',
			]
		);

		$this->assertArrayHasKey(
			'_iwp_ref_post_parent',
			$map,
			'field_map should virtualize advanced Parent Reference Column onto _iwp_ref_post_parent'
		);
		$this->assertSame( '{2}', $map['_iwp_ref_post_parent'] );

		$parent = $this->mock_product( 'variable' );
		$parent->set_name( 'Variable Product 1' );
		$parent->set_sku( 'variable-one' );
		$parent->save();

		// Mapper would persist the resolved SKU column value for this row.
		if ( isset( $map['_iwp_ref_post_parent'] ) ) {
			update_post_meta( $parent->get_id(), '_iwp_ref_post_parent', 'variable-one' );
		}

		$data = $this->make_parsed_data(
			[
				'advanced._parent.parent'       => 'variable-one',
				'advanced._parent._parent_type' => 'column',
				'inventory._sku'                => 'variation-one',
			]
		);

		$result = $template->pre_process( $data );

		$this->assertEquals(
			$parent->get_id(),
			$result->getValue( 'post_parent', 'advanced' ),
			'Variation Parent value variable-one should resolve via Reference Column pointing at SKU'
		);
	}

	/**
	 * @return ProductTemplate
	 */
	private function make_parent_enabled_product_template() {
		$importer_model = $this->createMock( ImporterModel::class );
		$importer_model->method( 'getSetting' )->willReturnCallback(
			function ( $key ) {
				return 'post_type' === $key ? [ 'product', 'product_variation' ] : null;
			}
		);
		$importer_model->method( 'isEnabledField' )->willReturnCallback(
			function ( $field ) {
				return 'advanced._parent' === $field;
			}
		);

		$template = new ProductTemplate( new EventHandler() );
		$template->register_hooks( $importer_model );

		return $template;
	}

	/**
	 * @param array $default_fields Fields as stored before pre_process_groups splits them.
	 * @return ParsedData
	 */
	private function make_parsed_data( array $default_fields ) {
		$mapper = $this->createMock( MapperInterface::class );
		$data   = new ParsedData( $mapper );
		$data->add( $default_fields );

		return $data;
	}

	public function test_get_product_id_by_field() {
		$product_template = $this->make_product_template();

		$this->assertFalse( $product_template->get_product_id_by_field( 'sku', 'test-one' ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'sku', 'test-two' ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'sku', 'test-three' ) );

		$product_one = $this->mock_product( 'simple' );
		$product_one->set_name( 'Test One' );
		$product_one->set_slug( 'slug-one' );
		$product_one->set_sku( 'test-one' );
		$product_one->save();

		$product_two = $this->mock_product( 'simple' );
		$product_two->set_name( 'Test Two' );
		$product_two->set_slug( 'slug-two' );
		$product_two->set_sku( 'test-two' );
		$product_two->save();

		$this->assertEquals( $product_one->get_id(), $product_template->get_product_id_by_field( 'sku', 'test-one' ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'sku', $product_one->get_name() ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'sku', $product_one->get_slug() ) );

		$this->assertEquals( $product_two->get_id(), $product_template->get_product_id_by_field( 'sku', 'test-two' ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'sku', $product_two->get_name() ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'sku', $product_two->get_slug() ) );

		$this->assertEquals( $product_one->get_id(), $product_template->get_product_id_by_field( 'name', 'Test One' ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'name', $product_one->get_slug() ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'name', $product_one->get_sku() ) );

		$this->assertEquals( $product_two->get_id(), $product_template->get_product_id_by_field( 'name', 'Test Two' ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'name', $product_two->get_slug() ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'name', $product_two->get_sku() ) );

		$this->assertEquals( $product_one->get_id(), $product_template->get_product_id_by_field( 'slug', 'slug-one' ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'sku', $product_one->get_name() ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'name', $product_one->get_sku() ) );

		$this->assertEquals( $product_two->get_id(), $product_template->get_product_id_by_field( 'slug', 'slug-two' ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'sku', $product_two->get_name() ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'name', $product_two->get_sku() ) );

		$this->assertEquals( $product_one->get_id(), $product_template->get_product_id_by_field( 'meta', [ '_sku' => $product_one->get_sku() ] ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'meta', [ '_sku' => $product_one->get_name() ] ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'meta', [ '_sku' => $product_one->get_slug() ] ) );

		$this->assertEquals( $product_two->get_id(), $product_template->get_product_id_by_field( 'meta', [ '_sku' => $product_two->get_sku() ] ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'meta', [ '_sku' => $product_two->get_name() ] ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'meta', [ '_sku' => $product_two->get_slug() ] ) );

		$this->assertFalse( $product_template->get_product_id_by_field( 'meta', [ '_sku' => '' ] ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'meta', '_sku' ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'meta', [ '_sku' => 0 ] ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'meta', [ '_sku' => 'test-sku' ] ) );
		$this->assertFalse( $product_template->get_product_id_by_field( '', 'test-one' ) );
		$this->assertFalse( $product_template->get_product_id_by_field( 'sku', '' ) );
	}

	public function test_get_product_id_by_sku() {
		$product_template = $this->getMockBuilder( ProductTemplate::class )
			->setConstructorArgs( [ new EventHandler() ] )
			->setMethods( [ 'get_product_id_by_field' ] )
			->getMock();

		$importer_model = $this->createMock( ImporterModel::class );
		$importer_model->method( 'getSetting' )->willReturn( [ 'product', 'product_variation' ] );
		$this->setProtectedProperty( $product_template, 'importer', $importer_model );

		$product_template->expects( $this->once() )
			->method( 'get_product_id_by_field' )
			->with( 'sku', 'example-sku' );

		$product_template->get_product_id_by_sku( 'example-sku' );
	}

	public function test_get_product_id_by_name() {
		$product_template = $this->getMockBuilder( ProductTemplate::class )
			->setConstructorArgs( [ new EventHandler() ] )
			->setMethods( [ 'get_product_id_by_field' ] )
			->getMock();

		$importer_model = $this->createMock( ImporterModel::class );
		$importer_model->method( 'getSetting' )->willReturn( [ 'product', 'product_variation' ] );
		$this->setProtectedProperty( $product_template, 'importer', $importer_model );

		$product_template->expects( $this->once() )
			->method( 'get_product_id_by_field' )
			->with( 'name', 'Example Name' );

		$product_template->get_product_id_by_name( 'Example Name' );
	}

	public function test_get_product_id_by_slug() {
		$product_template = $this->getMockBuilder( ProductTemplate::class )
			->setConstructorArgs( [ new EventHandler() ] )
			->setMethods( [ 'get_product_id_by_field' ] )
			->getMock();

		$importer_model = $this->createMock( ImporterModel::class );
		$importer_model->method( 'getSetting' )->willReturn( [ 'product', 'product_variation' ] );
		$this->setProtectedProperty( $product_template, 'importer', $importer_model );

		$product_template->expects( $this->once() )
			->method( 'get_product_id_by_field' )
			->with( 'slug', 'example-slug' );

		$product_template->get_product_id_by_slug( 'example-slug' );
	}

	/**
	 * @dataProvider provide_use_variation_not_set_on_insert
	 */
	public function test_use_variation_not_set_on_insert( $expected, $is_variation ) {
		$parent = $this->mock_product( 'variable' );
		$parent->save();

		$name = 'ColorTest' . $is_variation;
		$tax  = 'pa_colortest' . $is_variation;

		$parsed_data = $this->mock_parsed_data(
			[
				'attributes._index'      => 1,
				'attributes.0.name'      => $name,
				'attributes.0.terms'     => 'red',
				'attributes.0.global'    => 'yes',
				'attributes.0.visible'   => 'yes',
				'attributes.0.variation' => $is_variation,
			]
		);

		$product_template = $this->make_product_template();
		$product_template->set_product_data( $parent, $parsed_data );
		$parent->save();

		$final_parent      = wc_get_product( $parent->get_id() );
		$parent_attributes = $final_parent->get_attributes();

		$this->assertEquals( $tax, $parent_attributes[ $tax ]->get_name() );
		$this->assertGreaterThan( 0, $parent_attributes[ $tax ]->get_options()[0] );
		$this->assertTrue( $parent_attributes[ $tax ]->is_taxonomy() );
		$this->assertEquals( $expected, (bool) $parent_attributes[ $tax ]->get_variation() );
	}

	public function provide_use_variation_not_set_on_insert() {
		return [
			[ true, 'yes' ],
			[ false, 'no' ],
			[ false, '' ],
		];
	}

	public function test_no_duplicate_local_product_attribute() {
		$parent = $this->mock_product( 'simple' );
		$parent->save();

		$parsed_data = $this->mock_parsed_data(
			[
				'attributes._index'      => 1,
				'attributes.0.name'      => 'LocalProductAttribute',
				'attributes.0.terms'     => 'red,red',
				'attributes.0.global'    => 'no',
				'attributes.0.visible'   => 'yes',
				'attributes.0.variation' => '',
			]
		);

		$product_template = $this->make_product_template();
		$product_template->set_product_data( $parent, $parsed_data );
		$parent->save();

		$final_parent = wc_get_product( $parent->get_id() );

		$this->assertEquals( 'red', $final_parent->get_attribute( 'LocalProductAttribute' ) );
	}

	public function test_set_product_data_skips_empty_attribute_names() {
		$parent = $this->mock_product( 'simple' );
		$parent->save();

		$parsed_data = $this->mock_parsed_data(
			[
				'attributes._index'      => 2,
				'attributes.0.name'      => '',
				'attributes.0.terms'     => 'ignored',
				'attributes.0.global'    => 'no',
				'attributes.0.visible'   => 'yes',
				'attributes.0.variation' => '',
				'attributes.1.name'      => 'Material',
				'attributes.1.terms'     => 'cotton',
				'attributes.1.global'    => 'no',
				'attributes.1.visible'   => 'yes',
				'attributes.1.variation' => '',
			]
		);

		$product_template = $this->make_product_template();
		$product_template->set_product_data( $parent, $parsed_data );
		$parent->save();

		$final_parent = wc_get_product( $parent->get_id() );
		$attributes   = $final_parent->get_attributes();

		$this->assertCount( 1, $attributes );
		$this->assertEquals( 'cotton', $final_parent->get_attribute( 'Material' ) );
	}

	public function test_set_product_data_upsells_by_sku() {
		$related = $this->mock_product( 'simple' );
		$related->set_sku( 'upsell-sku' );
		$related->save();

		$product = $this->mock_product( 'simple' );
		$product->save();

		$parsed_data = $this->createMock( \ImportWP\Common\Importer\ParsedData::class );
		$parsed_data->method( 'permission' )->willReturn( null );
		$parsed_data->method( 'getData' )->with( 'attributes' )->willReturn( [ 'attributes._index' => 0 ] );
		$parsed_data->method( 'getValue' )->willReturnCallback(
			function ( $field, $group = null ) {
				$map = [
					'linked-products.upsell.products|linked-products' => 'upsell-sku',
					'linked-products.upsell._field_type|linked-products' => '_sku',
				];
				$key = $group ? $field . '|' . $group : $field;
				return array_key_exists( $key, $map ) ? $map[ $key ] : null;
			}
		);

		$template = new ProductTemplate( new EventHandler() );

		$importer_model = $this->createMock( ImporterModel::class );
		$importer_model->method( 'getSetting' )->willReturn( [ 'product', 'product_variation' ] );
		$importer_model->method( 'isEnabledField' )->willReturnCallback(
			function ( $field ) {
				return 'linked-products.upsell' === $field;
			}
		);
		$this->setProtectedProperty( $template, 'importer', $importer_model );

		$template->set_product_data( $product, $parsed_data );
		$product->save();

		$final = wc_get_product( $product->get_id() );
		$this->assertEquals( [ $related->get_id() ], $final->get_upsell_ids() );
	}

	public function test_register_includes_woocommerce_field_groups() {
		$template  = new ProductTemplate( new EventHandler() );
		$groups    = $template->register();
		$group_ids = array_column( $groups, 'id' );

		$this->assertContains( 'post', $group_ids );
		$this->assertContains( 'price', $group_ids );
		$this->assertContains( 'inventory', $group_ids );
		$this->assertContains( 'attributes', $group_ids );
		$this->assertContains( 'linked-products', $group_ids );

		$encoded = wp_json_encode( $groups );
		$this->assertNotFalse( strpos( $encoded, '_sku' ) );
		$this->assertNotFalse( strpos( $encoded, '_global_unique_id' ) );
	}
}
