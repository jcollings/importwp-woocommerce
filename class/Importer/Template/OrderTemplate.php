<?php

namespace ImportWPAddon\WooCommerce\Importer\Template;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\Common\Util\Logger;
use ImportWP\EventHandler;

if (!class_exists(__NAMESPACE__ . '\\IWP_Base_PostTemplate', false)) {
    if (class_exists('ImportWP\\Pro\\Importer\\Template\\PostTemplate')) {
        class IWP_Base_PostTemplate extends \ImportWP\Pro\Importer\Template\PostTemplate
        {
        }
    } else {
        class IWP_Base_PostTemplate extends \ImportWP\Common\Importer\Template\PostTemplate
        {
        }
    }
}

class OrderTemplate extends IWP_Base_PostTemplate implements TemplateInterface
{
    protected $name = 'WooCommerce Orders';
    protected $mapper = 'woocommerce-order';

    public function __construct(EventHandler $event_handler)
    {
        parent::__construct($event_handler);

        $this->default_template_options['post_type'] = ['shop_order'];
        $this->default_template_options['unique_field'] = ['ID', '_order_key'];

        // Replace post/taxonomy groups with order-specific groups.
        // Keep custom_fields when Pro PostTemplate registered it.
        $groups = [
            'order',
            'customer',
            'billing',
            'shipping',
            'line_items',
            'order_totals',
        ];
        if (in_array('custom_fields', $this->groups, true)) {
            $groups[] = 'custom_fields';
        }
        $this->groups = $groups;
    }

    public function register()
    {
        $groups = [];

        $status_options = [];
        foreach (array_keys(wc_get_order_statuses()) as $status) {
            $status_options[] = [
                'value' => str_replace('wc-', '', $status),
                'label' => wc_get_order_status_name($status),
            ];
        }

        $groups[] = $this->register_group('Order Fields', 'order', [
            $this->register_field('Order ID', 'ID', [
                'tooltip' => __('Order ID is only used as a reference and can not be inserted.', 'importwp'),
            ]),
            $this->register_core_field('Order Key', '_order_key', [
                'tooltip' => __('Unique order key used to match existing orders on update.', 'importwp'),
            ]),
            $this->register_field('Status', 'status', [
                'options' => $status_options,
                'default' => 'pending',
            ]),
            $this->register_field('Currency', 'currency', [
                'default' => get_woocommerce_currency(),
            ]),
            $this->register_field('Date Created', 'date_created'),
            $this->register_field('Customer Note', 'customer_note'),
            $this->register_field('Payment Method', 'payment_method'),
            $this->register_field('Payment Method Title', 'payment_method_title'),
            $this->register_field('Transaction ID', 'transaction_id'),
        ]);

        $groups[] = $this->register_group('Customer', 'customer', [
            $this->register_group('Customer Settings', '_customer', [
                $this->register_field('Customer', 'customer', [
                    'tooltip' => __('Customer ID, email, or username depending on the field type.', 'importwp'),
                ]),
                $this->register_field('Customer Field Type', '_customer_type', [
                    'default' => 'email',
                    'options' => [
                        ['value' => 'id', 'label' => 'ID'],
                        ['value' => 'email', 'label' => 'Email'],
                        ['value' => 'login', 'label' => 'Username'],
                    ],
                    'type' => 'select',
                    'tooltip' => __('Select how the customer field should be handled', 'importwp'),
                ]),
            ]),
        ]);

        $groups[] = $this->register_group('Billing Address', 'billing', [
            $this->register_field('First Name', 'first_name'),
            $this->register_field('Last Name', 'last_name'),
            $this->register_field('Company', 'company'),
            $this->register_field('Address 1', 'address_1'),
            $this->register_field('Address 2', 'address_2'),
            $this->register_field('City', 'city'),
            $this->register_field('Postcode', 'postcode'),
            $this->register_field('Country', 'country'),
            $this->register_field('State', 'state'),
            $this->register_field('Email', 'email'),
            $this->register_field('Phone', 'phone'),
        ]);

        $groups[] = $this->register_group('Shipping Address', 'shipping', [
            $this->register_field('First Name', 'first_name'),
            $this->register_field('Last Name', 'last_name'),
            $this->register_field('Company', 'company'),
            $this->register_field('Address 1', 'address_1'),
            $this->register_field('Address 2', 'address_2'),
            $this->register_field('City', 'city'),
            $this->register_field('Postcode', 'postcode'),
            $this->register_field('Country', 'country'),
            $this->register_field('State', 'state'),
            $this->register_field('Phone', 'phone'),
        ]);

        $groups[] = $this->register_group('Line Items', 'line_items', [
            $this->register_field('Product', 'product', [
                'tooltip' => __('Product ID or SKU depending on the field type.', 'importwp'),
            ]),
            $this->register_field('Product Field Type', '_product_type', [
                'default' => 'sku',
                'options' => [
                    ['value' => 'id', 'label' => 'Product ID'],
                    ['value' => 'sku', 'label' => 'Product SKU'],
                ],
                'type' => 'select',
            ]),
            $this->register_field('Quantity', 'quantity', [
                'default' => '1',
            ]),
            $this->register_field('Total', 'total', [
                'tooltip' => __('Optional line total. Leave empty to use the product price.', 'importwp'),
            ]),
            $this->register_field('Name', 'name', [
                'tooltip' => __('Optional line item name override.', 'importwp'),
            ]),
        ], ['type' => 'repeatable', 'row_base' => true, 'row_summary' => ['product', 'quantity']]);

        $groups[] = $this->register_group('Order Totals', 'order_totals', [
            $this->register_field('Shipping Total', 'shipping_total'),
            $this->register_field('Discount Total', 'discount_total'),
            $this->register_field('Cart Tax', 'cart_tax'),
            $this->register_field('Order Total', 'total', [
                'tooltip' => __('Optional. Leave empty to calculate totals from line items.', 'importwp'),
            ]),
        ]);

        return $groups;
    }

