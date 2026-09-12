<?php

namespace ImportWPAddon\WooCommerce\Tests\Importer\Template;

use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Importer\Template\OrderTemplate;
use ImportWPAddon\WooCommerce\Tests\Utils\OrderTestTrait;
use ImportWPAddon\WooCommerce\Tests\Utils\ProtectedPropertyTrait;

/**
 * @group Importer
 * @group Template
 * @group Order
 */
class OrderTemplateTest extends \WP_UnitTestCase {

	use ProtectedPropertyTrait;
	use OrderTestTrait;

	public function tear_down() {
		$this->tearDownOrderMocks();
		parent::tear_down();
	}

	public function test_unique_identifier_options_only_active_when_mapped() {
		$template = new OrderTemplate( new EventHandler() );

		$importer = $this->createMock( \ImportWP\Common\Model\ImporterModel::class );
		$importer->method( 'getMap' )->willReturn(
			[
				'order._order_key' => '{/order_key}',
			]
		);
		$importer->method( 'isEnabledField' )->willReturn( false );

		$options = $template->get_unique_identifier_options( $importer, [ 'ID', '_order_key' ] );

		$this->assertArrayHasKey( 'ID', $options );
		$this->assertArrayHasKey( '_order_key', $options );
		$this->assertFalse( $options['ID']['active'], 'Order ID should be inactive when not mapped' );
		$this->assertTrue( $options['_order_key']['active'], 'Order Key should be active when mapped' );
		$this->assertSame( 'Order Key', $options['_order_key']['label'] );
		$this->assertSame( '_order_key', $options['_order_key']['value'] );
		$this->assertSame( 'ID', $options['ID']['value'] );

		// Keys must match unique field ids — otherwise RestManager adds a duplicate "_order_key".
		$this->assertArrayNotHasKey( 'order._order_key', $options );
		$this->assertArrayNotHasKey( 'order.ID', $options );
	}

	public function test_unique_identifier_order_id_inactive_when_mapped_but_disabled() {
		$template = new OrderTemplate( new EventHandler() );

		$importer = $this->createMock( \ImportWP\Common\Model\ImporterModel::class );
		$importer->method( 'getMap' )->willReturn(
			[
				'order.ID'         => '{/id}',
				'order._order_key' => '{/order_key}',
			]
		);
		$importer->method( 'isEnabledField' )->willReturnCallback(
			function ( $field ) {
				// Order ID was enabled, populated, then disabled — map value remains.
				return 'order.ID' !== $field;
			}
		);

		$options = $template->get_unique_identifier_options( $importer, [ 'ID', '_order_key' ] );

		$this->assertFalse( $options['ID']['active'] );
		$this->assertTrue( $options['_order_key']['active'] );

		$active = array_values(
			array_filter(
				$options,
				function ( $item ) {
					return ! empty( $item['active'] );
				}
			)
		);

		$this->assertCount( 1, $active );
		$this->assertSame( '_order_key', $active[0]['value'] );
	}

	public function test_unique_identifier_order_id_active_when_mapped_and_enabled() {
		$template = new OrderTemplate( new EventHandler() );

		$importer = $this->createMock( \ImportWP\Common\Model\ImporterModel::class );
		$importer->method( 'getMap' )->willReturn(
			[
				'order.ID' => '{/id}',
			]
		);
		$importer->method( 'isEnabledField' )->willReturnCallback(
			function ( $field ) {
				return 'order.ID' === $field;
			}
		);

		$options = $template->get_unique_identifier_options( $importer, [ 'ID', '_order_key' ] );

		$this->assertTrue( $options['ID']['active'] );
		$this->assertFalse( $options['_order_key']['active'] );
	}

