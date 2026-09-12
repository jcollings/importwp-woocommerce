<?php

namespace ImportWPAddon\WooCommerce\Tests\Exporter\Mapper;

use ImportWPAddon\WooCommerce\Exporter\Mapper\CustomerMapper;
use ImportWPAddon\WooCommerce\Tests\Utils\CustomerTestTrait;
use ImportWPAddon\WooCommerce\Tests\Utils\ProtectedPropertyTrait;

/**
 * @group Exporter
 * @group Mapper
 * @group Customer
 */
class CustomerMapperTest extends \WP_UnitTestCase {

	use ProtectedPropertyTrait;
	use CustomerTestTrait;

	public function tear_down() {
		$this->tearDownCustomerMocks();
		parent::tear_down();
	}

	public function test_get_fields_includes_billing_and_shipping() {
		$mapper = new CustomerMapper();
		$fields = $mapper->get_fields();

		$this->assertSame( 'Customer', $fields['label'] );
		$this->assertArrayHasKey( 'billing', $fields['children'] );
		$this->assertArrayHasKey( 'shipping', $fields['children'] );
		$this->assertContains( 'email', $fields['children']['billing']['fields'] );
		$this->assertNotContains( 'email', $fields['children']['shipping']['fields'] );
		$this->assertContains( 'user_email', $fields['fields'] );
	}

	public function test_have_records_only_returns_customers() {
		$customer_id = $this->factory()->user->create(
			[
				'user_login' => 'export_customer',
				'user_email' => 'export_customer@example.com',
				'role'       => 'customer',
			]
		);
		$subscriber_id = $this->factory()->user->create(
			[
				'user_login' => 'export_subscriber',
				'user_email' => 'export_subscriber@example.com',
				'role'       => 'subscriber',
			]
		);
		$this->mock_customer_ids[] = $customer_id;
		$this->mock_customer_ids[] = $subscriber_id;

		$mapper = new CustomerMapper();
		$this->assertTrue( $mapper->have_records( 1 ) );

		$ids = $mapper->get_records();
		$this->assertContains( $customer_id, $ids );
		$this->assertNotContains( $subscriber_id, $ids );
	}

	public function test_setup_populates_billing_and_shipping() {
		$user_id = $this->factory()->user->create(
			[
				'user_login' => 'export_addr_customer',
				'user_email' => 'export_addr_customer@example.com',
				'role'       => 'customer',
			]
		);
		$this->mock_customer_ids[] = $user_id;

		$customer = new \WC_Customer( $user_id );
		$customer->set_billing_first_name( 'Alice' );
		$customer->set_billing_city( 'London' );
		$customer->set_billing_country( 'GB' );
		$customer->set_billing_email( 'alice@example.com' );
		$customer->set_shipping_first_name( 'Alice' );
		$customer->set_shipping_city( 'London' );
		$customer->save();

		$mapper = new CustomerMapper();
		$mapper->set_records( [ $user_id ] );

		$this->assertTrue( $mapper->setup( 0 ) );
		$record = $mapper->record();

		$this->assertSame( 'Alice', $record['billing']['first_name'] );
		$this->assertSame( 'London', $record['billing']['city'] );
		$this->assertSame( 'alice@example.com', $record['billing']['email'] );
		$this->assertSame( 'Alice', $record['shipping']['first_name'] );
		$this->assertSame( 'London', $record['shipping']['city'] );
	}

	public function test_remove_address_custom_fields() {
		$mapper = new CustomerMapper();
		$fields = $mapper->remove_address_custom_fields(
			[ 'billing_first_name', 'shipping_city', 'nickname', 'billing_phone' ]
		);

		$this->assertSame( [ 'nickname' ], $fields );
	}
}
