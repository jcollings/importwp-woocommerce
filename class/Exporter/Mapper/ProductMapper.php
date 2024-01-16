<?php

namespace ImportWPAddon\WooCommerce\Exporter\Mapper;

use ImportWP\Common\Exporter\Mapper\PostMapper;

class ProductMapper extends PostMapper
{
    public function __construct($post_type = 'post')
    {
        parent::__construct($post_type);
    }

    public function get_fields()
    {
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $fields = parent::get_fields();

        // Add sku to core field list
        $fields['fields'][] = 'sku';

        // product fields
        $fields['children']['woocommerce'] = [
            'key' => 'woocommerce',
            'label' => 'WooCommerce Product',
            'loop' => false,
            'fields' => [
                'product_type',
                'weight',
                'length',
                'width',
                'height',
            ],
            'children' => []
        ];

        // extra parent fields
        $fields['children']['parent']['fields'][] = 'sku';

        // TODO: Fetch all custom attributes
        $custom_attribute_rows = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_product_attributes' AND meta_value LIKE '%\"is_taxonomy\";i:0;%'");

        $tmp = [];


        // Fetch all non custom attributes
        $attribute_taxonomies = wc_get_attribute_taxonomy_names();

        if (!empty($attribute_taxonomies)) {
            foreach ($attribute_taxonomies as $attribute_tax) {
                $tmp[] = sprintf('%s::name', $attribute_tax);
                $tmp[] = sprintf('%s::visible', $attribute_tax);
                $tmp[] = sprintf('%s::variation', $attribute_tax);
            }
        }

        if (!empty($custom_attribute_rows)) {
            foreach ($custom_attribute_rows as $row) {
                $data = maybe_unserialize($row);
                foreach ($data as $attribute_id => $attribute_data) {
                    if ($attribute_data['is_taxonomy'] == 0 && !in_array($attribute_id, $tmp)) {
                        $tmp[] = $attribute_id;
                    }
                }
            }
        }

        $fields['children']['product_attributes'] = [
            'key' => 'product_attributes',
            'label' => 'Product Attributes',
            'loop' => false,
            'fields' => $tmp,
            'children' => []
        ];

        // Linked Products
        $fields['children']['linked_products'] = [
            'key' => 'linked_products',
            'label' => 'Linked Products',
            'loop' => false,
            'fields' => [
                'grouped::id',
                'grouped::name',
                'grouped::slug',
                'grouped::sku',
                'upsells::id',
                'upsells::name',
                'upsells::slug',
                'upsells::sku',
                'crosssells::id',
                'crosssells::name',
                'crosssells::slug',
                'crosssells::sku',
            ],
            'children' => []
        ];

        // Product Gallery
        $fields['children']['product_gallery'] = [
            'key' => 'product_gallery',
            'label' => 'Product Gallery',
            'loop' => false,
            'fields' => [
                'id', 'url', 'title', 'alt', 'caption', 'description'
            ],
            'children' => []
        ];

        return $fields;
    }


    public function setup($i)
    {
        $is_setup = parent::setup($i);

        $product = wc_get_product($this->record['ID']);

        // product fields
        $this->record['woocommerce'] = [
            'product_type' => $product->get_type(),
            'weight' => $product->get_weight(),
            'length' => $product->get_length(),
            'width' => $product->get_width(),
            'height' => $product->get_height(),
        ];

        // core product sku, added here to show in unique identifier field
        $this->record['sku'] = $product->get_sku();

        // extra parent fields
        $this->record['parent']['sku'] = '';
        if ($this->record['parent']['id'] > 0) {
            $this->record['parent']['sku'] = get_post_meta($this->record['parent']['id'], '_sku', true);
        }

        // Product attributes
        $this->record['product_attributes'] = [];

        $tmp = [];
        $attributes = $product->get_attributes();
        foreach ($attributes as $attribute_id => $attribute_data) {

            /**
             * @var \WC_Product_Attribute|string $attribute_data
             */

            if ($attribute_data instanceof \WC_Product_Attribute) {

                // returns int[]
                // $tmp[$attribute_id] = $attribute_data->get_options();

                // $tmp[$attribute_id] = [
                $tmp[sprintf('%s::name', $attribute_id)] = '';
                $tmp[sprintf('%s::visible', $attribute_id)] = $attribute_data->get_visible() ? 'yes' : 'no';
                $tmp[sprintf('%s::variation', $attribute_id)] = $attribute_data->get_variation() ? 'yes' : 'no';
                // 'visible' => $attribute_data->get_visible() ? 'yes' : 'no',
                // 'variation' => $attribute_data->get_variation() ? 'yes' : 'no',
                // ];

                $term_ids = $attribute_data->get_options();

                if (!empty($term_ids)) {

                    if (taxonomy_exists($attribute_id)) {

                        foreach ($term_ids as $term_id) {
                            $term = get_term_by('term_id', $term_id, $attribute_id);
                            if (!$term) {
                                continue;
                            }

                            $tmp[sprintf('%s::name', $attribute_id)] = $term->name;
                        }
                    } else {
                        $tmp[sprintf('%s::name', $attribute_id)] = $term_ids;
                    }
                }
            } else {
                $tmp[sprintf('%s::name', $attribute_id)] = $attribute_data;
            }
        }

        $this->record['product_attributes'] = $tmp;

        // Linked Products
        $this->record['linked_products'] = [];

        if ($product->is_type('grouped')) {
            $this->record['linked_products'] = array_merge(
                $this->record['linked_products'],
                $this->get_linked_product_data($product->get_children(), 'grouped')
            );
        }

        $this->record['linked_products'] = array_merge(
            $this->record['linked_products'],
            $this->get_linked_product_data($product->get_upsell_ids(), 'upsells')
        );

        $this->record['linked_products'] = array_merge(
            $this->record['linked_products'],
            $this->get_linked_product_data($product->get_cross_sell_ids(), 'crosssells')
        );

        // Product Gallery
        $gallery = $product->get_gallery_image_ids();
        $this->record['product_gallery'] = [];

        foreach ($gallery as $thumbnail_id) {

            $attachment = get_post($thumbnail_id, ARRAY_A);
            $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);

            $this->record['product_gallery'][] = [
                'id' => $thumbnail_id,
                'url' => wp_get_attachment_url($thumbnail_id),
                'title' => $attachment['post_title'],
                'alt' => $alt,
                'caption' => $attachment['post_excerpt'],
                'description' => $attachment['post_content']
            ];
        }

        return $is_setup;
    }

    public function get_linked_product_data($ids, $prefix)
    {
        $tmp = [
            "{$prefix}::id" => [],
            "{$prefix}::name" => [],
            "{$prefix}::slug" => [],
            "{$prefix}::sku" => [],
        ];

        if (!empty($ids)) {
            foreach ($ids as $child_id) {

                $child = wc_get_product($child_id);
                if (!$child) {
                    continue;
                }

                $tmp["{$prefix}::id"][] = $child->get_id();
                $tmp["{$prefix}::name"][] = $child->get_name();
                $tmp["{$prefix}::slug"][] = $child->get_slug();
                $tmp["{$prefix}::sku"][] = $child->get_sku();
            }
        }

        return $tmp;
    }
}