    /**
     * Convert exporter headings into order importer field map.
     *
     * @param mixed $fields
     * @param \ImportWP\Common\Model\ImporterModel $importer
     * @return array
     */
    public function generate_field_map($fields, $importer)
    {
        // Do not use PostTemplate mapping — order headings are not post fields.
        $map = [];
        $enabled = [];
        $line_items = [];

        foreach ($fields as $index => $field) {
            if (preg_match('/^line_items\.(.*?)$/', $field, $matches) === 1) {
                $line_items[$matches[1]] = sprintf('{%s}', $index);
                continue;
            }

            if (preg_match('/^(order|customer|billing|shipping|order_totals)\.(.*?)$/', $field, $matches) === 1) {
                $group = $matches[1];
                $field_id = $matches[2];
                $field_key = $group . '.' . $field_id;
                $map[$field_key] = sprintf('{%s}', $index);

                if ($group === 'customer') {
                    if ($field_id === 'email') {
                        $map['customer._customer.customer'] = sprintf('{%s}', $index);
                        $map['customer._customer._customer_type'] = 'email';
                        $enabled[] = 'customer._customer';
                    } elseif ($field_id === 'id') {
                        if (!isset($map['customer._customer.customer'])) {
                            $map['customer._customer.customer'] = sprintf('{%s}', $index);
                            $map['customer._customer._customer_type'] = 'id';
                            $enabled[] = 'customer._customer';
                        }
                    } elseif ($field_id === 'login') {
                        if (!isset($map['customer._customer.customer'])) {
                            $map['customer._customer.customer'] = sprintf('{%s}', $index);
                            $map['customer._customer._customer_type'] = 'login';
                            $enabled[] = 'customer._customer';
                        }
                    }
                } elseif ($group === 'order_totals') {
                    $enabled[] = $field_key;
                } elseif ($group === 'order' && in_array($field_id, ['ID', 'status', 'currency', 'date_created', 'customer_note', 'payment_method', 'payment_method_title', 'transaction_id'], true)) {
                    $enabled[] = $field_key;
                } elseif (in_array($group, ['billing', 'shipping'], true)) {
                    $enabled[] = $field_key;
                }

                continue;
            }

            // Core unique fields exported at the root of the order mapper.
            if ($field === 'ID') {
                $map['order.ID'] = sprintf('{%s}', $index);
                $enabled[] = 'order.ID';
            } elseif ($field === '_order_key') {
                $map['order._order_key'] = sprintf('{%s}', $index);
            }
        }

        if (!empty($line_items)) {
            $map['line_items._index'] = 1;

            if (isset($line_items['product'])) {
                $map['line_items.0.product'] = $line_items['product'];
                $map['line_items.0._product_type'] = 'sku';
            } elseif (isset($line_items['product_id'])) {
                $map['line_items.0.product'] = $line_items['product_id'];
                $map['line_items.0._product_type'] = 'id';
            }

            foreach (['quantity', 'total', 'name'] as $line_field) {
                if (isset($line_items[$line_field])) {
                    $map['line_items.0.' . $line_field] = $line_items[$line_field];
                }
            }
        }

        return [
            'map' => $map,
            // Object map works with older Import WP RestManager; list form does not.
            'enabled' => array_fill_keys(array_values(array_unique($enabled)), true),
        ];
    }

