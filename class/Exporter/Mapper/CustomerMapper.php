<?php

namespace ImportWPAddon\WooCommerce\Exporter\Mapper;

use ImportWP\Common\Exporter\Mapper\UserMapper;

class CustomerMapper extends UserMapper
{
    public function __construct()
    {
        parent::__construct();

        add_filter('iwp/exporter/user/custom_field_list', [$this, 'remove_address_custom_fields'], 10, 2);
    }

    public function get_fields()
    {
        $fields = parent::get_fields();

        $fields['label'] = __('Customer', 'importwp');

        $billing_fields = [
            'first_name',
            'last_name',
            'company',
            'address_1',
            'address_2',
            'city',
            'postcode',
            'country',
            'state',
            'email',
            'phone',
        ];

        $shipping_fields = [
            'first_name',
            'last_name',
            'company',
            'address_1',
            'address_2',
            'city',
            'postcode',
            'country',
            'state',
            'phone',
        ];

        $fields['children']['billing'] = [
            'key' => 'billing',
            'label' => __('Billing Address', 'importwp'),
            'loop' => false,
            'fields' => $billing_fields,
            'children' => [],
        ];

        $fields['children']['shipping'] = [
            'key' => 'shipping',
            'label' => __('Shipping Address', 'importwp'),
            'loop' => false,
            'fields' => $shipping_fields,
            'children' => [],
        ];

        return $this->parse_fields($fields);
    }

    public function have_records($exporter_id)
    {
        $query_args = [
            'role' => 'customer',
            'number' => -1,
            'fields' => 'ids',
        ];

        $query_args = apply_filters('iwp/exporter/woocommerce_customer/user_query', $query_args);
        $query_args = apply_filters(sprintf('iwp/exporter/%d/woocommerce_customer/user_query', $exporter_id), $query_args);

        $query = new \WP_User_Query($query_args);
        $this->items = $query->get_results();

        return $this->found_records() > 0;
    }

    public function setup($i)
    {
        $is_setup = parent::setup($i);
        if (!$is_setup) {
            return $is_setup;
        }

        $customer = new \WC_Customer($this->items[$i]);

        $this->record['billing'] = [
            'first_name' => $customer->get_billing_first_name(),
            'last_name' => $customer->get_billing_last_name(),
            'company' => $customer->get_billing_company(),
            'address_1' => $customer->get_billing_address_1(),
            'address_2' => $customer->get_billing_address_2(),
            'city' => $customer->get_billing_city(),
            'postcode' => $customer->get_billing_postcode(),
            'country' => $customer->get_billing_country(),
            'state' => $customer->get_billing_state(),
            'email' => $customer->get_billing_email(),
            'phone' => $customer->get_billing_phone(),
        ];

        $this->record['shipping'] = [
            'first_name' => $customer->get_shipping_first_name(),
            'last_name' => $customer->get_shipping_last_name(),
            'company' => $customer->get_shipping_company(),
            'address_1' => $customer->get_shipping_address_1(),
            'address_2' => $customer->get_shipping_address_2(),
            'city' => $customer->get_shipping_city(),
            'postcode' => $customer->get_shipping_postcode(),
            'country' => $customer->get_shipping_country(),
            'state' => $customer->get_shipping_state(),
            'phone' => $customer->get_shipping_phone(),
        ];

        return $is_setup;
    }

    /**
     * Hide billing/shipping meta from custom fields (exported in dedicated groups).
     *
     * @param string[] $fields
     * @param mixed $unused
     * @return string[]
     */
    public function remove_address_custom_fields($fields, $unused = null)
    {
        $fields = array_filter($fields, function ($item) {
            return strpos($item, 'billing_') !== 0 && strpos($item, 'shipping_') !== 0;
        });

        return array_values($fields);
    }
}