	public function test_unique_identifier_options_filters_like_permissions_dropdown() {
		$template = new OrderTemplate( new EventHandler() );

		$importer = $this->createMock( \ImportWP\Common\Model\ImporterModel::class );
		$importer->method( 'getMap' )->willReturn(
			[
				'order._order_key' => '{/order_key}',
			]
		);

		$options = $template->get_unique_identifier_options( $importer, [ 'ID', '_order_key' ] );

		// Mimic RestManager::get_importer_unique_identifier_options fallback + active filter.
		$unique_fields = [ 'ID', '_order_key' ];
		foreach ( $unique_fields as $field_id ) {
			if ( ! isset( $options[ $field_id ] ) ) {
				$options[ $field_id ] = [
					'value'  => $field_id,
					'label'  => $field_id,
					'uid'    => true,
					'active' => true,
				];
			}
		}

		$active = array_values(
			array_filter(
				$options,
				function ( $item ) {
					return ! empty( $item['active'] );
				}
			)
		);

		$this->assertCount( 1, $active );
		$this->assertSame( '_order_key', $active[0]['value'] );
		$this->assertSame( 'Order Key', $active[0]['label'] );
	}

	public function test_register_includes_order_field_groups() {
		$template  = new OrderTemplate( new EventHandler() );
		$groups    = $template->register();
		$group_ids = array_column( $groups, 'id' );

		$this->assertContains( 'order', $group_ids );
		$this->assertContains( 'customer', $group_ids );
		$this->assertContains( 'billing', $group_ids );
		$this->assertContains( 'shipping', $group_ids );
		$this->assertContains( 'line_items', $group_ids );
		$this->assertContains( 'order_totals', $group_ids );

		$customer_group = null;
		foreach ( $groups as $group ) {
			if ( 'customer' === $group['id'] ) {
				$customer_group = $group;
				break;
			}
		}

		$this->assertNotNull( $customer_group );
		$this->assertCount( 1, $customer_group['fields'] );
		$this->assertSame( '_customer', $customer_group['fields'][0]['id'] );
		$this->assertSame( 'group', $customer_group['fields'][0]['type'] );

		$customer_fields = array_column( $customer_group['fields'][0]['fields'], 'id' );
		$this->assertContains( 'customer', $customer_fields );
		$this->assertContains( '_customer_type', $customer_fields );

		$line_items_group = null;
		foreach ( $groups as $group ) {
			if ( 'line_items' === $group['id'] ) {
				$line_items_group = $group;
				break;
			}
		}

		$this->assertNotNull( $line_items_group );
		$this->assertSame( 'repeatable', $line_items_group['type'] );

		$line_item_fields = array_column( $line_items_group['fields'], 'id' );
		$this->assertContains( 'row_base', $line_item_fields );
		$this->assertContains( 'product', $line_item_fields );
		$this->assertContains( '_product_type', $line_item_fields );

		$encoded = wp_json_encode( $groups );
		$this->assertNotFalse( strpos( $encoded, '_order_key' ) );
		$this->assertNotFalse( strpos( $encoded, 'line_items' ) );
	}

	public function test_register_settings_includes_send_order_emails() {
		$template = new OrderTemplate( new EventHandler() );
		$settings = $template->register_settings();
		$ids      = array_column( $settings, 'id' );

		$this->assertContains( 'send_order_emails', $ids );
		$this->assertSame( 'checkbox', $settings[0]['type'] );
		$this->assertSame( 'Enable order notification emails.', $settings[0]['label'] );
	}

	public function test_suppress_order_emails_disables_wc_order_emails() {
		$template = $this->make_order_template();
		$template->suppress_order_emails();

		$this->assertFalse( apply_filters( 'woocommerce_email_enabled_new_order', true, null ) );
		$this->assertFalse( apply_filters( 'woocommerce_email_enabled_customer_processing_order', true, null ) );
		$this->assertFalse( apply_filters( 'woocommerce_email_enabled_customer_completed_order', true, null ) );
		$this->assertFalse( apply_filters( 'woocommerce_email_enabled_customer_on_hold_order', true, null ) );
	}

	public function test_pre_process_suppresses_emails_by_default() {
		$template = $this->make_order_template();

		$importer = $this->createMock( \ImportWP\Common\Model\ImporterModel::class );
		$importer->method( 'getSetting' )->willReturnCallback(
			function ( $key ) {
				return 'send_order_emails' === $key ? false : [ 'shop_order' ];
			}
		);
		$importer->method( 'isEnabledField' )->willReturn( true );
		$this->setProtectedProperty( $template, 'importer', $importer );

		$mapper = $this->createMock( \ImportWP\Common\Importer\MapperInterface::class );
		$data   = new \ImportWP\Common\Importer\ParsedData( $mapper );
		$data->replace( [], 'default' );

		$template->pre_process( $data );

		$this->assertFalse( apply_filters( 'woocommerce_email_enabled_new_order', true, null ) );
	}

