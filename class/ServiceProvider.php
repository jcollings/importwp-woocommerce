<?php

namespace ImportWPAddon\WooCommerce;

use ImportWPAddon\WooCommerce\Importer\Mapper\ProductMapper;
use ImportWPAddon\WooCommerce\Importer\Template\ProductTemplate;

class ServiceProvider extends \ImportWP\ServiceProvider
{
    public function __construct($event_handler)
    {
        $event_handler->listen('templates.register', [$this, 'register_templates']);
        $event_handler->listen('mappers.register', [$this, 'register_mappers']);
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
