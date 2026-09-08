<?php

namespace ImportWPAddon\WooCommerce\Tests\Utils;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;

trait ProductTestTrait {

	/**
	 * @var \WC_Product[]
	 */
	private $mock_products = [];

	public function tearDownProductMocks() {
		foreach ( $this->mock_products as $product ) {
			$product->delete( true );
		}
		$this->mock_products = [];
	}

	/**
	 * @param string $type Product type.
	 * @return \WC_Product
	 */
	protected function mock_product( $type ) {
		$classname = \WC_Product_Factory::get_classname_from_product_type( $type );
		$product   = new $classname( 0 );
		$product->save();

		$this->mock_products[] = $product;

		return $product;
	}

	/**
	 * @param array $methods Methods to partial-mock.
	 * @return ProductTemplate|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function make_product_template( array $methods = [] ) {
		$event_handler = new EventHandler();

		if ( empty( $methods ) ) {
			$template = new ProductTemplate( $event_handler );
		} else {
			$template = $this->getMockBuilder( ProductTemplate::class )
				->setConstructorArgs( [ $event_handler ] )
				->setMethods( $methods )
				->getMock();
		}

		$importer_model = $this->createMock( ImporterModel::class );
		$importer_model->method( 'getSetting' )->willReturn( [ 'product', 'product_variation' ] );
		$importer_model->method( 'isEnabledField' )->willReturn( false );

		$this->setProtectedProperty( $template, 'importer', $importer_model );

		return $template;
	}

	/**
	 * @param array $attributes Attribute payload for getData('attributes').
	 * @param array $get_value_map Map of "field|group" => value for getValue().
	 * @return ParsedData|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function mock_parsed_data( array $attributes = [], array $get_value_map = [] ) {
		$parsed_data = $this->createMock( ParsedData::class );
		$parsed_data->method( 'permission' )->willReturn( null );
		$parsed_data->method( 'isInsert' )->willReturn( true );

		if ( ! empty( $attributes ) ) {
			$parsed_data->method( 'getData' )
				->with( $this->equalTo( 'attributes' ) )
				->willReturn( $attributes );
		}

		if ( ! empty( $get_value_map ) ) {
			$parsed_data->method( 'getValue' )->willReturnCallback(
				function ( $field, $group = null ) use ( $get_value_map ) {
					$key = $group ? $field . '|' . $group : $field;
					return array_key_exists( $key, $get_value_map ) ? $get_value_map[ $key ] : null;
				}
			);
		}

		return $parsed_data;
	}
}