    public function register_options()
    {
        // Orders use a fixed post type; skip the default post type selector.
        return [];
    }

    public function register_settings()
    {
        return [
            $this->register_field(__('Enable order notification emails.', 'importwp'), 'send_order_emails', [
                'type' => 'checkbox',
                'tooltip' => __('Send WooCommerce order emails to customers and admins when order status is set during import. Disabled by default.', 'importwp'),
            ]),
        ];
    }

    /**
     * @param ParsedData $data
     * @return ParsedData
     */
    public function pre_process(ParsedData $data)
    {
        if ($this->importer->getSetting('send_order_emails') !== true) {
            $this->suppress_order_emails();
        }

        // Split mapped fields into groups. Do not call PostTemplate::pre_process —
        // orders are not standard post fields.
        if (!is_array($data->getData('default'))) {
            $data->replace([], 'default');
        }
        $data = $this->pre_process_groups($data);

        // Copy unique identifier fields into the default group so mapper exists()
        // can resolve them (same pattern as ProductTemplate::_sku).
        $order_key = $data->getValue('order._order_key', 'order');
        if ($order_key !== false && $order_key !== null && $order_key !== '') {
            $data->add(['_order_key' => $order_key]);
        }

        $order_id = $data->getValue('order.ID', 'order');
        if ($order_id !== false && $order_id !== null && $order_id !== '') {
            $data->add(['ID' => $order_id]);
        }

        return $data;
    }

    /**
     * Suppress WooCommerce order emails during import.
     */
    public function suppress_order_emails()
    {
        $email_ids = [
            'new_order',
            'cancelled_order',
            'failed_order',
            'customer_on_hold_order',
            'customer_processing_order',
            'customer_completed_order',
            'customer_refunded_order',
            'customer_invoice',
            'customer_note',
        ];

        foreach ($email_ids as $email_id) {
            add_filter('woocommerce_email_enabled_' . $email_id, '__return_false', 100);
        }
    }

