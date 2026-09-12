<?php

namespace ImportWPAddon\WooCommerce\Importer\Template;

use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\EventHandler;

if (!class_exists(__NAMESPACE__ . '\\IWP_Base_UserTemplate', false)) {
    if (class_exists('ImportWP\\Pro\\Importer\\Template\\UserTemplate')) {
        class IWP_Base_UserTemplate extends \ImportWP\Pro\Importer\Template\UserTemplate
        {
        }
    } else {
        class IWP_Base_UserTemplate extends \ImportWP\Common\Importer\Template\UserTemplate
        {
        }
    }
}

class CustomerTemplate extends IWP_Base_UserTemplate implements TemplateInterface
{
    protected $name = 'WooCommerce Customers';
    protected $mapper = 'woocommerce-customer';

    public function __construct(EventHandler $event_handler)
    {
        parent::__construct($event_handler);

        $this->default_template_options['unique_field'] = ['ID', 'user_email', 'user_login'];
        $this->groups = array_merge($this->groups, [
            'billing',
            'shipping',
        ]);
    }

    public function register()
    {
        $groups = parent::register();

        // Relabel core user group for WooCommerce context.
        if (!empty($groups[0]['heading'])) {
            $groups[0]['heading'] = 'Customer Fields';
        }

        $groups[] = $this->register_group('Billing Address', 'billing', [
            $this->register_field('First Name', 'first_name'),
            $this->register_field('Last Name', 'last_name'),
            $this->register_field('Company', 'company'),
            $this->register_field('Address 1', 'address_1'),
            $this->register_field('Address 2', 'address_2'),
            $this->register_field('City', 'city'),
            $this->register_field('Postcode', 'postcode'),
            $this->register_field('Country', 'country', [
                'tooltip' => __('Two-letter country code, e.g. GB or US.', 'importwp'),
            ]),
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
            $this->register_field('Country', 'country', [
                'tooltip' => __('Two-letter country code, e.g. GB or US.', 'importwp'),
            ]),
            $this->register_field('State', 'state'),
            $this->register_field('Phone', 'phone'),
        ]);

        return $groups;
    }

    public function register_settings()
    {
        $settings = parent::register_settings();

        // Clarify that the inherited notify_users setting also covers WooCommerce emails.
        foreach ($settings as &$setting) {
            if (isset($setting['id']) && $setting['id'] === 'notify_users') {
                $setting['label'] = __('Enable customer notification emails.', 'importwp');
                $setting['tooltip'] = __('Send WordPress and WooCommerce customer emails (new account, password/email changes). Disabled by default.', 'importwp');
            }
        }
        unset($setting);

        return $settings;
    }

    /**
     * @param ParsedData $data
     * @return ParsedData
     */
    public function pre_process(ParsedData $data)
    {
        $data = parent::pre_process($data);

        // Default new customers to the WooCommerce customer role when role is not mapped.
        $role = $data->getValue('role');
        if (empty($role) && !$this->importer->isEnabledField('user.role')) {
            $data->replace(array_merge($data->getData('default'), ['role' => 'customer']), 'default');
        }

        if ($this->importer->getSetting('notify_users') !== true) {
            $this->suppress_customer_emails();
        }

        return $data;
    }

    /**
     * Suppress WooCommerce customer emails when notifications are disabled.
     *
     * WordPress user emails are already handled by UserTemplate when
     * notify_users is not enabled.
     */
    public function suppress_customer_emails()
    {
        add_filter('woocommerce_email_enabled_customer_new_account', '__return_false', 100);
        add_filter('woocommerce_email_enabled_customer_reset_password', '__return_false', 100);
    }

    /**
     * Apply billing/shipping addresses after the user record is imported.
     *
     * UserTemplate::post_process is final, so this is invoked via the
     * template.post_process event from setup.php.
     *
     * @param int $user_id
     * @param ParsedData $data
     * @return void
     */
    public function apply_customer_addresses($user_id, ParsedData $data)
    {
        $customer = new \WC_Customer($user_id);
        if (!$customer->get_id()) {
            return;
        }

        $this->set_customer_address($customer, $data, 'billing');
        $this->set_customer_address($customer, $data, 'shipping');

        $customer->save();
    }

    /**
     * @param \WC_Customer $customer
     * @param ParsedData $data
     * @param string $type billing|shipping
     */
    public function set_customer_address(&$customer, ParsedData $data, $type)
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

        if ($data->permission()) {
            $address = $data->permission()->validate($address, $data->getMethod(), $type);
        }

        foreach ($address as $field => $value) {
            $value = apply_filters('iwp/template/process_field', $value, $type . '_' . $field, $this->importer);
            $value = apply_filters('iwp/woocommerce/customer_field', $value, $field, $type);
            $value = apply_filters("iwp/woocommerce/customer_field/{$type}_{$field}", $value);

            $setter = 'set_' . $type . '_' . $field;
            if (is_callable([$customer, $setter])) {
                $customer->{$setter}($value);
            }
        }
    }

    public function get_unique_identifier_options($importer_model, $unique_fields = [])
    {
        $output = parent::get_unique_identifier_options($importer_model, $unique_fields);

        // Relabel only — parent already sets active from the map. Do not add
        // duplicate always-active entries (same bug as Order unique identifiers).
        $labels = [
            'ID'         => 'Customer ID',
            'user_email' => 'Email',
            'user_login' => 'Username',
        ];
        foreach ($labels as $field_id => $label) {
            if (isset($output[$field_id])) {
                $output[$field_id]['label'] = $label;
            }
        }

        return $output;
    }
}
