<?php

use ImportWP\Common\Importer\ParsedData;
use ImportWPAddon\WooCommerce\Importer\Mapper\ProductMapper;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;

add_action('iwp/register_events', function ($event_handler) {
    $event_handler->listen('templates.register', 'iwp_woocommerce_register_templates');
    $event_handler->listen('mappers.register', 'iwp_woocommerce_register_mappers');
    $event_handler->listen('template.post_process', 'iwp_woocommerce_register_template_post_process');
});

/**
 * Remove default woocommerce category on insert when other categories have been added.
 *
 * @param int $post_id
 * @param ParsedData $data
 * @param ProductTemplate $template
 * @return void
 */
function iwp_woocommerce_register_template_post_process($post_id, $data, $template)
{
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
    return $templates;
}

function iwp_woocommerce_register_mappers($mappers)
{
    $mappers['woocommerce-product'] = ProductMapper::class;
    return $mappers;
}
