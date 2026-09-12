<?php

namespace ImportWPAddon\WooCommerce\Tests\Importer\FromExporter;

use ImportWP\Common\Exporter\ExporterManager;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Exporter\Mapper\CustomerMapper;
use ImportWPAddon\WooCommerce\Exporter\Mapper\OrderMapper;
use ImportWPAddon\WooCommerce\Exporter\Mapper\ProductMapper;
use ImportWPAddon\WooCommerce\Importer\Template\CustomerTemplate;
use ImportWPAddon\WooCommerce\Importer\Template\OrderTemplate;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;

/**
 * Covers create-importer-from-exporter mapping and exporter config upload payloads.
 *
 * @group Exporter
 * @group Importer
 * @group FromExporter
 */
class CreateImporterFromExporterTest extends \WP_UnitTestCase {

	/**
	 * @return ExporterManager
	 */
	private function exporter_manager() {
		return new ExporterManager();
	}

	/**
	 * @param object $mapper
	 * @return string[]
	 */
	private function csv_headings_from_mapper( $mapper ) {
		$fields   = $this->exporter_manager()->flattenFields( $mapper->get_fields() );
		$headings = [];
		foreach ( $fields as $field ) {
			$headings[] = $field['selection'];
		}
		return $headings;
	}

	/**
	 * Mimic download-config / read-config payload used by setup_type=upload.
	 *
	 * @param string $type
	 * @param object $mapper
	 * @return array
	 */
	private function build_exporter_config( $type, $mapper ) {
		$fields = $this->exporter_manager()->flattenFields( $mapper->get_fields() );

		return [
			'name'             => 'Test ' . $type . ' exporter',
			'data'             => [
				'type'          => $type,
				'file_type'     => 'csv',
				'file_settings' => [
					'delimiter' => ',',
					'enclosure' => '"',
				],
				'fields'        => $fields,
			],
			'fields'           => $fields,
			'formatted_fields' => $mapper->get_fields(),
		];
	}

	public function test_product_generate_field_map_from_exporter_csv_headings() {
		$mapper   = new ProductMapper( [ 'product', 'product_variation' ] );
		$headings = $this->csv_headings_from_mapper( $mapper );
		$importer = $this->createMock( ImporterModel::class );

		$template = new ProductTemplate( new EventHandler() );
		$result   = $template->generate_field_map( $headings, $importer );

		$this->assertArrayHasKey( 'map', $result );
		$this->assertArrayHasKey( 'enabled', $result );
		$this->assertArrayHasKey( 'price._regular_price', $result['map'] );
		$this->assertContains( 'sku', $headings );
		$this->assertContains( 'woocommerce.regular_price', $headings );
	}

	public function test_customer_generate_field_map_from_exporter_csv_headings() {
		$mapper   = new CustomerMapper();
		$headings = $this->csv_headings_from_mapper( $mapper );
		$importer = $this->createMock( ImporterModel::class );

		$template = new CustomerTemplate( new EventHandler() );
		$result   = $template->generate_field_map( $headings, $importer );

		$this->assertArrayHasKey( 'user.user_email', $result['map'] );
		$this->assertArrayHasKey( 'billing.first_name', $result['map'] );
		$this->assertArrayHasKey( 'shipping.city', $result['map'] );
		$this->assertArrayHasKey( 'billing.first_name', $result['enabled'] );
		$this->assertTrue( $result['enabled']['billing.first_name'] );
		$this->assertArrayHasKey( 'shipping.city', $result['enabled'] );
		$this->assertTrue( $result['enabled']['shipping.city'] );

		// Older RestManager expects enabled as field_id => true.
		$enabled_model = new ImporterModel(
			[
				'name'    => 'from-exporter-customer',
				'enabled' => [],
			]
		);
		foreach ( $result['enabled'] as $key => $value ) {
			if ( true === $value || 1 === $value || '1' === $value || 'true' === $value ) {
				$enabled_model->setEnabled( $key );
			}
		}

		$this->assertTrue( $enabled_model->isEnabledField( 'billing.first_name' ) );
		$this->assertTrue( $enabled_model->isEnabledField( 'shipping.city' ) );
	}