	public function test_set_order_data_and_addresses() {
		$order = wc_create_order();
		$this->assertNotInstanceOf( \WP_Error::class, $order );
		$this->mock_orders[] = $order;

		$template = $this->make_order_template();
		$data     = $this->mock_order_parsed_data(
			[
				'order.status|order'                   => 'processing',
				'order.currency|order'                 => 'GBP',
				'order.customer_note|order'            => 'Leave with neighbour',
				'order.payment_method|order'           => 'bacs',
				'order.payment_method_title|order'     => 'Direct bank transfer',
				'order._order_key|order'               => 'order_key_test_1',
				'billing.first_name|billing'           => 'Alice',
				'billing.last_name|billing'            => 'Smith',
				'billing.address_1|billing'            => '10 High Street',
				'billing.city|billing'                 => 'London',
				'billing.postcode|billing'             => 'SW1A 1AA',
				'billing.country|billing'              => 'GB',
				'billing.email|billing'                => 'alice@example.com',
				'shipping.first_name|shipping'         => 'Alice',
				'shipping.city|shipping'               => 'London',
				'shipping.country|shipping'            => 'GB',
			]
		);

		$template->set_order_data( $order, $data );
		$template->set_order_address( $order, $data, 'billing' );
		$template->set_order_address( $order, $data, 'shipping' );
		$order->save();

		$final = wc_get_order( $order->get_id() );
		$this->assertSame( 'processing', $final->get_status() );
		$this->assertSame( 'GBP', $final->get_currency() );
		$this->assertSame( 'Leave with neighbour', $final->get_customer_note() );
		$this->assertSame( 'order_key_test_1', $final->get_order_key() );
		$this->assertSame( 'Alice', $final->get_billing_first_name() );
		$this->assertSame( 'London', $final->get_billing_city() );
		$this->assertSame( 'alice@example.com', $final->get_billing_email() );
		$this->assertSame( 'Alice', $final->get_shipping_first_name() );
	}

	public function test_set_order_line_items_by_sku() {
		$product = $this->mock_order_product( 'simple-one', 10.99 );
		$order   = wc_create_order();
		$this->mock_orders[] = $order;

		$template = $this->make_order_template();
		$data     = $this->mock_order_parsed_data(
			[],
			[
				'line_items._index'          => 1,
				'line_items.0.product'       => 'simple-one',
				'line_items.0._product_type' => 'sku',
				'line_items.0.quantity'      => '2',
				'line_items.0.total'         => '21.98',
			]
		);

		$template->set_order_line_items( $order, $data );
		$template->set_order_totals( $order, $data );
		$order->save();

		$final = wc_get_order( $order->get_id() );
		$items = $final->get_items();
		$this->assertCount( 1, $items );

		$item = array_values( $items )[0];
		$this->assertSame( $product->get_id(), $item->get_product_id() );
		$this->assertSame( 2.0, floatval( $item->get_quantity() ) );
		$this->assertEquals( 21.98, floatval( $item->get_total() ) );
	}

	public function test_set_order_totals_creates_shipping_and_tax_items() {
		$order = wc_create_order();
		$this->mock_orders[] = $order;

		$template = $this->make_order_template();
		$data     = $this->mock_order_parsed_data(
			[
				'order_totals.shipping_total|order_totals' => '5',
				'order_totals.discount_total|order_totals' => '10',
				'order_totals.cart_tax|order_totals'       => '15',
				'order_totals.total|order_totals'          => '20',
			]
		);

		$template->set_order_totals( $order, $data );
		$order->save();

		$final = wc_get_order( $order->get_id() );

		$shipping_items = $final->get_items( 'shipping' );
		$this->assertCount( 1, $shipping_items );
		$shipping_item = array_values( $shipping_items )[0];
		$this->assertEquals( 5.0, floatval( $shipping_item->get_total() ) );
		$this->assertEquals( 5.0, floatval( $final->get_shipping_total() ) );

		$tax_items = $final->get_items( 'tax' );
		$this->assertCount( 1, $tax_items );
		$tax_item = array_values( $tax_items )[0];
		$this->assertEquals( 15.0, floatval( $tax_item->get_tax_total() ) );
		$this->assertEquals( 15.0, floatval( $final->get_cart_tax() ) );

		$this->assertEquals( 10.0, floatval( $final->get_discount_total() ) );
		$this->assertEquals( 20.0, floatval( $final->get_total() ) );
	}

