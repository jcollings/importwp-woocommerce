<?php

namespace ImportWPAddon\WooCommerce\Tests\Utils;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Importer\Template\CustomerTemplate;

trait CustomerTestTrait {

	/**
	 * @var int[]
	 */
	private $mock_customer_ids = [];

	public function tearDownCustomerMocks() {
		foreach ( $this->mock_customer_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->mock_customer_ids = [];
	}

	/**
	 * @param array $methods Methods to partial-mock.
	 * @return CustomerTemplate|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function make_customer_template( array $methods = [] ) {
		$event_handler = new EventHandler();

		if ( empty( $methods ) ) {
			$template = new CustomerTemplate( $event_handler );
		} else {
			$template = $this->getMockBuilder( CustomerTemplate::class )
				->setConstructorArgs( [ $event_handler ] )
				->setMethods( $methods )
				->getMock();
		}

		$importer_model = $this->createMock( ImporterModel::class );
		$importer_model->method( 'isEnabledField' )->willReturn( false );
		$importer_model->method( 'getSetting' )->willReturn( null );

		$this->setProtectedProperty( $template, 'importer', $importer_model );

		return $template;
	}

	/**
	 * @param array $get_value_map Map of "field|group" => value for getValue().
	 * @return ParsedData|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function mock_customer_parsed_data( array $get_value_map = [] ) {
		$parsed_data = $this->createMock( ParsedData::class );
		$parsed_data->method( 'permission' )->willReturn( null );
		$parsed_data->method( 'isInsert' )->willReturn( true );
		$parsed_data->method( 'getValue' )->willReturnCallback(
			function ( $field, $group = null ) use ( $get_value_map ) {
				$key = $group ? $field . '|' . $group : $field;
				return array_key_exists( $key, $get_value_map ) ? $get_value_map[ $key ] : null;
			}
		);

		return $parsed_data;
	}
}
