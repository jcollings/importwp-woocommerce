<?php

namespace ImportWPAddon\WooCommerce\Tests\Exporter;

use ImportWPAddon\WooCommerce\Exporter\Mapper\CustomerMapper;
use ImportWPAddon\WooCommerce\Exporter\Mapper\OrderMapper;
use ImportWPAddon\WooCommerce\Exporter\Mapper\ProductMapper;

/**
 * @group Exporter
 * @group Registration
 */
class ExporterRegistrationTest extends \WP_UnitTestCase {

	public function test_export_field_list_includes_woocommerce_types() {
		$fields = apply_filters( 'iwp/exporter/export_field_list', [] );
		$ids    = array_column( $fields, 'id' );

		$this->assertContains( 'woocommerce_product', $ids );
		$this->assertContains( 'woocommerce_customer', $ids );
		$this->assertContains( 'woocommerce_order', $ids );

		$by_id = [];
		foreach ( $fields as $field ) {
			$by_id[ $field['id'] ] = $field;
		}

		$this->assertSame( 'WooCommerce Products', $by_id['woocommerce_product']['label'] );
		$this->assertSame( 'WooCommerce Customers', $by_id['woocommerce_customer']['label'] );
		$this->assertSame( 'WooCommerce Orders', $by_id['woocommerce_order']['label'] );
		$this->assertNotEmpty( $by_id['woocommerce_product']['fields'] );
		$this->assertNotEmpty( $by_id['woocommerce_customer']['fields'] );
		$this->assertNotEmpty( $by_id['woocommerce_order']['fields'] );
	}

	public function test_load_mapper_returns_woocommerce_mappers() {
		$product  = apply_filters( 'iwp/exporter/load_mapper', false, 'woocommerce_product' );
		$customer = apply_filters( 'iwp/exporter/load_mapper', false, 'woocommerce_customer' );
		$order    = apply_filters( 'iwp/exporter/load_mapper', false, 'woocommerce_order' );
		$other    = apply_filters( 'iwp/exporter/load_mapper', false, 'post' );

		$this->assertInstanceOf( ProductMapper::class, $product );
		$this->assertInstanceOf( CustomerMapper::class, $customer );
		$this->assertInstanceOf( OrderMapper::class, $order );
		$this->assertFalse( $other );
	}
}
