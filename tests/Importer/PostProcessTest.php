<?php

namespace ImportWPAddon\WooCommerce\Tests\Importer;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;
use ImportWPAddon\WooCommerce\Tests\Utils\ProductTestTrait;
use ImportWPAddon\WooCommerce\Tests\Utils\ProtectedPropertyTrait;

/**
 * @group Importer
 * @group Setup
 */
class PostProcessTest extends \WP_UnitTestCase {

	use ProtectedPropertyTrait;
	use ProductTestTrait;

	/**
	 * @var int
	 */
	private $original_default_cat;

	public function set_up() {
		parent::set_up();
		$this->original_default_cat = (int) get_option( 'default_product_cat' );
	}

	public function tear_down() {
		update_option( 'default_product_cat', $this->original_default_cat );
		$this->tearDownProductMocks();
		parent::tear_down();
	}

	/**
	 * Ensure a real product_cat term is the site default for this test.
	 *
	 * @return int
	 */
	private function ensure_default_product_cat() {
		$term = wp_insert_term( 'IWP Default Cat ' . uniqid(), 'product_cat' );
		$this->assertFalse( is_wp_error( $term ) );

		$term_id = (int) $term['term_id'];
		update_option( 'default_product_cat', $term_id );

		return $term_id;
	}

	public function test_default_product_cat_removed_when_other_categories_imported() {
		$default_cat = $this->ensure_default_product_cat();

		$custom_cat = wp_insert_term( 'Imported Category ' . uniqid(), 'product_cat' );
		$this->assertFalse( is_wp_error( $custom_cat ) );

		$product = $this->mock_product( 'simple' );
		$product->set_category_ids( [ $default_cat, (int) $custom_cat['term_id'] ] );
		$product->save();

		$template = new ProductTemplate( new EventHandler() );
		$this->setProtectedProperty(
			$template,
			'_taxonomies',
			[
				'product_cat' => [ 'Imported Category' ],
			]
		);

		$parsed_data = $this->createMock( ParsedData::class );

		$result = iwp_woocommerce_register_template_post_process( $product->get_id(), $parsed_data, $template );
		$this->assertEquals( $product->get_id(), $result );

		$term_ids = wp_get_object_terms( $product->get_id(), 'product_cat', [ 'fields' => 'ids' ] );

		$this->assertNotContains( $default_cat, $term_ids );
		$this->assertContains( (int) $custom_cat['term_id'], $term_ids );
	}

	public function test_default_product_cat_kept_when_no_imported_categories() {
		$default_cat = $this->ensure_default_product_cat();

		$product = $this->mock_product( 'simple' );
		$product->set_category_ids( [ $default_cat ] );
		$product->save();

		$before = wp_get_object_terms( $product->get_id(), 'product_cat', [ 'fields' => 'ids' ] );
		$this->assertContains( $default_cat, $before );

		$template = new ProductTemplate( new EventHandler() );
		$this->setProtectedProperty( $template, '_taxonomies', [] );

		$parsed_data = $this->createMock( ParsedData::class );
		iwp_woocommerce_register_template_post_process( $product->get_id(), $parsed_data, $template );

		$after = wp_get_object_terms( $product->get_id(), 'product_cat', [ 'fields' => 'ids' ] );
		$this->assertEquals( $before, $after );
		$this->assertContains( $default_cat, $after );
	}
}
