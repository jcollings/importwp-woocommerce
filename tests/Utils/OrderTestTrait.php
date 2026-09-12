<?php

namespace ImportWPAddon\WooCommerce\Tests\Utils;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Importer\Template\OrderTemplate;

trait OrderTestTrait {

	/**
	 * @var \WC_Order[]
	 */
	private $mock_orders = [];

	/**
	 * @var \WC_Product[]
	 */
	private $mock_order_products = [];

	public function tearDownOrderMocks() {
		foreach ( $this->mock_orders as $order ) {
			$order->delete( true );
		}
		$this->mock_orders = [];

		foreach ( $this->mock_order_products as $product ) {
			$product->delete( true );
		}
		$this->mock_order_products = [];
	}

	/**
	 * @param array $methods Methods to partial-mock.
	 * @return OrderTemplate|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function make_order_template( array $methods = [] ) {
		$event_handler = new EventHandler();

		if ( empty( $methods ) ) {
			$template = new OrderTemplate( $event_handler );
		} else {
			$template = $this->getMockBuilder( OrderTemplate::class )
				->setConstructorArgs( [ $event_handler ] )
				->setMethods( $methods )
				->getMock();
		}

		$importer_model = $this->createMock( ImporterModel::class );
		$importer_model->method( 'getSetting' )->willReturn( [ 'shop_order' ] );
		$importer_model->method( 'isEnabledField' )->willReturn( true );

		$this->setProtectedProperty( $template, 'importer', $importer_model );

		return $template;
	}

	/**
	 * @param string $sku
	 * @param float  $price
	 * @return \WC_Product
	 */
	protected function mock_order_product( $sku = 'order-test-sku', $price = 10.0 ) {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Order Test Product' );
		$product->set_sku( $sku );
		$product->set_regular_price( $price );
		$product->save();

		$this->mock_order_products[] = $product;

		return $product;
	}

	/**
	 * @param array $get_value_map Map of "field|group" => value for getValue().
	 * @param array $line_items    Payload for getData('line_items').
	 * @param array $get_data_map  Extra getData() payloads keyed by group id (e.g. line_items.0).
	 * @return ParsedData|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function mock_order_parsed_data( array $get_value_map = [], array $line_items = [], array $get_data_map = [] ) {
		$parsed_data = $this->createMock( ParsedData::class );
		$parsed_data->method( 'permission' )->willReturn( null );
		$parsed_data->method( 'isInsert' )->willReturn( true );
		$parsed_data->method( 'getMethod' )->willReturn( 'insert' );
		$parsed_data->method( 'getValue' )->willReturnCallback(
			function ( $field, $group = null ) use ( $get_value_map ) {
				$key = $group ? $field . '|' . $group : $field;
				return array_key_exists( $key, $get_value_map ) ? $get_value_map[ $key ] : null;
			}
		);

		if ( ! empty( $line_items ) || ! empty( $get_data_map ) ) {
			$parsed_data->method( 'getData' )->willReturnCallback(
				function ( $group = 'default' ) use ( $line_items, $get_data_map ) {
					if ( array_key_exists( $group, $get_data_map ) ) {
						return $get_data_map[ $group ];
					}
					if ( 'line_items' === $group ) {
						return $line_items;
					}
					return [];
				}
			);
		}

		return $parsed_data;
	}
}
