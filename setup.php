<?php

use ImportWP\Common\Importer\ParsedData;
use ImportWPAddon\WooCommerce\Importer\Mapper\CustomerMapper;
use ImportWPAddon\WooCommerce\Importer\Mapper\OrderMapper;
use ImportWPAddon\WooCommerce\Importer\Mapper\ProductMapper;
use ImportWPAddon\WooCommerce\Importer\Template\CustomerTemplate;
use ImportWPAddon\WooCommerce\Importer\Template\OrderTemplate;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;

// TODO: Try disabling term recount to speed up import: 'woocommerce_product_recount_terms'

add_action('iwp/register_events', function ($event_handler) {
    $event_handler->listen('templates.register', 'iwp_woocommerce_register_templates');
    $event_handler->listen('mappers.register', 'iwp_woocommerce_register_mappers');
    $event_handler->listen('template.post_process', 'iwp_woocommerce_register_template_post_process');
});

/**
 * Remove default woocommerce category on insert when other categories have been added.
 * Apply WooCommerce customer billing/shipping addresses after user import.
 *
 * @param int $post_id
 * @param ParsedData $data
 * @param mixed $template
 * @return void
 */
function iwp_woocommerce_register_template_post_process($post_id, $data, $template)
{
    if ($template instanceof CustomerTemplate) {
        $template->apply_customer_addresses($post_id, $data);
        return $post_id;
    }

    if (!($template instanceof ProductTemplate)) {
        return;
    }

    // check importer product categories
    $tax = 'product_cat';
    $imported_taxonomies = $template->get_importer_taxonomies();
    $product_cats = isset($imported_taxonomies[$tax]) ? $imported_taxonomies[$tax] : [];
    if (!empty($product_cats)) {
        $default_product_cat = intval(get_option('default_product_cat'));
        $terms = wp_get_object_terms($post_id, $tax);

        if (count($terms) >= count($product_cats)) {
            $found = false;
            foreach ($terms as $i => $term) {
                if ($term->term_id === intval($default_product_cat)) {
                    $found = true;
                }
            }

            if ($found === true) {
                wp_remove_object_terms($post_id, $default_product_cat, $tax, true);
            }
        }
    }

    return $post_id;
}

function iwp_woocommerce_register_templates($templates)
{
    $templates['woocommerce-product'] = ProductTemplate::class;
    $templates['woocommerce-customer'] = CustomerTemplate::class;
    $templates['woocommerce-order'] = OrderTemplate::class;
    return $templates;
}

function iwp_woocommerce_register_mappers($mappers)
{
    $mappers['woocommerce-product'] = ProductMapper::class;
    $mappers['woocommerce-customer'] = CustomerMapper::class;
    $mappers['woocommerce-order'] = OrderMapper::class;
    return $mappers;
}

function iwp_woocommerce_mapper_unique_fields($unique_fields, $mapper_id)
{
    if ($mapper_id == 'woocommerce-product') {
        return ['ID', '_sku', 'post_name'];
    }

    if ($mapper_id == 'woocommerce-customer') {
        return ['ID', 'user_email', 'user_login'];
    }

    if ($mapper_id == 'woocommerce-order') {
        return ['ID', '_order_key'];
    }

    return $unique_fields;
}
add_filter('iwp/mapper/unique_fields', 'iwp_woocommerce_mapper_unique_fields', 10, 2);

/**
 * Add WooCommerce plugin to compatability whitelist
 * 
 * @param string[] $plugins 
 * @return string[] 
 */
function iwp_woocommerce_compat_whitelist($plugins)
{
    $plugins[] = 'woocommerce/woocommerce.php';
    return $plugins;
}

add_filter('iwp/compat/whitelist', 'iwp_woocommerce_compat_whitelist');
