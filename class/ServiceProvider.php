<?php

namespace ImportWPAddon\WooCommerce;

use ImportWP\Common\Importer\ParsedData;
use ImportWPAddon\WooCommerce\Importer\Mapper\ProductMapper;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;

class ServiceProvider extends \ImportWP\ServiceProvider
{
    public function __construct($event_handler)
    {
        $event_handler->listen('templates.register', [$this, 'register_templates']);
        $event_handler->listen('mappers.register', [$this, 'register_mappers']);
        $event_handler->listen('template.post_process', [$this, 'register_template_post_process']);
    }

    /**
     * Remove default woocommerce category on insert when other categories have been added.
     *
     * @param int $post_id
     * @param ParsedData $data
     * @param ProductTemplate $template
     * @return void
     */
    public function register_template_post_process($post_id, $data, $template)
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

    public function register_templates($templates)
    {
        $templates['woocommerce-product'] = ProductTemplate::class;
        return $templates;
    }

    public function register_mappers($mappers)
    {
        $mappers['woocommerce-product'] = ProductMapper::class;
        return $mappers;
    }
}
