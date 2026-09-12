<?php

use ImportWPAddon\WooCommerce\Exporter\Mapper\CustomerMapper;
use ImportWPAddon\WooCommerce\Exporter\Mapper\OrderMapper;
use ImportWPAddon\WooCommerce\Exporter\Mapper\ProductMapper;

/**
 * Add WooCommerce export types to the exporter field list.
 */
add_filter('iwp/exporter/export_field_list', function ($fields) {

    $product_mapper = new ProductMapper(['product', 'product_variation']);
    $customer_mapper = new CustomerMapper();
    $order_mapper = new OrderMapper();

    array_unshift($fields, [
        'id' => 'woocommerce_order',
        'label' => 'WooCommerce Orders',
        'fields' => $order_mapper->get_fields(),
    ]);

    array_unshift($fields, [
        'id' => 'woocommerce_customer',
        'label' => 'WooCommerce Customers',
        'fields' => $customer_mapper->get_fields(),
    ]);

    array_unshift($fields, [
        'id' => 'woocommerce_product',
        'label' => 'WooCommerce Products',
        'fields' => $product_mapper->get_fields(),
    ]);

    return $fields;
});

/**
 * Load WooCommerce exporter mappers.
 */
add_filter('iwp/exporter/load_mapper', function ($result, $type) {

    if ($type === 'woocommerce_product') {
        return new ProductMapper(['product', 'product_variation']);
    }

    if ($type === 'woocommerce_customer') {
        return new CustomerMapper();
    }

    if ($type === 'woocommerce_order') {
        return new OrderMapper();
    }

    return $result;
}, 10, 2);
