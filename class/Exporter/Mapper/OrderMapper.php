<?php

namespace ImportWPAddon\WooCommerce\Exporter\Mapper;

use ImportWP\Common\Exporter\ExporterRecord;
use ImportWP\Common\Exporter\Mapper\AbstractMapper;
use ImportWP\Common\Exporter\MapperInterface;

class OrderMapper extends AbstractMapper implements MapperInterface
{
    public function get_fields()
    {
        $fields = [
            'key' => 'main',
            'label' => __('Order', 'importwp'),
            'loop' => true,
            'fields' => [
                'ID',
                '_order_key',
            ],
            'children' => [
                'order' => [
                    'key' => 'order',
                    'label' => __('Order Fields', 'importwp'),
                    'loop' => false,
                    'fields' => [
                        'ID',
                        '_order_key',
                        'status',
                        'currency',
                        'date_created',
                        'customer_note',
                        'payment_method',
                        'payment_method_title',
                        'transaction_id',
                    ],
                    'children' => [],
                ],
                'customer' => [
                    'key' => 'customer',
                    'label' => __('Customer', 'importwp'),
                    'loop' => false,
                    'fields' => [
                        'id',
                        'email',
                        'login',
                    ],
                    'children' => [],
                ],
                'billing' => [
                    'key' => 'billing',
                    'label' => __('Billing Address', 'importwp'),
                    'loop' => false,
                    'fields' => [
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
                    ],
                    'children' => [],
                ],
                'shipping' => [
                    'key' => 'shipping',
                    'label' => __('Shipping Address', 'importwp'),
                    'loop' => false,
                    'fields' => [
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
                    ],
                    'children' => [],
                ],
                'line_items' => [
                    'key' => 'line_items',
                    'label' => __('Line Items', 'importwp'),
                    'loop' => true,
                    'fields' => [
                        'product',
                        'product_id',
                        'quantity',
                        'total',
                        'name',
                    ],
                    'children' => [],
                ],
                'order_totals' => [
                    'key' => 'order_totals',
                    'label' => __('Order Totals', 'importwp'),
                    'loop' => false,
                    'fields' => [
                        'shipping_total',
                        'discount_total',
                        'cart_tax',
                        'total',
                    ],
                    'children' => [],
                ],
            ],
        ];

        $fields = apply_filters('iwp/exporter/woocommerce_order/fields', $fields);

        return $this->parse_fields($fields);
    }

    public function have_records($exporter_id)
    {
        $query_args = [
            'limit' => -1,
            'return' => 'ids',
            'type' => 'shop_order',
            'status' => array_keys(wc_get_order_statuses()),
        ];

        $query_args = apply_filters('iwp/exporter/woocommerce_order/order_query', $query_args);
        $query_args = apply_filters(sprintf('iwp/exporter/%d/woocommerce_order/order_query', $exporter_id), $query_args);

        $this->items = wc_get_orders($query_args);

        return $this->found_records() > 0;
    }

    public function found_records()
    {
        return count($this->items);
    }

    public function setup($i)
    {
        $order = wc_get_order($this->items[$i]);
        if (!$order) {
            return false;
        }

        $customer_id = $order->get_customer_id();
        $customer_email = '';
        $customer_login = '';
        if ($customer_id > 0) {
            $user = get_user_by('id', $customer_id);
            if ($user) {
                $customer_email = $user->user_email;
                $customer_login = $user->user_login;
            }
        }
        if ($customer_email === '') {
            $customer_email = $order->get_billing_email();
        }

        $date_created = $order->get_date_created();

        $line_items = [];
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            $line_items[] = [
                'product' => $product ? $product->get_sku() : '',
                'product_id' => $item->get_product_id(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'name' => $item->get_name(),
            ];
        }

        $this->record = new ExporterRecord([
            'ID' => $order->get_id(),
            '_order_key' => $order->get_order_key(),
            'order' => [
                'ID' => $order->get_id(),
                '_order_key' => $order->get_order_key(),
                'status' => $order->get_status(),
                'currency' => $order->get_currency(),
                'date_created' => $date_created ? $date_created->date('Y-m-d H:i:s') : '',
                'customer_note' => $order->get_customer_note(),
                'payment_method' => $order->get_payment_method(),
                'payment_method_title' => $order->get_payment_method_title(),
                'transaction_id' => $order->get_transaction_id(),
            ],
            'customer' => [
                'id' => $customer_id,
                'email' => $customer_email,
                'login' => $customer_login,
            ],
            'billing' => [
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'state' => $order->get_billing_state(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ],
            'shipping' => [
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
                'state' => $order->get_shipping_state(),
                'phone' => $order->get_shipping_phone(),
            ],
            'line_items' => $line_items,
            'order_totals' => [
                'shipping_total' => $order->get_shipping_total(),
                'discount_total' => $order->get_discount_total(),
                'cart_tax' => $order->get_cart_tax(),
                'total' => $order->get_total(),
            ],
        ], 'woocommerce_order');

        $this->record = apply_filters('iwp/exporter/woocommerce_order/setup_data', $this->record, 'woocommerce_order');

        return true;
    }
}
