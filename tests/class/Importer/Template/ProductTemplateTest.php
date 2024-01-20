<?php

namespace ImportWPAddonTests\WooCommerce\Importer\Template;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\EventHandler;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;

class ProductTemplateTest extends \WP_UnitTestCase
{
    /**
     * @var \WC_Product[]
     */
    private $mock_products = [];

    public function set_up()
    {
        parent::set_up();
    }

    public function tear_down()
    {
        parent::tear_down();

        foreach ($this->mock_products as $product) {
            $product->delete(true);
        }
    }

    /**
     * @return \WC_Product
     */
    private function mock_product($type)
    {
        $classname = \WC_Product_Factory::get_classname_from_product_type($type);
        $product = new $classname(0);
        $product->save();

        $this->mock_products[] = $product;

        return $product;
    }

    public function test_set_variation_data_custom_attribute()
    {
        // generate test products
        $parent = $this->mock_product('variable');
        $parent->set_sku('ASD123');
        $parent->save();

        $variation = $this->mock_product('variation');

        // mock objects
        $event_handler = new EventHandler();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject|ParsedData $parsed_data
         */
        $parsed_data = $this->createMock(ParsedData::class);

        $parsed_data->method('getData')
            ->with($this->equalTo('attributes'))
            ->willReturn([
                'attributes._index' => 3,
                'attributes.0.name' => 'Color',
                'attributes.0.terms' => 'red',
                'attributes.0.global' => 'no',
                'attributes.0.visible' => 'yes',
                'attributes.0.variation' => 'yes',
                'attributes.1.name' => 'Size',
                'attributes.1.terms' => 'sm',
                'attributes.1.global' => 'no',
                'attributes.1.visible' => 'no',
                'attributes.1.variation' => '',
                'attributes.2.name' => 'Shape',
                'attributes.2.terms' => 'square, circle',
                'attributes.2.global' => 'no',
                'attributes.2.visible' => 'yes',
                'attributes.2.variation' => 'no',
            ]);

        $parsed_data->method('getValue')
            ->with('post_parent', 'advanced')
            ->willReturn($parent->get_id());


        $product_template = new ProductTemplate($event_handler);
        $product_template->set_variation_data($variation, $parsed_data);

        $variation->set_price(10);
        $variation->save();

        /**
         * @var \WC_Product_Variable $final_parent
         */
        $final_parent = wc_get_product($parent->get_id());

        /**
         * @var \WC_Product_Variation $final_child
         */
        $final_child = wc_get_product($variation->get_id());

        // Confirm parent link
        $this->assertEquals($final_parent->get_id(), $final_child->get_parent_id());

        /**
         * @var \WC_Product_Attribute[] $parent_attributes
         */
        $parent_attributes = $final_parent->get_attributes();

        $this->assertEquals('Color', $parent_attributes['color']->get_name());
        $this->assertEquals(['red'], $parent_attributes['color']->get_options());
        $this->assertFalse($parent_attributes['color']->is_taxonomy());
        $this->assertTrue($parent_attributes['color']->get_variation());
        $this->assertTrue($parent_attributes['color']->get_visible());

        $this->assertEquals('Size', $parent_attributes['size']->get_name());
        $this->assertEquals(['sm'], $parent_attributes['size']->get_options());
        $this->assertFalse($parent_attributes['size']->is_taxonomy());
        $this->assertTrue($parent_attributes['size']->get_variation());
        $this->assertFalse($parent_attributes['size']->get_visible());

        $this->assertArrayNotHasKey('shape', $parent_attributes);

        $this->assertCount(2, $parent_attributes);

        $variation_attributes = $final_parent->get_variation_attributes();
        $this->assertEquals(['red'], $variation_attributes['Color']);
        $this->assertEquals(['sm'], $variation_attributes['Size']);
        $this->assertCount(2, $variation_attributes);
    }

    public function test_set_variation_data_global_attribute()
    {
        // generate test products
        $parent = $this->mock_product('variable');
        $parent->set_sku('ASD123');
        $parent->save();

        $variation = $this->mock_product('variation');

        // mock objects
        $event_handler = new EventHandler();

        /**
         * @var \PHPUnit\Framework\MockObject\MockObject|ParsedData $parsed_data
         */
        $parsed_data = $this->createMock(ParsedData::class);

        $parsed_data->method('getData')
            ->with($this->equalTo('attributes'))
            ->willReturn([
                'attributes._index' => 3,
                'attributes.0.name' => 'Color',
                'attributes.0.terms' => 'red',
                'attributes.0.global' => 'yes',
                'attributes.0.visible' => 'yes',
                'attributes.0.variation' => 'yes',
                'attributes.1.name' => 'Size',
                'attributes.1.terms' => 'sm',
                'attributes.1.global' => 'yes',
                'attributes.1.visible' => 'no',
                'attributes.1.variation' => '',
                'attributes.2.name' => 'Shape',
                'attributes.2.terms' => 'square, circle',
                'attributes.2.global' => 'yes',
                'attributes.2.visible' => 'yes',
                'attributes.2.variation' => 'no',
            ]);

        $parsed_data->method('getValue')
            ->with('post_parent', 'advanced')
            ->willReturn($parent->get_id());


        $product_template = new ProductTemplate($event_handler);
        $product_template->set_variation_data($variation, $parsed_data);

        $variation->set_price(10);
        $variation->save();

        /**
         * @var \WC_Product_Variable $final_parent
         */
        $final_parent = wc_get_product($parent->get_id());

        /**
         * @var \WC_Product_Variation $final_child
         */
        $final_child = wc_get_product($variation->get_id());

        // Confirm parent link
        $this->assertEquals($final_parent->get_id(), $final_child->get_parent_id());

        /**
         * @var \WC_Product_Attribute[] $parent_attributes
         */
        $parent_attributes = $final_parent->get_attributes();
        $this->assertEquals('pa_color', $parent_attributes['pa_color']->get_name());
        $this->assertGreaterThan(0, $parent_attributes['pa_color']->get_options()[0]);
        $this->assertTrue($parent_attributes['pa_color']->is_taxonomy());
        $this->assertTrue($parent_attributes['pa_color']->get_variation());

        $this->assertEquals('pa_size', $parent_attributes['pa_size']->get_name());
        $this->assertGreaterThan(0, $parent_attributes['pa_size']->get_options()[0]);
        $this->assertTrue($parent_attributes['pa_size']->is_taxonomy());
        $this->assertTrue($parent_attributes['pa_size']->get_variation());

        $this->assertArrayNotHasKey('pa_shape', $parent_attributes);

        $this->assertCount(2, $parent_attributes);

        $variation_attributes = $final_parent->get_variation_attributes();
        $this->assertEquals(['red'], $variation_attributes['pa_color']);
        $this->assertEquals(['sm'], $variation_attributes['pa_size']);
        $this->assertCount(2, $variation_attributes);
    }

    public function test_get_product_id_by_field()
    {
    }

    public function test_get_product_id_by_sku()
    {
    }
    public function test_get_product_id_by_name()
    {
    }
    public function test_get_product_id_by_slug()
    {
    }
}
