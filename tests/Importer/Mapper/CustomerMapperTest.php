<?php

namespace ImportWPAddon\WooCommerce\Tests\Importer\Mapper;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Importer\Mapper\CustomerMapper;
use ImportWPAddon\WooCommerce\Importer\Template\CustomerTemplate;
use ImportWPAddon\WooCommerce\Tests\Utils\CustomerTestTrait;
use ImportWPAddon\WooCommerce\Tests\Utils\ProtectedPropertyTrait;

/**
 * @group Importer
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

	/**
	 * @return CustomerMapper
	 */
	private function make_mapper() {
		$importer = $this->createMock( ImporterModel::class );
		$importer->method( 'isEnabledField' )->willReturn( false );
		$importer->method( 'getSetting' )->willReturn( null );
		$importer->method( 'has_custom_unique_identifier' )->willReturn( false );

		$template = new CustomerTemplate( new EventHandler() );
		$this->setProtectedProperty( $template, 'importer', $importer );

		$mapper = new CustomerMapper( $importer, $template );
		$this->setProtectedProperty( $template, 'mapper', null );

		return $mapper;
	}

	public function test_unique_fields_defaults() {
		$mapper = $this->make_mapper();
		$fields = $this->getProtectedProperty( $mapper, '_unique_fields' );

		$this->assertSame( [ 'ID', 'user_email', 'user_login' ], $fields );
	}

	public function test_insert_defaults_role_to_customer() {
		$mapper   = $this->make_mapper();
		$template = $this->getProtectedProperty( $mapper, 'template' );

		$data = new ParsedData( $mapper );
		$data->replace(
			[
				'user_login' => 'mapper_customer',
				'user_email' => 'mapper_customer@example.com',
				'user_pass'  => 'password123',
				'first_name' => 'Map',
				'last_name'  => 'Customer',
			],
			'default'
		);

		$user_id = $mapper->insert( $data );
		$this->assertTrue( is_int( $user_id ) );
		$this->mock_customer_ids[] = $user_id;

		$user = get_user_by( 'id', $user_id );
		$this->assertContains( 'customer', $user->roles );
		$this->assertSame( 'mapper_customer@example.com', $user->user_email );
		$this->assertSame( 'Map', $user->first_name );
	}
}
