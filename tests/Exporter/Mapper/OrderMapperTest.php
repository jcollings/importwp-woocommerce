<?php

namespace ImportWPAddon\WooCommerce\Tests\Exporter\Mapper;

use ImportWPAddon\WooCommerce\Exporter\Mapper\OrderMapper;
use ImportWPAddon\WooCommerce\Tests\Utils\OrderTestTrait;
use ImportWPAddon\WooCommerce\Tests\Utils\ProtectedPropertyTrait;

/**
 * @group Exporter
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

	public function test_get_fields_includes_order_groups() {
		$mapper = new OrderMapper();
		$fields = $mapper->get_fields();

		$this->assertSame( 'main', $fields['key'] );
		$this->assertContains( 'ID', $fields['fields'] );
		$this->assertContains( '_order_key', $fields['fields'] );
		$this->assertArrayHasKey( 'order', $fields['children'] );
		$this->assertArrayHasKey( 'customer', $fields['children'] );
		$this->assertArrayHasKey( 'billing', $fields['children'] );
		$this->assertArrayHasKey( 'shipping', $fields['children'] );
		$this->assertArrayHasKey( 'line_items', $fields['children'] );
		$this->assertArrayHasKey( 'order_totals', $fields['children'] );
		$this->assertTrue( $fields['children']['line_items']['loop'] );
		$this->assertContains( 'product', $fields['children']['line_items']['fields'] );
	}

	public function test_setup_populates_order_record() {
		$product = $this->mock_order_product( 'export-order-sku', 10.99 );
		$order   = wc_create_order();
		$order->set_order_key( 'order_key_export_1' );
		$order->set_status( 'processing' );
		$order->set_currency( 'GBP' );
		$order->set_billing_first_name( 'Alice' );
		$order->set_billing_email( 'alice@example.com' );
		$order->set_shipping_total( 5 );
		$order->set_discount_total( 1 );
		$order->add_product( $product, 2 );
		$order->set_total( 25.98 );
		$order->save();
		$this->mock_orders[] = $order;

		$mapper = new OrderMapper();
		$mapper->set_records( [ $order->get_id() ] );

		$this->assertTrue( $mapper->setup( 0 ) );
		$record = $mapper->record();

		$this->assertSame( $order->get_id(), $record['ID'] );
		$this->assertSame( 'order_key_export_1', $record['_order_key'] );
		$this->assertSame( 'processing', $record['order']['status'] );
		$this->assertSame( 'GBP', $record['order']['currency'] );
		$this->assertSame( 'Alice', $record['billing']['first_name'] );
		$this->assertSame( 'alice@example.com', $record['billing']['email'] );
		$this->assertCount( 1, $record['line_items'] );
		$this->assertSame( 'export-order-sku', $record['line_items'][0]['product'] );
		$this->assertSame( 2, intval( $record['line_items'][0]['quantity'] ) );
		$this->assertEquals( 5.0, floatval( $record['order_totals']['shipping_total'] ) );
		$this->assertEquals( 1.0, floatval( $record['order_totals']['discount_total'] ) );
	}

	public function test_have_records_finds_orders() {
		$order = wc_create_order();
		$order->save();
		$this->mock_orders[] = $order;

		$mapper = new OrderMapper();
		$this->assertTrue( $mapper->have_records( 1 ) );
		$this->assertContains( $order->get_id(), $mapper->get_records() );
	}
}
