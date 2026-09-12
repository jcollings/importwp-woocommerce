<?php

namespace ImportWPAddon\WooCommerce\Tests\Importer\Template;

use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Importer\Template\CustomerTemplate;
use ImportWPAddon\WooCommerce\Tests\Utils\CustomerTestTrait;
use ImportWPAddon\WooCommerce\Tests\Utils\ProtectedPropertyTrait;

/**
 * @group Importer
 * @group Template
 * @group Customer
 */
class CustomerTemplateTest extends \WP_UnitTestCase {

	use ProtectedPropertyTrait;
	use CustomerTestTrait;

	public function tear_down() {
		$this->tearDownCustomerMocks();
		parent::tear_down();
	}

	public function test_register_includes_customer_field_groups() {
		$template  = new CustomerTemplate( new EventHandler() );
		$groups    = $template->register();
		$group_ids = array_column( $groups, 'id' );

		$this->assertContains( 'user', $group_ids );
		$this->assertContains( 'billing', $group_ids );
		$this->assertContains( 'shipping', $group_ids );
		$this->assertSame( 'Customer Fields', $groups[0]['heading'] );

		$encoded = wp_json_encode( $groups );
		$this->assertNotFalse( strpos( $encoded, 'user_email' ) );
		$this->assertNotFalse( strpos( $encoded, 'billing' ) );
	}

	public function test_unique_identifier_options_only_active_when_mapped() {
		$template = new CustomerTemplate( new EventHandler() );

		$importer = $this->createMock( \ImportWP\Common\Model\ImporterModel::class );
		$importer->method( 'getMap' )->willReturn(
			[
				'user.user_email' => '{/email}',
			]
		);
		$importer->method( 'isEnabledField' )->willReturn( true );

		$options = $template->get_unique_identifier_options( $importer, [ 'ID', 'user_email', 'user_login' ] );

		$this->assertArrayHasKey( 'user_email', $options );
		$this->assertArrayHasKey( 'user_login', $options );
		$this->assertTrue( $options['user_email']['active'] );
		$this->assertFalse( $options['user_login']['active'] );
		$this->assertSame( 'Email', $options['user_email']['label'] );
		$this->assertSame( 'Username', $options['user_login']['label'] );

		// No duplicate always-active entries under user.user_* keys.
		$this->assertArrayNotHasKey( 'user.user_email', $options );
		$this->assertArrayNotHasKey( 'user.user_login', $options );

		$active = array_values(
			array_filter(
				$options,
				function ( $item ) {
					return ! empty( $item['active'] );
				}
			)
		);
		$this->assertCount( 1, $active );
		$this->assertSame( 'user_email', $active[0]['value'] );
	}

	public function test_register_settings_includes_notify_users() {
		$template = new CustomerTemplate( new EventHandler() );
		$settings = $template->register_settings();
		$ids      = array_column( $settings, 'id' );

		$this->assertContains( 'notify_users', $ids );
		$this->assertContains( 'generate_pass', $ids );

		$notify = null;
		foreach ( $settings as $setting ) {
			if ( 'notify_users' === $setting['id'] ) {
				$notify = $setting;
				break;
			}
		}

		$this->assertNotNull( $notify );
		$this->assertSame( 'checkbox', $notify['type'] );
		$this->assertSame( 'Enable customer notification emails.', $notify['label'] );
	}

	public function test_suppress_customer_emails_disables_wc_new_account_email() {
		$template = $this->make_customer_template();
		$template->suppress_customer_emails();

		$this->assertFalse( apply_filters( 'woocommerce_email_enabled_customer_new_account', true ) );
		$this->assertFalse( apply_filters( 'woocommerce_email_enabled_customer_reset_password', true ) );
	}

	public function test_set_customer_address_applies_billing_and_shipping() {
		$user_id = wp_insert_user(
			[
				'user_login' => 'wc_customer_test',
				'user_email' => 'wc_customer_test@example.com',
				'user_pass'  => 'password',
				'role'       => 'customer',
			]
		);
		$this->assertTrue( is_int( $user_id ) );
		$this->mock_customer_ids[] = $user_id;

		$template = $this->make_customer_template();
		$customer = new \WC_Customer( $user_id );
		$data     = $this->mock_customer_parsed_data(
			[
				'billing.first_name|billing'   => 'Alice',
				'billing.last_name|billing'    => 'Smith',
				'billing.address_1|billing'    => '10 High Street',
				'billing.city|billing'         => 'London',
				'billing.postcode|billing'     => 'SW1A 1AA',
				'billing.country|billing'      => 'GB',
				'billing.email|billing'        => 'alice@example.com',
				'billing.phone|billing'        => '07000000001',
				'shipping.first_name|shipping' => 'Alice',
				'shipping.last_name|shipping'  => 'Smith',
				'shipping.address_1|shipping'  => '10 High Street',
				'shipping.city|shipping'       => 'London',
				'shipping.postcode|shipping'   => 'SW1A 1AA',
				'shipping.country|shipping'    => 'GB',
			]
		);

		$template->set_customer_address( $customer, $data, 'billing' );
		$template->set_customer_address( $customer, $data, 'shipping' );
		$customer->save();

		$final = new \WC_Customer( $user_id );
		$this->assertSame( 'Alice', $final->get_billing_first_name() );
		$this->assertSame( 'Smith', $final->get_billing_last_name() );
		$this->assertSame( '10 High Street', $final->get_billing_address_1() );
		$this->assertSame( 'London', $final->get_billing_city() );
		$this->assertSame( 'SW1A 1AA', $final->get_billing_postcode() );
		$this->assertSame( 'GB', $final->get_billing_country() );
		$this->assertSame( 'alice@example.com', $final->get_billing_email() );
		$this->assertSame( 'Alice', $final->get_shipping_first_name() );
		$this->assertSame( 'London', $final->get_shipping_city() );
	}

	public function test_apply_customer_addresses_saves_billing_data() {
		$user_id = wp_insert_user(
			[
				'user_login' => 'wc_customer_post',
				'user_email' => 'wc_customer_post@example.com',
				'user_pass'  => 'password',
				'role'       => 'customer',
			]
		);
		$this->mock_customer_ids[] = $user_id;

		$template = $this->make_customer_template();
		$data     = $this->mock_customer_parsed_data(
			[
				'billing.first_name|billing' => 'Bob',
				'billing.last_name|billing'  => 'Jones',
				'billing.city|billing'       => 'Manchester',
				'billing.country|billing'    => 'GB',
				'billing.email|billing'      => 'bob@example.com',
			]
		);

		$template->apply_customer_addresses( $user_id, $data );

		$final = new \WC_Customer( $user_id );
		$this->assertSame( 'Bob', $final->get_billing_first_name() );
		$this->assertSame( 'Jones', $final->get_billing_last_name() );
		$this->assertSame( 'Manchester', $final->get_billing_city() );
		$this->assertSame( 'bob@example.com', $final->get_billing_email() );
	}
}