	public function test_order_generate_field_map_from_exporter_csv_headings() {
		$mapper   = new OrderMapper();
		$headings = $this->csv_headings_from_mapper( $mapper );
		$importer = $this->createMock( ImporterModel::class );

		$template = new OrderTemplate( new EventHandler() );
		$result   = $template->generate_field_map( $headings, $importer );

		$this->assertArrayHasKey( 'order._order_key', $result['map'] );
		$this->assertArrayHasKey( 'order.status', $result['map'] );
		$this->assertArrayHasKey( 'billing.email', $result['map'] );
		$this->assertArrayHasKey( 'order_totals.total', $result['map'] );
		$this->assertArrayHasKey( 'line_items._index', $result['map'] );
		$this->assertArrayHasKey( 'line_items.0.product', $result['map'] );
		$this->assertSame( 'sku', $result['map']['line_items.0._product_type'] );
		$this->assertArrayHasKey( 'customer._customer.customer', $result['map'] );
		$this->assertSame( 'email', $result['map']['customer._customer._customer_type'] );
		$this->assertArrayHasKey( 'order_totals.total', $result['enabled'] );
		$this->assertTrue( $result['enabled']['order_totals.total'] );
	}

	public function test_upload_exporter_config_payload_maps_customer_fields() {
		$config   = $this->build_exporter_config( 'woocommerce_customer', new CustomerMapper() );
		$headings = array_map(
			function ( $item ) {
				return $item['selection'];
			},
			$config['fields']
		);

		$this->assertSame( 'csv', $config['data']['file_type'] );
		$this->assertArrayHasKey( 'formatted_fields', $config );

		$template = new CustomerTemplate( new EventHandler() );
		$result   = $template->generate_field_map( $headings, $this->createMock( ImporterModel::class ) );

		$this->assertArrayHasKey( 'billing.first_name', $result['map'] );
		$this->assertArrayHasKey( 'user.user_email', $result['map'] );
	}

	public function test_upload_exporter_config_payload_maps_order_fields() {
		$config   = $this->build_exporter_config( 'woocommerce_order', new OrderMapper() );
		$headings = array_map(
			function ( $item ) {
				return $item['selection'];
			},
			$config['fields']
		);

		$template = new OrderTemplate( new EventHandler() );
		$result   = $template->generate_field_map( $headings, $this->createMock( ImporterModel::class ) );

		$this->assertArrayHasKey( 'order._order_key', $result['map'] );
		$this->assertArrayHasKey( 'line_items.0.product', $result['map'] );
		$this->assertArrayHasKey( 'order_totals.shipping_total', $result['map'] );
	}

	public function test_upload_exporter_config_payload_maps_product_fields() {
		$config   = $this->build_exporter_config( 'woocommerce_product', new ProductMapper( [ 'product', 'product_variation' ] ) );
		$headings = array_map(
			function ( $item ) {
				return $item['selection'];
			},
			$config['fields']
		);

		$template = new ProductTemplate( new EventHandler() );
		$result   = $template->generate_field_map( $headings, $this->createMock( ImporterModel::class ) );

		$this->assertArrayHasKey( 'map', $result );
		$this->assertArrayHasKey( 'price._regular_price', $result['map'] );
	}

	public function test_from_exporter_unique_identifier_aliases_product_sku() {
		$importer = $this->createMock( ImporterModel::class );
		$importer->method( 'getTemplate' )->willReturn( 'woocommerce-product' );

		$mapped = apply_filters( 'iwp/importer/from_exporter/unique_identifier', 'sku', 'sku', $importer, null );
		$this->assertSame( '_sku', $mapped );
	}

	public function test_from_exporter_unique_identifier_passes_through_customer_email() {
		$importer = $this->createMock( ImporterModel::class );
		$importer->method( 'getTemplate' )->willReturn( 'woocommerce-customer' );

		$mapped = apply_filters( 'iwp/importer/from_exporter/unique_identifier', 'user_email', 'user_email', $importer, null );
		$this->assertSame( 'user_email', $mapped );
	}

	public function test_from_exporter_unique_identifier_passes_through_order_key() {
		$importer = $this->createMock( ImporterModel::class );
		$importer->method( 'getTemplate' )->willReturn( 'woocommerce-order' );

		$mapped = apply_filters( 'iwp/importer/from_exporter/unique_identifier', '_order_key', '_order_key', $importer, null );
		$this->assertSame( '_order_key', $mapped );
	}
}
