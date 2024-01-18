<?php

namespace ImportWPAddon\WooCommerce\Exporter\Mapper;

use ImportWP\Common\Exporter\Mapper\PostMapper;

class ProductMapper extends PostMapper
{
    public function __construct($post_type = 'post')
    {
        parent::__construct($post_type);

        add_filter('iwp/exporter/post_type/custom_field_list',  [$this, 'remove_custom_fields'], 10, 2);
        add_filter('iwp/exporter/post_type/fields', [$this, 'remove_taxonomy_fields'], 10, 2);
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
                'product_type',
                'weight',
                'length',
                'width',
                'height',
                'total_sales',
                'tax_status',
                'tax_class',
                'visibility',
                'manage_stock',
                'backorders',
                'sold_individually',
                'virtual',
                'downloadable',
                'download_limit',
                'download_expiry',
                'stock',
                'stock_status',
                'low_stock_amount',
                'average_rating',
                'review_count',
                'regular_price',
                'sale_price',
                'date_on_sale_from',
                'date_on_sale_to',
                'purchase_note',
                'product_url',
                'button_text',
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

        // Downloadable files
        $fields['children']['downloadable_files'] = [
            'key' => 'downloadable_files',
            'label' => 'Downloadable',
            'loop' => false,
            'fields' => [
                'name', 'file'
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
            'total_sales' => $product->get_total_sales(),
            'tax_status' => $product->get_tax_status(),
            'tax_class' => $product->get_tax_class(),
            'visibility' => $product->get_catalog_visibility(),
            'manage_stock' => $product->get_manage_stock(),
            'backorders' => $product->get_backorders(),
            'sold_individually' => $product->get_sold_individually(),
            'virtual' => $product->get_virtual(),
            'downloadable' => $product->get_downloadable(),
            'download_limit' => $product->get_download_limit(),
            'download_expiry' => $product->get_download_expiry(),
            'stock' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'low_stock_amount' => $product->get_low_stock_amount(),
            'average_rating' => $product->get_average_rating(),
            'review_count' => $product->get_review_count(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'date_on_sale_from' => $product->get_date_on_sale_from(),
            'date_on_sale_to' => $product->get_date_on_sale_to(),
            'purchase_note' => $product->get_purchase_note(),
            'product_url' => '',
            'button_text' => ''
        ];

        if (is_a($product, '\WC_Product_External')) {

            /**
             * @var \WC_Product_External $product
             */
            $this->record['woocommerce'] = array_merge($this->record['woocommerce'], [
                'product_url' => $product->get_product_url(),
                'button_text' => $product->get_button_text(),
            ]);
        }

        // core product sku, added here to show in unique identifier field
        $this->record['sku'] = $product->get_sku();

        // extra parent fields
        $this->record['parent']['sku'] = '';
        if ($this->record['parent']['id'] > 0) {
            $this->record['parent']['sku'] = get_post_meta($this->record['parent']['id'], '_sku', true);
        }

        // Product attributes
        $this->record['product_attributes'] = [];

        $attributes = $product->get_attributes();
        if (!empty($attributes)) {
            $tmp = [];
            foreach ($attributes as $attribute_id => $attribute_data) {

                /**
                 * @var \WC_Product_Attribute|string $attribute_data
                 */

                if ($attribute_data instanceof \WC_Product_Attribute) {

                    $tmp[sprintf('%s::name', $attribute_id)] = '';
                    $tmp[sprintf('%s::visible', $attribute_id)] = $attribute_data->get_visible() ? 'yes' : 'no';
                    $tmp[sprintf('%s::variation', $attribute_id)] = $attribute_data->get_variation() ? 'yes' : 'no';

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
        }

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

        if (!empty($gallery)) {
            foreach ($gallery as $thumbnail_id) {

                $attachment = get_post($thumbnail_id, ARRAY_A);
                $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);

                $product_gallery[] = [
                    'id' => $thumbnail_id,
                    'url' => wp_get_attachment_url($thumbnail_id),
                    'title' => $attachment['post_title'],
                    'alt' => $alt,
                    'caption' => $attachment['post_excerpt'],
                    'description' => $attachment['post_content']
                ];
            }

            $this->record['product_gallery'] = $product_gallery;
        }

        // Downloadable files
        /**
         * @var \WC_Product_Download[] $downloads
         */
        $downloads = $product->get_downloads();
        $this->record['downloadable_files'] = [];
        if (!empty($downloads)) {

            $downloadable_files = [];
            foreach ($downloads as $download) {
                $downloadable_files[] = [
                    'name' => $download->get_name(),
                    'file' => $download->get_file()
                ];
            }
            $this->record['downloadable_files'] = $downloadable_files;
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

    /**
     * Remove WooCommerce fields from custom field list
     * 
     * @param string[] $fields 
     * @param string[] $post_types 
     * @return string[] 
     */
    public function remove_custom_fields($fields, $post_types)
    {

        if ($post_types !== $this->post_type) {
            return $fields;
        }

        $cf_fields_to_remove = [
            'total_sales',
            '_tax_status',
            '_tax_class',
            '_manage_stock',
            '_backorders',
            '_sold_individually',
            '_virtual',
            '_downloadable',
            '_download_limit',
            '_download_expiry',
            '_stock',
            '_stock_status',
            '_wc_average_rating',
            '_wc_review_count',
            '_product_version',
            'downloads',
            '_downloads',
            '_sku',
            '_regular_price',
            '_product_image_gallery',
            '_price',
            '_children',
            '_product_url',
            '_product_attributes',
            '_variation_description',
            'attribute_pa_colour',
            '_downloadable_files',
        ];

        $fields = array_filter($fields, function ($item) use ($cf_fields_to_remove) {
            return !in_array($item, $cf_fields_to_remove);
        });

        return $fields;
    }

    /**
     * Hide woocommerce attribute taxonomies from taxonomy list
     * 
     * @param string[] $fields 
     * @param string[] $post_types 
     * @return string[] 
     */
    public function remove_taxonomy_fields($fields, $post_types)
    {
        if ($post_types !== $this->post_type) {
            return $fields;
        }

        $attribute_taxonomies = wc_get_attribute_taxonomy_names();

        foreach ($attribute_taxonomies as $attribute_taxonomy) {

            $key = sprintf('tax_%s', $attribute_taxonomy);
            if (isset($fields['children'][$key])) {
                unset($fields['children'][$key]);
            }
        }

        return $fields;
    }
}
