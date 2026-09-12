<?php

namespace ImportWPAddon\WooCommerce\Tests\Importer\Mapper;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Importer\Mapper\OrderMapper;
use ImportWPAddon\WooCommerce\Importer\Template\OrderTemplate;
use ImportWPAddon\WooCommerce\Tests\Utils\OrderTestTrait;
use ImportWPAddon\WooCommerce\Tests\Utils\ProtectedPropertyTrait;

/**
 * @group Importer
 * @group Mapper
 * @group Order
 */
class OrderMapperTest extends \WP_UnitTestCase {

	use ProtectedPropertyTrait;
	use OrderTestTrait;

	public function tear_down() {
		$this->tearDownOrderMocks();
		parent::tear_down();
	}

	/**
	 * @return OrderMapper
	 */
	private function make_mapper( array $settings = [] ) {
		$defaults = [
			'post_type'              => [ 'shop_order' ],
			'unique_field'           => [ 'ID', '_order_key' ],
			'unique_identifier_type' => null,
			'unique_identifier'      => null,
		];
		$settings = array_merge( $defaults, $settings );

		$importer = $this->createMock( ImporterModel::class );
		$importer->method( 'getSetting' )->willReturnCallback(
			function ( $key ) use ( $settings ) {
				return array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
			}
		);
		$importer->method( 'has_custom_unique_identifier' )->willReturn(
			( $settings['unique_identifier_type'] ?? null ) === 'custom'
		);
		$importer->method( 'has_field_unique_identifier' )->willReturn(
			( $settings['unique_identifier_type'] ?? null ) === 'field'
		);

		$template = new OrderTemplate( new EventHandler() );
		$this->setProtectedProperty( $template, 'importer', $importer );

		return new OrderMapper( $importer, $template );
	}

	/**
	 * @return ParsedData|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function mock_create_data( array $order_values ) {
		$parsed_data = $this->createMock( ParsedData::class );
		$parsed_data->method( 'permission' )->willReturn( null );
		$parsed_data->method( 'getValue' )->willReturnCallback(
			function ( $field, $group = null ) use ( $order_values ) {
				if ( 'order' !== $group ) {
					return null;
				}
				$key = preg_replace( '/^order\./', '', $field );
				return array_key_exists( $key, $order_values ) ? $order_values[ $key ] : null;
			}
		);

		return $parsed_data;
	}

	public function test_create_order() {
		$mapper = $this->make_mapper();
		$data   = $this->mock_create_data(
			[
				'status'     => 'processing',
				'currency'   => 'GBP',
				'_order_key' => 'order_key_mapper_1',
			]
		);

		$order_id = $mapper->create_post( [], $data );
		$this->assertTrue( is_int( $order_id ) );

		$order = wc_get_order( $order_id );
		$this->assertInstanceOf( \WC_Order::class, $order );
		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( 'GBP', $order->get_currency() );
		$this->assertSame( 'order_key_mapper_1', $order->get_order_key() );

		$this->mock_orders[] = $order;
	}

	public function test_exists_by_order_key() {
		$existing = wc_create_order();
		$existing->set_order_key( 'order_key_exists_1' );
		$existing->save();
		$this->mock_orders[] = $existing;

		$mapper = $this->make_mapper(
			[
				'unique_field' => [ '_order_key' ],
			]
		);

		$data = new ParsedData( $mapper );
		$data->replace(
			[
				'_order_key' => 'order_key_exists_1',
			],
			'default'
		);
		$data->replace(
			[
				'_order_key' => 'order_key_exists_1',
			],
			'order'
		);

		$found_id = $mapper->exists( $data );
		$this->assertSame( $existing->get_id(), $found_id );
	}

	/**
	 * Reproduces: Order Key mapped as order._order_key, unique identifier set to
	 * template field "Order Key", then exists() must resolve the value after pre_process.
	 */
	public function test_exists_resolves_order_key_unique_identifier_from_mapped_field() {
		$mapper = $this->make_mapper(
			[
				'unique_identifier_type' => 'field',
				'unique_identifier'      => '_order_key',
			]
		);
		$template = $this->getProtectedProperty( $mapper, 'template' );

		// As the XML parser leaves mapped fields in the default group.
		$data = new ParsedData( $mapper );
		$data->replace(
			[
				'order._order_key' => 'order_key_1001',
				'order.status'     => 'processing',
			],
			'default'
		);

		$data = $template->pre_process( $data );

		$this->assertSame( 'order_key_1001', $data->getValue( '_order_key' ) );
		$this->assertSame( 'order_key_1001', $data->getValue( 'order._order_key', 'order' ) );

		// No existing order — exists should return false, not throw.
		$this->assertFalse( $mapper->exists( $data ) );
	}

	public function test_exists_finds_existing_order_via_mapped_order_key_unique_identifier() {
		$existing = wc_create_order();
		$existing->set_order_key( 'order_key_1001' );
		$existing->save();
		$this->mock_orders[] = $existing;

		$mapper = $this->make_mapper(
			[
				'unique_identifier_type' => 'field',
				'unique_identifier'      => '_order_key',
			]
		);
		$template = $this->getProtectedProperty( $mapper, 'template' );

		$data = new ParsedData( $mapper );
		$data->replace(
			[
				'order._order_key' => 'order_key_1001',
			],
			'default'
		);

		$data = $template->pre_process( $data );

		$this->assertSame( $existing->get_id(), $mapper->exists( $data ) );
	}

	public function test_unique_fields_defaults() {
		$mapper = $this->make_mapper();
		$fields = $this->getProtectedProperty( $mapper, '_unique_fields' );

		$this->assertSame( [ 'ID', '_order_key' ], $fields );
	}
}
