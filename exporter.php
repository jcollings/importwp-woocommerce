<?php

use ImportWPAddon\WooCommerce\Exporter\Mapper\ProductMapper;

/**
 * Add WooCommerce Products to exporting field list.
 */
add_filter('iwp/exporter/export_field_list', function ($fields) {

    $mapper = new ProductMapper('product,product_variation');

    array_unshift($fields, [
        'id' => 'woocommerce_product',
        'label' => 'WooCommerce Products',
        'fields' => $mapper->get_fields()
    ]);

    return $fields;
});

/**
 * Load ProductMapper when exporting data with the id: woocommerce_product.
 */
add_filter('iwp/exporter/load_mapper', function ($result, $type) {

    if ($type === 'woocommerce_product') {
        return new ProductMapper(['product', 'product_variation']);
    }

    return $result;
}, 10, 2);
