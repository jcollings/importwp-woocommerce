<?php

// Register Product Template

use ImportWPAddon\WooCommerce\Importer\Mapper\ProductMapper;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;

add_filter('iwp/templates/register', function ($templates) {
    $templates['woocommerce-product'] = ProductTemplate::class;
    return $templates;
});

// Register Product Mapper
add_filter('iwp/mappers/register', function ($mappers) {
    $mappers['woocommerce-product'] = ProductMapper::class;
    return $mappers;
});
