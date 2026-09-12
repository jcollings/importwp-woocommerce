<?php

namespace ImportWPAddon\WooCommerce\Importer\Mapper;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\Mapper\PostMapper;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\ParsedData;

class OrderMapper extends PostMapper implements MapperInterface
{
    /**
     * Only used for backwards compatibility with ImportWP
     * @var string[]
     */
    protected $_unique_fields = ['ID', '_order_key'];

    public function create_post($post, ParsedData $data)
    {
        $order = wc_create_order([
            'status' => 'pending',
            'created_via' => 'importwp',
        ]);

        if (is_wp_error($order)) {
            return $order;
        }

        if (!$order) {
            return new \WP_Error('woocommerce_order_importer_create_failed', __('Unable to create order.', 'importwp'));
        }

        $status = $data->getValue('order.status', 'order');
        if (!empty($status)) {
            $order->set_status($status);
        }

        $currency = $data->getValue('order.currency', 'order');
        if (!empty($currency)) {
            $order->set_currency($currency);
        }

        $order_key = $data->getValue('order._order_key', 'order');
        if (!empty($order_key)) {
            $order->set_order_key($order_key);
        }

        $order->save();

        return $order->get_id();
    }

    public function exists(ParsedData $data)
    {
        list($unique_fields, $meta_args, $has_unique_field) = $this->exists_get_identifier($data);

        if (!$has_unique_field) {
            foreach ($unique_fields as $field) {
                $unique_value = $this->find_unique_field_in_data($data, $field);
                if ($this->has_identifier_value($unique_value)) {
                    $has_unique_field = true;

                    if ($field === 'ID') {
                        $order = wc_get_order(absint($unique_value));
                        if ($order) {
                            $this->ID = $order->get_id();
                            $this->set_unique_identifier_settings($field, $unique_value);
                            return $this->ID;
                        }
                        return false;
                    }

                    if ($field === '_order_key') {
                        $orders = wc_get_orders([
                            'limit' => 2,
                            'return' => 'ids',
                            'order_key' => $unique_value,
                        ]);

                        if (count($orders) > 1) {
                            throw new MapperException(sprintf(__('Record is not unique: %s, Matching Ids: (%s).', 'jc-importer'), $field, implode(', ', $orders)));
                        }

                        if (count($orders) === 1) {
                            $this->ID = $orders[0];
                            $this->set_unique_identifier_settings($field, $unique_value);
                            return $this->ID;
                        }

                        return false;
                    }

                    break;
                }
            }
        }

        if (!$has_unique_field) {
            throw new MapperException(__('No unique identifier value present. Check that Permissions has a unique identifier set and this row has a non-empty value.', 'jc-importer'));
        }

        return parent::exists($data);
    }
}
