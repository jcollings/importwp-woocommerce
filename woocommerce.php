<?php

/**
 * Plugin Name: Import WP - WooCommerce Importer Addon
 * Plugin URI: https://www.importwp.com
 * Description: Allow Import WP to import WooCommerce Products.
 * Author: James Collings <james@jclabs.co.uk>
 * Version: 2.2.1 
 * Author URI: https://www.importwp.com
 * Network: True
 */

if (!defined('IWP_WC_MIN_CORE_VERSION')) {
    define('IWP_WC_MIN_CORE_VERSION', '2.5.0');
}

add_action('admin_init', 'iwp_woocommerce_check');

function iwp_woocommerce_requirements_met()
{
    return false === (is_admin() && current_user_can('activate_plugins') &&  (!class_exists('WooCommerce') || (!function_exists('import_wp_pro') && !function_exists('import_wp')) || version_compare(IWP_VERSION, IWP_WC_MIN_CORE_VERSION, '<')));
}

function iwp_woocommerce_check()
{
    if (!iwp_woocommerce_requirements_met()) {

        add_action('admin_notices', 'iwp_woocommerce_notice');

        deactivate_plugins(plugin_basename(__FILE__));

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

function iwp_woocommerce_setup()
{
    if (!iwp_woocommerce_requirements_met()) {
        return;
    }

    $base_path = dirname(__FILE__);

    require_once $base_path . '/class/autoload.php';
    require_once $base_path . '/setup.php';
    require_once $base_path . '/exporter.php';

    // Install updater
    if (file_exists($base_path . '/updater.php') && !class_exists('IWP_Updater')) {
        require_once $base_path . '/updater.php';
    }

    if (class_exists('IWP_Updater')) {
        $updater = new IWP_Updater(__FILE__, 'importwp-woocommerce');
        $updater->initialize();
    }
}
add_action('plugins_loaded', 'iwp_woocommerce_setup', 9);

function iwp_woocommerce_notice()
{
    echo '<div class="error">';
    echo '<p><strong>Import WP - WooCommerce Importer Addon</strong> requires that you have <strong>Import WP v' . IWP_WC_MIN_CORE_VERSION . ' or newer</strong>, and <strong>WooCommerce</strong> installed.</p>';
    echo '</div>';
}
