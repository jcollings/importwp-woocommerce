<?php

namespace ImportWPAddon\WooCommerce\Importer\Mapper;

use ImportWP\Common\Importer\Mapper\PostMapper;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\ParsedData;

class ProductMapper extends PostMapper implements MapperInterface
{
    protected $_unique_fields = ['ID', '_sku', 'post_name'];

    public function create_post($post, ParsedData $data)
    {
        $product_type = $data->getValue('post.product_type', 'post');
        $object_args = [];

        if ($product_type) {
            $allowed_product_types = array_keys(wc_get_product_types());
            $product_types = explode(',', $product_type);
            if (count($product_types) > 1) {
                $product_types = array_filter(array_map('trim', $product_types));
                foreach ($product_types as $p_type) {
                    if (in_array($p_type, $allowed_product_types)) {
                        $object_args['type'] = $p_type;
                        break;
                    }
                }
            } else {
                $object_args['type'] = $product_type;
            }
        }

        $product = $this->get_product_object($object_args);
        if (is_wp_error($product)) {
            return $product;
        }

        $wc_data_keys = [
            'post_title',
            'post_name',
            'post_content',
            'post_excerpt',
            'post_status'
        ];

        $wc_data = [];
        foreach ($wc_data_keys as $key) {
            $wc_data[$key] = $data->getValue('post.' . $key, 'post');
        }

        if ($data->permission()) {
            $wc_data = $data->permission()->validate($wc_data, $data->getMethod(), 'product');
        }

        if (!isset($wc_data['post_title'])) {
            return new \WP_Error('IWP_WC_01', 'Unable to insert product (product title is required).');
        }

        if (isset($wc_data['post_title']) && !empty($wc_data['post_title'])) {
            $product->set_name($wc_data['post_title']);
        }
        if (isset($wc_data['post_content']) && !empty($wc_data['post_content'])) {
            $product->set_description($wc_data['post_content']);
        }
        if (isset($wc_data['post_excerpt']) && !empty($wc_data['post_excerpt'])) {
            $product->set_short_description($wc_data['post_excerpt']);
        }
        if (isset($wc_data['post_status']) && !empty($wc_data['post_status'])) {
            $product->set_status($wc_data['post_status']);
        }
        if (isset($wc_data['post_name']) && !empty($wc_data['post_name'])) {
            $product->set_slug($wc_data['post_name']);
        }

        $product->save();

        return $product->get_id();
    }

    /**
     * @param $data
     *
     * @return false|\WC_Product|\WC_Product_Simple|\WP_Error|null
     */
    public function get_product_object($data)
    {

        $id = isset($data['ID']) ? absint($data['ID']) : 0;

        // Type is the most important part here because we need to be using the correct class and methods.
        if (isset($data['type'])) {
            $types   = array_keys(wc_get_product_types());
            $types[] = 'variation';

            if (!in_array($data['type'], $types, true)) {
                return new \WP_Error('woocommerce_product_importer_invalid_type', __('Invalid product type.', 'woocommerce'), array('status' => 401));
            }

            $classname = \WC_Product_Factory::get_classname_from_product_type($data['type']);

            if (!class_exists($classname)) {
                $classname = 'WC_Product_Simple';
            }

            $product = new $classname($id);
        } elseif (!empty($data['id'])) {
            $product = wc_get_product($id);

            if (!$product) {
                return new \WP_Error(
                    'woocommerce_product_csv_importer_invalid_id',
                    /* translators: %d: product ID */
                    sprintf(__('Invalid product ID %d.', 'woocommerce'), $id),
                    array(
                        'id'     => $id,
                        'status' => 401,
                    )
                );
            }
        } else {
            $product = new \WC_Product_Simple($id);
        }

        return $product;
    }
}