    /**
     * @param int $order_id
     * @param ParsedData $data
     * @return void
     */
    public function post_process($order_id, ParsedData $data)
    {
        if ($this->importer->getSetting('send_order_emails') !== true) {
            $this->suppress_order_emails();
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $this->set_order_data($order, $data);
        $this->set_order_customer($order, $data);
        $this->set_order_address($order, $data, 'billing');
        $this->set_order_address($order, $data, 'shipping');
        $this->set_order_line_items($order, $data);
        $this->set_order_totals($order, $data);

        $order->save();
    }

    /**
     * @param \WC_Order $order
     * @param ParsedData $data
     */
    public function set_order_data(&$order, ParsedData $data)
    {
        $fields = [
            'status' => $data->getValue('order.status', 'order'),
            'currency' => $data->getValue('order.currency', 'order'),
            'date_created' => $data->getValue('order.date_created', 'order'),
            'customer_note' => $data->getValue('order.customer_note', 'order'),
            'payment_method' => $data->getValue('order.payment_method', 'order'),
            'payment_method_title' => $data->getValue('order.payment_method_title', 'order'),
            'transaction_id' => $data->getValue('order.transaction_id', 'order'),
            '_order_key' => $data->getValue('order._order_key', 'order'),
        ];

        $optional = [
            'status' => 'order.status',
            'currency' => 'order.currency',
            'date_created' => 'order.date_created',
            'customer_note' => 'order.customer_note',
            'payment_method' => 'order.payment_method',
            'payment_method_title' => 'order.payment_method_title',
            'transaction_id' => 'order.transaction_id',
        ];

        foreach ($optional as $field_id => $enable_id) {
            if (isset($fields[$field_id]) && method_exists($this->importer, 'isEnabledField') && !$this->importer->isEnabledField($enable_id)) {
                unset($fields[$field_id]);
            }
        }

        if ($data->permission()) {
            $fields = $data->permission()->validate($fields, $data->getMethod(), 'order');
        }

        foreach ($fields as $field => $value) {
            if ($value === false || $value === null || $value === '') {
                continue;
            }

            $value = apply_filters('iwp/template/process_field', $value, $field, $this->importer);
            $value = apply_filters('iwp/woocommerce/order_field', $value, $field);
            $value = apply_filters("iwp/woocommerce/order_field/{$field}", $value);

            switch ($field) {
                case 'status':
                    $order->set_status($value);
                    break;
                case 'currency':
                    $order->set_currency($value);
                    break;
                case 'date_created':
                    $order->set_date_created($value);
                    break;
                case 'customer_note':
                    $order->set_customer_note($value);
                    break;
                case 'payment_method':
                    $order->set_payment_method($value);
                    break;
                case 'payment_method_title':
                    $order->set_payment_method_title($value);
                    break;
                case 'transaction_id':
                    $order->set_transaction_id($value);
                    break;
                case '_order_key':
                    $order->set_order_key($value);
                    break;
            }
        }
    }

    /**
     * @param \WC_Order $order
     * @param ParsedData $data
     */
    public function set_order_customer(&$order, ParsedData $data)
    {
        if (method_exists($this->importer, 'isEnabledField') && !$this->importer->isEnabledField('customer._customer')) {
            return;
        }

        $customer_value = $data->getValue('customer._customer.customer', 'customer');
        $customer_type = $data->getValue('customer._customer._customer_type', 'customer');

        if ($customer_value === false || $customer_value === null || $customer_value === '') {
            return;
        }

        $user_id = 0;
        if ($customer_type === 'id') {
            $user = get_user_by('ID', $customer_value);
            $user_id = $user ? intval($user->ID) : 0;
        } elseif ($customer_type === 'login') {
            $user = get_user_by('login', $customer_value);
            $user_id = $user ? intval($user->ID) : 0;
        } else {
            $user = get_user_by('email', $customer_value);
            $user_id = $user ? intval($user->ID) : 0;
        }

        if ($user_id > 0) {
            $order->set_customer_id($user_id);
        }
    }

    /**
     * @param \WC_Order $order
     * @param ParsedData $data
     * @param string $type billing|shipping
     */
    public function set_order_address(&$order, ParsedData $data, $type)
    {
        $fields = [
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

        if ($type === 'billing') {
            $fields[] = 'email';
        }

        $address = [];
        foreach ($fields as $field) {
            $value = $data->getValue($type . '.' . $field, $type);
            if ($value === false || $value === null) {
                continue;
            }
            $address[$field] = $value;
        }

        if (empty($address)) {
            return;
        }

        if ($data->permission()) {
            $address = $data->permission()->validate($address, $data->getMethod(), $type);
        }

        foreach ($address as $field => $value) {
            $value = apply_filters('iwp/template/process_field', $value, $type . '_' . $field, $this->importer);
            $value = apply_filters('iwp/woocommerce/order_field', $value, $type . '_' . $field);
            $address[$field] = $value;
        }

        if ($type === 'billing') {
            $order->set_address($address, 'billing');
        } else {
            $order->set_address($address, 'shipping');
        }
    }

    /**
     * @param \WC_Order $order
     * @param ParsedData $data
     */
    public function set_order_line_items(&$order, ParsedData $data)
    {
        $group = 'line_items';
        $items = $data->getData($group);
        if (empty($items) || !isset($items[$group . '._index'])) {
            return;
        }

        $index = intval($items[$group . '._index']);
        if ($index < 1) {
            return;
        }

        // Replace existing items on update so re-imports stay consistent.
        foreach ($order->get_items() as $item_id => $item) {
            $order->remove_item($item_id);
        }

        for ($i = 0; $i < $index; $i++) {
            $prefix = $group . '.' . $i . '.';

            // XML/JSON repeater nodes expand into getData('line_items.{i}'),
            // matching taxonomies and other row_base groups.
            $sub_rows = [$items];
            if (isset($items[$prefix . 'row_base']) && !empty($items[$prefix . 'row_base'])) {
                $sub_rows = $data->getData($group . '.' . $i);
                if (empty($sub_rows) || !is_array($sub_rows)) {
                    continue;
                }
            }

            foreach ($sub_rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $product_value = isset($row[$prefix . 'product']) ? $row[$prefix . 'product'] : '';
                $product_type = isset($row[$prefix . '_product_type']) ? $row[$prefix . '_product_type'] : 'sku';
                $quantity = isset($row[$prefix . 'quantity']) ? $row[$prefix . 'quantity'] : 1;
                $total = isset($row[$prefix . 'total']) ? $row[$prefix . 'total'] : '';
                $name = isset($row[$prefix . 'name']) ? $row[$prefix . 'name'] : '';

                if ($product_value === '' || $product_value === null) {
                    continue;
                }

                $product = $this->get_product_from_value($product_value, $product_type);
                if (!$product) {
                    Logger::write(__CLASS__ . '::set_order_line_items -skip missing product type=' . $product_type . ' value=' . $product_value);
                    continue;
                }

                $quantity = max(1, floatval($quantity));
                $args = [];
                if ($total !== '' && $total !== null) {
                    $args['total'] = floatval($total);
                    $args['subtotal'] = floatval($total);
                }
                if ($name !== '' && $name !== null) {
                    $args['name'] = $name;
                }

                $order->add_product($product, $quantity, $args);
            }
        }
    }

    /**
     * @param \WC_Order $order
     * @param ParsedData $data
     */
    public function set_order_totals(&$order, ParsedData $data)
    {
        $shipping_total = $data->getValue('order_totals.shipping_total', 'order_totals');
        $discount_total = $data->getValue('order_totals.discount_total', 'order_totals');
        $cart_tax = $data->getValue('order_totals.cart_tax', 'order_totals');
        $total = $data->getValue('order_totals.total', 'order_totals');

        // Shipping/tax only appear in the WC admin totals UI when matching
        // order items exist — set_*_total alone is not enough.
        if ($shipping_total !== false && $shipping_total !== null && $shipping_total !== '') {
            foreach ($order->get_items('shipping') as $item_id => $item) {
                $order->remove_item($item_id);
            }

            $shipping_amount = floatval($shipping_total);
            $shipping_item = new \WC_Order_Item_Shipping();
            $shipping_item->set_method_title(__('Shipping', 'importwp'));
            $shipping_item->set_method_id('importwp');
            $shipping_item->set_total($shipping_amount);
            $order->add_item($shipping_item);
            $order->set_shipping_total($shipping_amount);
        }

        if ($discount_total !== false && $discount_total !== null && $discount_total !== '') {
            $order->set_discount_total(floatval($discount_total));
        }

        if ($cart_tax !== false && $cart_tax !== null && $cart_tax !== '') {
            foreach ($order->get_items('tax') as $item_id => $item) {
                $order->remove_item($item_id);
            }

            $tax_amount = floatval($cart_tax);
            $tax_item = new \WC_Order_Item_Tax();
            $tax_item->set_rate_code('IMPORTWP-TAX');
            $tax_item->set_label(__('Tax', 'importwp'));
            $tax_item->set_tax_total($tax_amount);
            $tax_item->set_shipping_tax_total(0);
            $order->add_item($tax_item);
            $order->set_cart_tax($tax_amount);
        }

        if ($total !== false && $total !== null && $total !== '') {
            $order->set_total(floatval($total));
        } else {
            $order->calculate_totals(false);
        }
    }

    /**
     * @param string $value
     * @param string $type
     * @return \WC_Product|false
     */
    public function get_product_from_value($value, $type = 'sku')
    {
        if ($type === 'id') {
            $product = wc_get_product(absint($value));
            return $product ? $product : false;
        }

        $product_id = wc_get_product_id_by_sku($value);
        if (!$product_id) {
            return false;
        }

        $product = wc_get_product($product_id);
        return $product ? $product : false;
    }

    public function get_unique_identifier_options($importer_model, $unique_fields = [])
    {
        $mapped_data = $importer_model->getMap();
        $output = [
            // Keys must match unique field ids so RestManager does not add duplicates.
            'ID' => [
                'value' => 'ID',
                'label' => 'Order ID',
                'uid' => true,
                // Non-core field: must be mapped and enabled (map values persist when disabled).
                'active' => isset($mapped_data['order.ID'])
                    && !empty(trim((string) $mapped_data['order.ID']))
                    && true === $importer_model->isEnabledField('order.ID'),
            ],
            '_order_key' => [
                'value' => '_order_key',
                'label' => 'Order Key',
                'uid' => true,
                // Core field: always available when mapped.
                'active' => isset($mapped_data['order._order_key']) && !empty(trim((string) $mapped_data['order._order_key'])),
            ],
        ];

        // Preserve Pro custom field unique identifiers when available.
        if (property_exists($this, 'custom_fields') && $this->custom_fields) {
            $output = array_merge(
                $output,
                $this->custom_fields->get_unique_identifier_options_from_map($importer_model, $unique_fields)
            );
        }

        return $output;
    }
}