	/**
	 * XML/JSON row_base expands into getData('line_items.0') as a list of rows.
	 */
	public function test_set_order_line_items_from_row_base() {
		$product_a = $this->mock_order_product( 'simple-one', 10.99 );
		$product_b = $this->mock_order_product( 'simple-two', 5.50 );
		$order     = wc_create_order();
		$this->mock_orders[] = $order;

		$template = $this->make_order_template();
		$data     = $this->mock_order_parsed_data(
			[],
			[
				'line_items._index'        => 1,
				'line_items.0.row_base'    => '/line_items/item',
			],
			[
				'line_items.0' => [
					[
						'line_items.0.product'       => 'simple-one',
						'line_items.0._product_type' => 'sku',
						'line_items.0.quantity'      => '1',
						'line_items.0.total'         => '10.99',
					],
					[
						'line_items.0.product'       => 'simple-two',
						'line_items.0._product_type' => 'sku',
						'line_items.0.quantity'      => '2',
						'line_items.0.total'         => '11.00',
					],
				],
			]
		);

		$template->set_order_line_items( $order, $data );
		$order->save();

		$final = wc_get_order( $order->get_id() );
		$items = array_values( $final->get_items() );
		$this->assertCount( 2, $items );
		$this->assertSame( $product_a->get_id(), $items[0]->get_product_id() );
		$this->assertSame( $product_b->get_id(), $items[1]->get_product_id() );
		$this->assertSame( 2.0, floatval( $items[1]->get_quantity() ) );
	}

	public function test_set_order_line_items_skips_missing_sku() {
		$order = wc_create_order();
		$this->mock_orders[] = $order;

		$template = $this->make_order_template();
		$data     = $this->mock_order_parsed_data(
			[],
			[
				'line_items._index'          => 1,
				'line_items.0.product'       => 'does-not-exist',
				'line_items.0._product_type' => 'sku',
				'line_items.0.quantity'      => '1',
			]
		);

		$template->set_order_line_items( $order, $data );
		$order->save();

		$this->assertCount( 0, wc_get_order( $order->get_id() )->get_items() );
	}

	public function test_get_product_from_value_by_sku_and_id() {
		$product  = $this->mock_order_product( 'lookup-sku', 5 );
		$template = $this->make_order_template();

		$by_sku = $template->get_product_from_value( 'lookup-sku', 'sku' );
		$by_id  = $template->get_product_from_value( (string) $product->get_id(), 'id' );

		$this->assertInstanceOf( \WC_Product::class, $by_sku );
		$this->assertSame( $product->get_id(), $by_sku->get_id() );
		$this->assertInstanceOf( \WC_Product::class, $by_id );
		$this->assertSame( $product->get_id(), $by_id->get_id() );
		$this->assertFalse( $template->get_product_from_value( 'missing-sku', 'sku' ) );
	}

	public function test_set_order_customer_by_email() {
		$user_id = $this->factory()->user->create(
			[
				'user_login' => 'order_customer',
				'user_email' => 'order_customer@example.com',
				'role'       => 'customer',
			]
		);

		$order = wc_create_order();
		$this->mock_orders[] = $order;

		$template = $this->make_order_template();
		$data     = $this->mock_order_parsed_data(
			[
				'customer._customer.customer|customer'       => 'order_customer@example.com',
				'customer._customer._customer_type|customer' => 'email',
			]
		);

		$template->set_order_customer( $order, $data );
		$order->save();

		$final = wc_get_order( $order->get_id() );
		$this->assertSame( $user_id, $final->get_customer_id() );

		wp_delete_user( $user_id );
	}
}
