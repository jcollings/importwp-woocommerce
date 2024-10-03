<?php

namespace ImportWPAddon\WooCommerce\Importer\Template;

use ImportWP\Common\Importer\Exception\MapperException;
use ImportWP\Common\Importer\ParsedData;
use ImportWP\Common\Importer\TemplateInterface;
use ImportWP\Container;
use ImportWP\EventHandler;

if (class_exists('ImportWP\Pro\Importer\Template\PostTemplate')) {
    class IWP_Base_PostTemplate extends \ImportWP\Pro\Importer\Template\PostTemplate {}
} else {
    class IWP_Base_PostTemplate extends \ImportWP\Common\Importer\Template\PostTemplate {}
}

class ProductTemplate extends IWP_Base_PostTemplate implements TemplateInterface
{
    protected $name = 'WooCommerce Products';
    protected $mapper = 'woocommerce-product';

    /**
     * List of field names that have been modified
     *
     * @var array
     */
    private $_fields = [];

    public function __construct(EventHandler $event_handler)
    {
        parent::__construct($event_handler);
        $this->default_template_options['post_type'] = ['product', 'product_variation'];
        $this->default_template_options['unique_field'] = ['ID', '_sku', 'post_name'];
        $this->groups = array_merge($this->groups, [
            'price',
            'inventory',
            'advanced',
            'shipping',
            'linked-products',
            'attributes',
            'product_gallery',
            'downloads'
        ]);

        $this->field_options = array_merge($this->field_options, [
            'advanced._parent.parent' => [$this, 'get_post_parent_options'],
        ]);

        add_filter('iwp/importer/generate_field_map/custom_fields', [$this, 'generate_field_map_remove_wc_custom_fields']);
    }

    public function register()
    {
        $groups = [];

        $product_types = wc_get_product_types();
        $product_types['variation'] = 'Product Variation';
        $product_types_options = [];
        foreach ($product_types as $k => $v) {
            $product_types_options[] = [
                'value' => $k,
                'label' => $v
            ];
        }

        $tax_classes = wc_get_product_tax_class_options();
        $tax_classes_options = [];
        foreach ($tax_classes as $key => $value) {
            $tax_classes_options[] = [
                'value' => $key,
                'label' => $value
            ];
        }

        $yes_no = [
            ['value' => 'yes', 'label' => 'Yes'],
            ['value' => 'no', 'label' => 'No'],
        ];

        $groups[] = $this->register_group('Product Fields', 'post', [
            $this->register_core_field('Product Name', 'post_title'),
            $this->register_core_field('Description', 'post_content'),
            $this->register_core_field('Short Description', 'post_excerpt'),
            $this->register_field('Slug', 'post_name', [
                'tooltip' => __('The slug is the user friendly and URL valid name of the post.', 'importwp')
            ]),
            $this->register_field('Product Type', 'product_type', [
                'options' => $product_types_options
            ]),
            $this->register_field('Virtual', '_virtual', [
                'options' => $yes_no,
                'default' => 'no'
            ]),
            $this->register_field('Downloadable', '_downloadable', [
                'options' => $yes_no,
                'default' => 'no'
            ]),
            $this->register_field('Catalog visibility', '_visibility', [
                'options' => [
                    ['value' => 'visible', 'label' => 'Shop and search results'],
                    ['value' => 'catalog', 'label' => 'Shop only'],
                    ['value' => 'search', 'label' => 'Search results only'],
                    ['value' => 'hidden', 'label' => 'Hidden']
                ],
                'default' => 'visible'
            ]),
            $this->register_group('External Product URL', 'external', [
                $this->register_field('Product URL', '_product_url'),
                $this->register_field('Product URL Button Text', '_button_text'),
            ]),
            $this->register_field('Status', 'post_status', [
                'default' => 'publish',
                'options'         => [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'publish', 'label' => 'Published'],
                    ['value' => 'pending', 'label' => 'Pending'],
                    ['value' => 'future', 'label' => 'Future'],
                    ['value' => 'private', 'label' => 'Private'],
                    ['value' => 'trash', 'label' => 'Trash']
                ],
                'tooltip' => __('Whether the post can accept comments. Accepts open or closed', 'importwp')
            ]),
            $this->register_group('Tax', 'tax', [
                $this->register_field('Tax Status', 'tax_status', [
                    'options' => [
                        ['value' => 'taxable', 'label' => __('Taxable', 'woocommerce')],
                        ['value' => 'shipping', 'label' => __('Shipping only', 'woocommerce')],
                        ['value' => 'none', 'label' => _x('None', 'Tax status', 'woocommerce')],
                    ],
                    'default' => 'taxable'
                ]),
                $this->register_field('Tax Class', 'tax_class', [
                    'options' => $tax_classes_options,
                    'default' => ''
                ]),
            ]),
            $this->register_group('Author Settings', '_author', [
                $this->register_field('Author', 'post_author', [
                    'tooltip' => __('The user of who added this post', 'importwp')
                ]),
                $this->register_field('Author Field Type', '_author_type', [
                    'default' => 'id',
                    'options' => [
                        ['value' => 'id', 'label' => 'ID'],
                        ['value' => 'login', 'label' => 'Login'],
                        ['value' => 'email', 'label' => 'Email'],
                    ],
                    'tooltip' => __('Select how the author field should be handled', 'importwp')
                ])
            ]),
            $this->register_field('Product ID', 'ID', [
                'tooltip' => __('Product ID field is only used as a reference and can not be inserted or updated.', 'importwp'),
            ]),
        ]);

        $groups[] = $this->register_group('Pricing', 'price', [
            $this->register_core_field('Regular Price', '_regular_price'),
            $this->register_group('Sale Price', 'sale', [
                $this->register_core_field('Sale Price', '_sale_price'),
                $this->register_core_field('Sale Price From', '_sale_price_dates_from'),
                $this->register_core_field('Sale Price To', '_sale_price_dates_to'),
            ]),
        ]);

        $groups[] = $this->register_group('Inventory', 'inventory', [
            $this->register_core_field('Product SKU', '_sku', [
                'tooltip' => 'SKU refers to a Stock-keeping unit, a unique identifier for each distinct product and service that can be purchased.'
            ]),
            $this->register_group('Stock', 'stock', [
                $this->register_field('Manage Stock?', '_manage_stock', [
                    'options' => $yes_no,
                    'default' => 'no',
                    'tooltip' => 'Enable stock management at product level',
                ]),
                $this->register_field('Stock status', '_stock_status', [
                    'options' => [
                        ['value' => 'instock', 'label' => 'In stock'],
                        ['value' => 'outofstock', 'label' => 'Out of stock'],
                        ['value' => 'onbackorder', 'label' => 'On backorder']
                    ],
                    'default' => 'instock',
                    'tooltip' => 'Controls whether or not the product is listed as "in stock" or "out of stock" on the frontend.',
                ]),
                $this->register_field('Stock quantity', '_stock', [
                    'tooltip' => 'Stock quantity. If this is a variable product this value will be used to control stock for all variations, unless you define stock at variation level.',
                ]),
                $this->register_field('Allow back-orders?', '_backorders', [
                    'options' => array(
                        ['value' => 'yes', 'label' => 'Allow'],
                        ['value' => 'no', 'label' => 'Do not allow'],
                        ['value' => 'notify', 'label' => 'Allow, but notify customer']
                    ),
                    'default' => 'no',
                    'tooltip' => 'If managing stock, this controls whether or not back-orders are allowed. if enabled, stock quantity can go below 0.',
                ]),
                $this->register_field('Low stock threshold', '_low_stock_amount', array(
                    'tooltip' => 'When product stock reaches this amount you will be notified by email'
                )),
                $this->register_field('Sold individually', '_sold_individually', array(
                    'options' => $yes_no,
                    'default' => 'no',
                    'tooltip' => 'Enable this to only allow one of this item to be bought in a single order',
                )),
            ])
        ]);

        $attributes = apply_filters('iwp/woocommerce/xml_child_variation_attributes', []);
        if (!empty($attributes)) {

            $attribute_fields = [];
            foreach ($attributes as $attribute) {
                $attribute_fields[] = $this->register_field('Attribute ' . $attribute, 'attribute_' . $attribute);
            }

            $groups[] = $this->register_group('Variations', 'variations', array_merge([

                $this->register_field('Stock', 'stock'),
                $this->register_field('Price', 'price'),
                $this->register_field('Sku', 'sku'),

            ], $attribute_fields), ['type' => 'repeatable', 'row_base' => true]);
        }

        $groups[] = $this->register_group('Shipping', 'shipping', [
            $this->register_group('Product Dimensions', 'dimensions', [
                $this->register_field('Weight', '_weight'),
                $this->register_field('Length', '_length'),
                $this->register_field('Width', '_width'),
                $this->register_field('Height', '_height'),
            ]),
        ]);

        $linked_products_field_type = $this->register_field('Type', '_field_type', [
            'options' => [
                ['value' => 'ID', 'label' => 'Product ID'],
                ['value' => '_sku', 'label' => 'Product SKU'],
                ['value' => 'name', 'label' => 'Product Name'],
                ['value' => 'slug', 'label' => 'Product Slug'],
            ],
            'default' => 'ID'
        ]);

        $groups[] = $this->register_group('Linked Products', 'linked-products', [
            $this->register_group('Cross-Sells', 'crosssell', [
                $this->register_field('Cross-Sells Products', 'products'),
                $linked_products_field_type
            ]),
            $this->register_group('Up-Sells', 'upsell', [
                $this->register_field('Up-Sells Products', 'products'),
                $linked_products_field_type
            ]),
            $this->register_group('Grouped products', 'grouped', [
                $this->register_field('Grouped Products', 'products'),
                $linked_products_field_type
            ]),
        ]);
        $groups[] = $this->register_group('Product Attributes', 'attributes', [
            $this->register_field('Name', 'name'),
            $this->register_field('Terms', 'terms'),
            $this->register_field('Is Global?', 'global', [
                'options' => $yes_no,
                'default' => 'no'
            ]),
            $this->register_field('Is Visible?', 'visible', [
                'options' => $yes_no,
                'default' => 'no'
            ]),
            $this->register_field('Append terms?', 'append', [
                'options' => $yes_no,
                'default' => 'no',
                'tooltip' => __('Clear existing product attribute terms, or append new attribute terms.', 'importwp')
            ]),
            $this->register_field('Used for variations?', 'variation', [
                'options' => $yes_no,
                'default' => ''
            ]),
        ], ['type' => 'repeatable', 'settings' => [
            $this->register_field('Keep existing Product Attributes.', 'append_attributes', [
                'type' => 'toggle',
                'default' => 'no',
                'tooltip' => __('If enabled attributes will be appended onto existing attribute list.')
            ])
        ]]);
        $groups[] = $this->register_group('Advanced', 'advanced', [
            $this->register_group('Parent Settings', '_parent', [
                $this->register_field('Parent', 'parent', [
                    'default' => '',
                    'options' => 'callback',
                    'tooltip' => __('Set this for the post it belongs to', 'importwp')
                ]),
                $this->register_field('Parent Field Type', '_parent_type', [
                    'default' => 'id',
                    'options' => [
                        ['value' => 'id', 'label' => 'ID'],
                        ['value' => 'slug', 'label' => 'Slug'],
                        ['value' => 'name', 'label' => 'Name'],
                        ['value' => 'sku', 'label' => 'Sku'],
                        ['value' => 'column', 'label' => 'Reference Column'],
                    ],
                    'type' => 'select',
                    'tooltip' => __('Select how the parent field should be handled', 'importwp')
                ]),
                $this->register_field('Parent Reference Column', '_parent_ref', [
                    'condition' => ['_parent_type', '==', 'column'],
                    'tooltip' => __('Select the column/node that the parent field is referencing', 'importwp')
                ])
            ]),
            $this->register_field('Purchase note', '_purchase_note'),
            $this->register_field('Order', 'menu_order', [
                'tooltip' => __('The order the post should be displayed in', 'importwp')
            ]),
            $this->register_field('Enable Reviews', 'comment_status', [
                'options' => $yes_no,
                'default' => 'no'
            ]),
            $this->register_field('Download Limit', '_download_limit'),
            $this->register_field('Download Expiry', '_download_expiry'),
            $this->register_field('Date', 'post_date', [
                'tooltip' => __('The date of the post , enter in the format "YYYY-MM-DD HH:ii:ss"', 'importwp')
            ]),
        ]);

        $groups[] = $this->register_attachment_fields('Product Gallery', 'product_gallery');

        $groups[] = $this->register_group('Product downloads', 'downloads', [
            $this->register_field('File ID', 'download_id'),
            $this->register_field('File Name', 'name'),
            $this->register_field('File URL', 'file'),
        ], ['type' => 'repeatable']);

        // remove core parent group
        $parent_groups = parent::register();
        array_shift($parent_groups);
        $groups = array_merge($groups, $parent_groups);

        return $groups;
    }

    /**
     * Process data before record is importer.
     * 
     * Alter data that is passed to the mapper.
     *
     * @param ParsedData $data
     * @return ParsedData
     */
    public function pre_process(ParsedData $data)
    {
        $data = parent::pre_process($data);

        $sku = $data->getValue('inventory._sku', 'inventory');
        $data->add(['_sku' => $sku]);

        $post_parent_value = $data->getValue('advanced._parent.parent', 'advanced');

        if ($this->importer->isEnabledField('post._author')) {
            $post_author = $data->getValue('post._author.post_author', 'post');
            $post_author_type = $data->getValue('post._author._author_type', 'post');

            $user_id = 0;

            if ($post_author_type === 'id') {

                $user = get_user_by('ID', $post_author);
                if ($user) {
                    $user_id = intval($user->ID);
                }
            } elseif ($post_author_type === 'login') {

                $user = get_user_by('login', $post_author);
                if ($user) {
                    $user_id = intval($user->ID);
                }
            } elseif ($post_author_type === 'email') {

                $user = get_user_by('email', $post_author);
                if ($user) {
                    $user_id = intval($user->ID);
                }
            }

            $data->add(['post_author' => $user_id > 0 ? $user_id : ''], 'post');
        }

        if ($this->importer->isEnabledField('advanced._parent') && isset($post_parent_value)) {

            $parent_id = 0;
            $parent_field_type = $data->getValue('advanced._parent._parent_type', 'advanced');

            if ($parent_field_type === 'name') {

                $temp_id = $this->get_product_id_by_name($post_parent_value);
                if (intval($temp_id > 0)) {
                    $parent_id = intval($temp_id);
                }
            } elseif ($parent_field_type === 'slug') {

                $temp_id = $this->get_product_id_by_slug($post_parent_value);
                if (intval($temp_id > 0)) {
                    $parent_id = intval($temp_id);
                }
            } elseif ($parent_field_type === 'id') {

                $parent_id = intval($post_parent_value);
            } elseif ($parent_field_type === 'sku') {
                $temp_id = $this->get_product_id_by_sku($post_parent_value);
                if (intval($temp_id > 0)) {
                    $parent_id = intval($temp_id);
                }
            } elseif ($parent_field_type === 'column') {

                // reference column
                $temp_id = $this->get_post_by_cf('post_parent', $post_parent_value);
                if (intval($temp_id > 0)) {
                    $parent_id = intval($temp_id);
                }
            }

            if ($parent_id > 0) {
                $data->add(['post_parent' => $parent_id], 'advanced');
            }
        }

        return $data;
    }

    /**
     * Process data after record is importer.
     * 
     * Use data that is returned from the mapper.
     *
     * @param int $post_id
     * @param ParsedData $data
     * @return void
     */
    public function post_process($post_id, ParsedData $data)
    {
        $this->clear_field_log();

        parent::post_process($post_id, $data);

        $wc_data = [
            // post
            'post_title' => $data->getValue('post.post_title', 'post'),
            'post_name' => $data->getValue('post.post_name', 'post'),
            'post_content' => $data->getValue('post.post_content', 'post'),
            'post_excerpt' => $data->getValue('post.post_excerpt', 'post'),
            'post_status' => $data->getValue('post.post_status', 'post'),
            'product_type' => $data->getValue('post.product_type', 'post'),
            '_virtual' => $data->getValue('post._virtual', 'post'),
            '_downloadable' => $data->getValue('post._downloadable', 'post'),
            '_visibility' => $data->getValue('post._visibility', 'post'),
            '_product_url' => $data->getValue('post.external._product_url', 'post'),
            '_button_text' => $data->getValue('post.external._button_text', 'post'),
            'tax_status' => $data->getValue('post.tax.tax_status', 'post'),
            'tax_class' => $data->getValue('post.tax.tax_class', 'post'),
            'post_author' => $data->getValue('post_author', 'post'),

            // Price
            '_regular_price' => $data->getValue('price._regular_price', 'price'),
            '_sale_price' => $data->getValue('price.sale._sale_price', 'price'),
            '_sale_price_dates_to' => $data->getValue('price.sale._sale_price_dates_to', 'price'),
            '_sale_price_dates_from' => $data->getValue('price.sale._sale_price_dates_from', 'price'),
            // inventory
            '_sku' => $data->getValue('inventory._sku', 'inventory'),
            '_stock_status' => $data->getValue('inventory.stock._stock_status', 'inventory'),
            '_manage_stock' => $data->getValue('inventory.stock._manage_stock', 'inventory'),
            '_stock' => $data->getValue('inventory.stock._stock', 'inventory'),
            '_backorders' => $data->getValue('inventory.stock._backorders', 'inventory'),
            '_low_stock_amount' => $data->getValue('inventory.stock._low_stock_amount', 'inventory'),
            '_sold_individually' => $data->getValue('inventory.stock._sold_individually', 'inventory'),
            // shipping
            '_weight' => $data->getValue('shipping.dimensions._weight', 'shipping'),
            '_length' => $data->getValue('shipping.dimensions._length', 'shipping'),
            '_width' => $data->getValue('shipping.dimensions._width', 'shipping'),
            '_height' => $data->getValue('shipping.dimensions._height', 'shipping'),

            // advanced
            '_purchase_note' => $data->getValue('advanced._purchase_note', 'advanced'),
            '_download_limit' => $data->getValue('advanced._download_limit', 'advanced'),
            '_download_expiry' => $data->getValue('advanced._download_expiry', 'advanced'),
            'post_date' => $data->getValue('advanced.post_date', 'advanced'),

        ];

        // remove disabled fields
        $optional_fields = [
            // post
            'post_status' => 'post.post_status',
            'post_name' => 'post.post_name',
            'product_type' => 'post.product_type',
            '_virtual' => 'post._virtual',
            '_downloadable' => 'post._downloadable',
            '_visibility' => 'post._visibility',
            '_product_url' => 'post.external',
            '_button_text' => 'post.external',
            'tax_status' => 'post.tax_status',
            'tax_class' => 'post.tax_class',

            // price
            '_sale_price' => 'price.sale',
            '_sale_price_dates_to' => 'price.sale',
            '_sale_price_dates_from' => 'price.sale',

            // stock
            '_stock_status' => 'inventory.stock',
            '_manage_stock' => 'inventory.stock',
            '_stock' => 'inventory.stock',
            '_backorders' => 'inventory.stock',
            '_low_stock_amount' => 'inventory.stock',
            '_sold_individually' => 'inventory.stock',

            // shipping shipping.dimensions
            '_weight' => 'shipping.dimensions',
            '_length' => 'shipping.dimensions',
            '_width' => 'shipping.dimensions',
            '_height' => 'shipping.dimensions',

            // advanced
            // TODO: '' => 'advanced._parent',
            'menu_order' => 'advanced.menu_order',
            'comment_status' => 'advanced.comment_status',
            '_purchase_note' => 'advanced._purchase_note',
            '_download_limit' => 'advanced._download_limit',
            '_download_expiry' => 'advanced._download_expiry',
            'post_date' => 'advanced.post_date',
        ];

        foreach ($optional_fields as $field_id => $enable_id) {
            if (isset($wc_data[$field_id]) && !$this->importer->isEnabledField($enable_id)) {
                unset($wc_data[$field_id]);
            }
        }

        // check permissions for product data
        $wc_data = $data->permission()->validate($wc_data, $data->getMethod(), 'product');

        foreach ($wc_data as $field => $value) {
            $raw_value = $this->format_field($field, $value);
            $value = apply_filters('iwp/template/process_field', $value, $field, $this->importer);
            $value = apply_filters("iwp/woocommerce/product_field", $value, $raw_value, $field);
            $value = apply_filters("iwp/woocommerce/product_field/{$field}", $value, $raw_value);
            $wc_data[$field] = $value;

            $this->log_field($field);
        }

        $product = $this->get_product_object($post_id, $wc_data);

        try {

            /**
             * @var \WC_Product $product
             */
            if (isset($wc_data['_sku'])) {
                $product->set_sku($wc_data['_sku']);
            }

            // product types
            if (isset($wc_data['product_type'])) {
                $downloadable_set = false;
                $virtual_set = false;
                $product_types = explode(',', $wc_data['product_type']);
                if (count($product_types) > 0) {
                    $product_types = array_filter(array_map('trim', $product_types));
                    if (in_array('downloadable', $product_types, true)) {
                        $product->set_downloadable('yes');
                        $downloadable_set = true;
                    }
                    if (in_array('virtual', $product_types, true)) {
                        $product->set_virtual('yes');
                        $virtual_set = true;
                    }
                }
            }

            // set only if they have not been previously set by product type column
            if (isset($wc_data['_downloadable']) && false === $downloadable_set) {
                $product->set_downloadable($wc_data['_downloadable']);
            }
            if (isset($wc_data['_virtual']) && false === $virtual_set) {
                $product->set_virtual($wc_data['_virtual']);
            }

            // name
            if (isset($wc_data['post_title'])) {
                $product->set_name($wc_data['post_title']);
            }

            if (isset($wc_data['post_name'])) {
                $product->set_slug($wc_data['post_name']);
            }

            if (isset($wc_data['post_excerpt'])) {
                $product->set_short_description($wc_data['post_excerpt']);
            }

            if (isset($wc_data['post_content'])) {
                $product->set_description($wc_data['post_content']);
            }

            if (isset($wc_data['post_status'])) {
                $product->set_status($wc_data['post_status']);
            }

            if (isset($wc_data['menu_order'])) {
                $product->set_menu_order($wc_data['menu_order']);
            }

            if (isset($wc_data['comment_status'])) {
                $product->set_reviews_allowed($wc_data['comment_status']);
            }

            if (isset($wc_data['post_date'])) {
                $product->set_date_created($wc_data['post_date']);
            }

            // set product values via WC_Product Methods
            if (isset($wc_data['_regular_price'])) {
                $product->set_regular_price($wc_data['_regular_price']);
            }
            if (isset($wc_data['_sale_price'])) {
                $product->set_sale_price($wc_data['_sale_price']);
            }
            if (isset($wc_data['_sale_price_dates_from'])) {
                $product->set_date_on_sale_from($wc_data['_sale_price_dates_from']);
            }
            if (isset($wc_data['_sale_price_dates_to'])) {
                $product->set_date_on_sale_to($wc_data['_sale_price_dates_to']);
            }
            if (isset($wc_data['_visibility'])) {
                $product->set_catalog_visibility($wc_data['_visibility']);
            }
            if (isset($wc_data['_purchase_note'])) {
                $product->set_purchase_note($wc_data['_purchase_note']);
            }
            if (isset($wc_data['tax_class'])) {
                $product->set_tax_class($wc_data['tax_class']);
            }
            if (isset($wc_data['tax_status'])) {
                $product->set_tax_status($wc_data['tax_status']);
            }

            // Dimensions
            if (isset($wc_data['_weight'])) {
                $product->set_weight($wc_data['_weight']);
            }
            if (isset($wc_data['_length'])) {
                $product->set_length($wc_data['_length']);
            }
            if (isset($wc_data['_width'])) {
                $product->set_width($wc_data['_width']);
            }
            if (isset($wc_data['_height'])) {
                $product->set_height($wc_data['_height']);
            }

            if ($product->is_type('external')) {

                /**
                 * @var WC_Product_External $product
                 */
                if (isset($wc_data['_button_text'])) {
                    $product->set_button_text($wc_data['_button_text']);
                }
                if (isset($wc_data['_product_url'])) {
                    $product->set_product_url($wc_data['_product_url']);
                }
            } else {

                // Stock
                if (isset($wc_data['_manage_stock'])) {
                    $product->set_manage_stock($wc_data['_manage_stock']);
                }
                if (isset($wc_data['_stock_status'])) {
                    $product->set_stock_status($wc_data['_stock_status']);
                }
                if (isset($wc_data['_stock'])) {
                    $product->set_stock_quantity($wc_data['_stock']);
                }
                if (isset($wc_data['_backorders'])) {
                    $product->set_backorders($wc_data['_backorders']);
                }
                if (isset($wc_data['_low_stock_amount'])) {
                    $product->set_low_stock_amount($wc_data['_low_stock_amount']);
                }
                if (isset($wc_data['_sold_individually'])) {
                    $product->set_sold_individually($wc_data['_sold_individually']);
                }
            }

            if (isset($wc_data['post_author'])) {
                $product->set_props(['post_author' => $wc_data['post_author']]);
            }

            // // downloadable
            $this->set_downloads($product, $data, $wc_data);

            // TODO: set variations
            if ($product->is_type('variable')) {
                $attributes = apply_filters('iwp/woocommerce/xml_child_variation_attributes', []);
                if (!empty($attributes)) {

                    /**
                     * @var \WC_Product_Variable $product
                     */
                    $available_variations = $product->get_available_variations();

                    /**
                     * @var \ImportWP\Common\Importer\ParsedData $data
                     */
                    $variations = $data->getData('variations.0');
                    foreach ($variations as $variation_data) {

                        $sku = $variation_data['variations.0.sku'];
                        $stock = $variation_data['variations.0.stock'];
                        $price = $variation_data['variations.0.price'];

                        $formatted_attrs = [];
                        foreach ($attributes as $attr) {

                            // if starts with pa_ then we get the id
                            $name = $variation_data['variations.0.attribute_' . $attr];
                            if (strpos($attr, 'pa_') === 0) {
                                $name = get_term_by('name', $name, $attr)->slug;
                            }
                            $formatted_attrs[$attr] = $name;
                        }

                        $variation_id = false;
                        foreach ($available_variations as $existing_variation) {
                            if (
                                $existing_variation['sku'] == $sku
                                || !array_diff_assoc($formatted_attrs, $existing_variation['attributes'])
                            ) {
                                $variation_id = $existing_variation['variation_id'];
                                break;
                            }
                        }

                        if (!empty($variation_id)) {
                            $variation = wc_get_product_object('variation', $variation_id);
                        } else {
                            $variation = new \WC_Product_Variation();
                            $variation->set_attributes($formatted_attrs);
                        }

                        $variation->set_parent_id($product->get_id());
                        $variation->set_stock_quantity($stock);
                        $variation->set_regular_price($price);
                        $variation->save();
                    }
                }
            }

            if ('variation' === $product->get_type()) {
                $this->set_variation_data($product, $data);
            } else {
                $this->set_product_data($product, $data);
            }

            // Product gallery
            $this->process_product_gallery($product, $data);

            $product->save();
        } catch (\Exception $e) {
            throw new MapperException($e->getMessage());
        }
    }

    function get_product_object($post_id, $wc_data)
    {
        $found_type = false;

        if (isset($wc_data['product_type'])) {

            $allowed_types   = array_keys(wc_get_product_types());
            $allowed_types[] = 'variation';
            $product_types = explode(',', $wc_data['product_type']);

            foreach ($allowed_types as $allowed_type) {
                if (in_array($allowed_type, $product_types)) {
                    $found_type = $allowed_type;
                }
            }
        }

        if ($found_type) {

            $classname = \WC_Product_Factory::get_classname_from_product_type($found_type);
            if (!class_exists($classname)) {
                $classname = 'WC_Product_Simple';
            }

            try {
                $product = new $classname($post_id);
            } catch (\Exception $e) {
                throw new MapperException($e->getMessage());
            }
        } else {
            $product = wc_get_product($post_id);
        }
        if (!$product) {
            throw new MapperException("Error loading product");
        }

        return $product;
    }

    /**
     * @param \WC_Product $product
     * @param ParsedData $data
     * @return void
     */
    public function process_product_gallery(&$product, $data)
    {

        /**
         * @var Filesystem $filesystem
         */
        $filesystem = Container::getInstance()->get('filesystem');

        /**
         * @var Ftp $ftp
         */
        $ftp = Container::getInstance()->get('ftp');

        /**
         * @var Attachment $attachment
         */
        $attachment = Container::getInstance()->get('attachment');

        $image_ids = $this->process_attachments($product->get_id(), $data, $filesystem, $ftp, $attachment, 'product_gallery');
        if ($image_ids !== false) {
            $product->set_gallery_image_ids($image_ids);
        }
    }

    private function format_field($field, $value)
    {

        switch ($field) {
            case '_regular_price':
            case '_sale_price':
            case 'price':
                $value = wc_format_decimal($value);
                break;
            case '_weight':
            case '_length':
            case '_width':
            case '_height':
                if ('' !== $value) {
                    $value = floatval($value);
                }
                break;
        }

        return $value;
    }

    /**
     * @param \WC_Product $product
     * @param ParsedData $data
     * @param array $data
     */
    private function set_downloads(&$product, $data, $wc_data)
    {

        if (isset($wc_data['_download_limit'])) {
            $product->set_download_limit($wc_data['_download_limit']);
        }
        if (isset($wc_data['_download_expiry'])) {
            $product->set_download_expiry($wc_data['_download_expiry']);
        }

        $group = 'downloads';
        $raw_downloads = $data->getData($group);
        $record_count = isset($raw_downloads[$group . '._index']) ? intval($raw_downloads[$group . '._index']) : 0;
        $skipped = 0;

        $downloads = [];
        for ($i = 0; $i < $record_count; $i++) {

            $permission_key = 'product_downloads.' . $i; //downloads.0

            if ($data->permission()) {
                $allowed = $data->permission()->validate([$permission_key => ''], $data->getMethod(), $group);
                $is_allowed = isset($allowed[$permission_key]) ? true : false;

                if (!$is_allowed) {
                    $skipped++;
                    continue;
                }
            }

            $prefix = $group . '.' . $i . '.';
            $row = [
                'name' => $raw_downloads[$prefix . 'name'],
                'file' => $raw_downloads[$prefix . 'file']
            ];

            $id = $raw_downloads[$prefix . 'download_id'];
            if (!empty($id)) {
                $row['download_id'] = $id;
            }

            // skip empty rows
            if (empty($row['file'])) {
                continue;
            }

            $downloads[] = $row;
        }

        if ($record_count > $skipped) {
            $product->set_downloads($downloads);
        }
    }

    /**
     * @param \WC_Product_Variation $variation 
     * @param ParsedData $data 
     * @return void 
     * @throws Exception 
     */
    public function set_variation_data(&$variation, ParsedData $data)
    {

        $parent = false;

        $post_parent = $data->getValue('post_parent', 'advanced');

        // Check if parent exist.
        if (isset($post_parent)) {
            $parent = wc_get_product($post_parent);

            if ($parent) {
                $variation->set_parent_id($parent->get_id());
            }
        }

        // Stop if parent does not exists.
        if ($data->isInsert()) {
            if (!$parent) {
                throw new \Exception(__('Variation cannot be imported: Missing parent ID or parent does not exist yet.', 'woocommerce'));
            }

            // Stop if parent is a product variation.
            if ($parent->is_type('variation')) {
                throw new \Exception(__('Variation cannot be imported: Parent product cannot be a product variation', 'woocommerce'));
            }
        }

        $raw_attributes = $data->getData('attributes');
        $record_count = intval($raw_attributes['attributes._index']);

        $attributes = array();
        $skipped = 0;
        $update_parent = false;

        if ($record_count > 0) {

            $parent_attributes = [];

            if (!$parent) {
                $parent = wc_get_product($variation->get_parent_id());
            }

            if ($parent) {
                $parent_attributes = $this->get_variation_parent_attributes($raw_attributes, $parent);
            }

            for ($i = 0; $i < $record_count; $i++) {

                $prefix = 'attributes.' . $i . '.';
                $name = $raw_attributes[$prefix . 'name'];
                $terms = $raw_attributes[$prefix . 'terms'];
                $global = $raw_attributes[$prefix . 'global'];
                $visible = $raw_attributes[$prefix . 'visible'];
                $use_variation = isset($raw_attributes[$prefix . 'variation']) ? $raw_attributes[$prefix . 'variation'] : '';

                if ($use_variation === 'no') {
                    $skipped++;
                    continue;
                }

                $permission_key = 'product_attributes.' . $i; //attributes.$name

                if ($data->permission()) {
                    $allowed = $data->permission()->validate([$permission_key => ''], $data->getMethod(), 'attributes');
                    $is_allowed = isset($allowed[$permission_key]) ? true : false;

                    if (!$is_allowed) {
                        $skipped++;
                        continue;
                    }
                }

                $attribute_id = 0;

                if ($global === 'yes') {
                    $attribute_id = $this->get_attribute_taxonomy_id($name);
                }

                if ($attribute_id) {
                    $attribute_name = wc_attribute_taxonomy_name_by_id($attribute_id);
                } else {
                    $attribute_name = sanitize_title($name);
                }

                // if parent has variation flag set then we use that.
                if (isset($parent_attributes[$attribute_name]) && $parent_attributes[$attribute_name]->get_variation()) {
                    $use_variation = 'yes';
                }

                // Add attribute onto parent
                // TODO: tidy up duplicate code

                $existing_options = isset($parent_attributes[$attribute_name]) ? $parent_attributes[$attribute_name]->get_options() : [];

                if (isset($terms)) {
                    if (!is_array($terms)) {
                        $attribute_delimiter = apply_filters('iwp/woocommerce/attribute/delimiter', ',');
                        $attribute_delimiter = apply_filters('iwp/woocommerce/attribute/' . $attribute_name . '/delimiter', $attribute_delimiter);
                        $terms = explode($attribute_delimiter, $terms);
                    }
                }

                if ($attribute_id) {
                    if (isset($terms)) {

                        $options = array_map('wc_sanitize_term_text_based', $terms);
                        $options = array_filter($options, 'strlen');
                    } else {
                        $options = array();
                    }

                    $options = array_unique(array_merge($existing_options, $options));

                    if (!empty($options)) {
                        $attribute_object = new \WC_Product_Attribute();
                        $attribute_object->set_id($attribute_id);
                        $attribute_object->set_name($attribute_name);
                        $attribute_object->set_options($options);
                        $attribute_object->set_variation($use_variation !== 'no');
                        $attribute_object->set_visible($visible === 'yes');
                        $parent_attributes[$attribute_name] = $attribute_object;
                        $update_parent = true;
                    }
                } elseif (isset($terms)) {

                    $attribute_object = new \WC_Product_Attribute();
                    $attribute_object->set_name($name);
                    $attribute_object->set_options($terms);
                    $attribute_object->set_variation($use_variation !== 'no');
                    $attribute_object->set_visible($visible === 'yes');
                    $parent_attributes[$attribute_name] = $attribute_object;
                    $update_parent = true;
                }

                if ($use_variation !== 'no' && $attribute_object) {

                    $attribute_key   = sanitize_title($attribute_object->get_name());
                    $attribute_value = is_array($terms) ? $terms[0] : $terms;


                    if ($attribute_object->is_taxonomy()) {
                        // If dealing with a taxonomy, we need to get the slug from the name posted to the API.
                        $term = get_term_by('name', $attribute_value, $attribute_name);

                        if ($term && !is_wp_error($term)) {
                            $attribute_value = $term->slug;
                        } else {
                            $attribute_value = sanitize_title($attribute_value);
                        }
                    }

                    $attributes[$attribute_key] = $attribute_value;
                }
            }
        }

        if ($record_count > $skipped) {
            $variation->set_attributes($attributes);
        }

        if ($parent && $update_parent) {
            $parent->set_attributes($parent_attributes);
            $parent->save();
        }
    }

    /**
     * @param \WC_Product $product
     * @param ParsedData $data
     *
     * @throws \Exception
     */
    public function set_product_data(&$product, $data)
    {

        if ($this->importer->isEnabledField('linked-products.upsell')) {

            $is_allowed = true;
            if ($data->permission()) {
                $permission_key = 'product_upsell';
                $allowed = $data->permission()->validate([$permission_key => ''], $data->getMethod(), 'linked-products');
                $is_allowed = isset($allowed[$permission_key]) ? true : false;
            }

            if ($is_allowed) {
                $product->set_upsell_ids($this->set_related_products($data, 'upsell'));
            }
        }

        if ($this->importer->isEnabledField('linked-products.crosssell')) {

            $is_allowed = true;
            if ($data->permission()) {
                $permission_key = 'product_crosssell';
                $allowed = $data->permission()->validate([$permission_key => ''], $data->getMethod(), 'linked-products');
                $is_allowed = isset($allowed[$permission_key]) ? true : false;
            }

            if ($is_allowed) {
                $product->set_cross_sell_ids($this->set_related_products($data, 'crosssell'));
            }
        }

        if ($this->importer->isEnabledField('linked-products.grouped')) {

            $is_allowed = true;
            if ($data->permission()) {
                $permission_key = 'product_grouped';
                $allowed = $data->permission()->validate([$permission_key => ''], $data->getMethod(), 'linked-products');
                $is_allowed = isset($allowed[$permission_key]) ? true : false;
            }

            if ($is_allowed) {
                $product->set_props([
                    'children' => $this->set_related_products($data, 'grouped')
                ]);
            }
        }

        $raw_attributes = $data->getData('attributes');
        $append_attributes = isset($raw_attributes['attributes._iwp_settings.append_attributes']) && $raw_attributes['attributes._iwp_settings.append_attributes'] == 'yes';
        $record_count = intval($raw_attributes['attributes._index']);

        $attributes          = [];
        $skipped = 0;

        if ($record_count > 0) {

            $default_attributes  = [];
            $existing_attributes = $product->get_attributes();

            for ($i = 0; $i < $record_count; $i++) {

                $prefix = 'attributes.' . $i . '.';
                $name = isset($raw_attributes[$prefix . 'name']) ? $raw_attributes[$prefix . 'name'] : '';
                $terms = isset($raw_attributes[$prefix . 'terms']) ? $raw_attributes[$prefix . 'terms'] : '';
                $global = isset($raw_attributes[$prefix . 'global']) ? $raw_attributes[$prefix . 'global'] : '';
                $visible = isset($raw_attributes[$prefix . 'visible']) ? $raw_attributes[$prefix . 'visible'] : '';
                $append = isset($raw_attributes[$prefix . 'append']) ? $raw_attributes[$prefix . 'append'] : '';
                $use_variation = isset($raw_attributes[$prefix . 'variation']) ? $raw_attributes[$prefix . 'variation'] : '';

                if ($data->permission()) {
                    $permission_key = 'product_attributes.' . $i;
                    $allowed = $data->permission()->validate([$permission_key => ''], $data->getMethod(), 'attributes');
                    $is_allowed = isset($allowed[$permission_key]) ? true : false;

                    if (!$is_allowed) {
                        $skipped++;
                        continue;
                    }
                }

                if (empty($name)) {
                    continue;
                }

                $attribute_id = 0;

                // Get ID if is a global attribute.
                if ($global === 'yes') {
                    $attribute_id = $this->get_attribute_taxonomy_id($name);
                }

                // Set attribute visibility.
                if (isset($visible)) {
                    $is_visible = $visible;
                } else {
                    $is_visible = 1;
                }

                // Get name.
                $attribute_name = $attribute_id ? wc_attribute_taxonomy_name_by_id($attribute_id) : $name;

                // allow to keep existing attributes
                $existing_options = isset($existing_attributes[$attribute_name]) ? $existing_attributes[$attribute_name]->get_options() : [];
                if ($append == 'no') {
                    $existing_options = [];
                }

                // Set if is a variation attribute based on existing attributes if possible so updates via CSV do not change this.
                $is_variation = 0;

                if ($use_variation !== 'no' && $existing_attributes) {

                    if ($use_variation === 'yes') {
                        $is_variation = 1;
                    } else {
                        foreach ($existing_attributes as $existing_attribute) {
                            if ($existing_attribute->get_name() === $attribute_name) {
                                $is_variation = $existing_attribute->get_variation();
                                break;
                            }
                        }
                    }
                }

                // convert csv of terms to array
                if (isset($terms)) {
                    if (!is_array($terms)) {
                        $attribute_delimiter = apply_filters('iwp/woocommerce/attribute/delimiter', ',');
                        $attribute_delimiter = apply_filters('iwp/woocommerce/attribute/' . $attribute_name . '/delimiter', $attribute_delimiter);
                        $terms = explode($attribute_delimiter, $terms);
                    }

                    // remove empty array elements
                    $terms = array_filter($terms);

                    // skip variation attributes if they are empty on the variable product.
                    if (apply_filters('iwp/wc_ignore_empty_variable_attributes', true) === true && $use_variation !== 'no' && $product->is_type('variable') && empty($terms)) {
                        continue;
                    }
                }

                if ($attribute_id) {
                    if (isset($terms)) {

                        $options = array_map('wc_sanitize_term_text_based', $terms);
                        $options = array_filter($options, 'strlen');
                    } else {
                        $options = array();
                    }

                    $options = array_unique(array_merge($existing_options, $options));

                    // Check for default attributes and set "is_variation".
                    // if (isset($attribute['default']) && !empty($attribute['default']) && in_array($attribute['default'], $options, true)) {
                    //     $default_term = get_term_by('name', $attribute['default'], $attribute_name);

                    //     if ($default_term && !is_wp_error($default_term)) {
                    //         $default = $default_term->slug;
                    //     } else {
                    //         $default = sanitize_title($attribute['default']);
                    //     }

                    //     $default_attributes[$attribute_name] = $default;
                    //     $is_variation                          = 1;
                    // }

                    if (!empty($options)) {
                        $attribute_object = new \WC_Product_Attribute();
                        $attribute_object->set_id($attribute_id);
                        $attribute_object->set_name($attribute_name);
                        $attribute_object->set_options($options);
                        $attribute_object->set_position($i);
                        $attribute_object->set_visible($is_visible);
                        $attribute_object->set_variation($is_variation);
                        $attributes[] = $attribute_object;
                    }
                } elseif (isset($terms)) {
                    // Check for default attributes and set "is_variation".
                    // if (isset($attribute['default']) && !empty($attribute['default']) && in_array($attribute['default'], $terms, true)) {
                    //     $default_attributes[sanitize_title($name)] = $attribute['default'];
                    //     $is_variation = 1;
                    // }

                    $attribute_object = new \WC_Product_Attribute();
                    $attribute_object->set_name($name);
                    $attribute_object->set_options($terms);
                    $attribute_object->set_position($i);
                    $attribute_object->set_visible($is_visible);
                    $attribute_object->set_variation($is_variation);
                    $attributes[] = $attribute_object;
                }
            }
        }

        if ($record_count > $skipped) {

            $append_attributes = apply_filters('iwp/woocommerce/append_attributes', $append_attributes);
            if ($append_attributes) {

                $append_list = [];

                foreach ($attributes as $attribute) {
                    if (isset($existing_attributes[$attribute->get_name()])) {
                        $existing_attributes[$attribute->get_name()] = $attribute;
                    } else {
                        $append_list[] = $attribute;
                    }
                }

                $attributes = array_merge($existing_attributes, $append_list);
            }

            $product->set_attributes($attributes);
        }

        // Set variable default attributes.
        if ($product->is_type('variable')) {
            $product->set_default_attributes($default_attributes);
        }
    }

    /**
     * Get variation parent attributes and set "is_variation".
     *
     * @param array $attributes Attributes list.
     * @param WC_Product $parent Parent product data.
     *
     * @return array
     * @throws \Exception
     */
    protected function get_variation_parent_attributes($raw_attributes, $parent)
    {
        $parent_attributes = $parent->get_attributes();
        $require_save      = false;

        $record_count = intval($raw_attributes['attributes._index']);

        for ($i = 0; $i < $record_count; $i++) {
            $prefix = 'attributes.' . $i . '.';
            $name = $raw_attributes[$prefix . 'name'];
            $terms = $raw_attributes[$prefix . 'terms'];
            $global = $raw_attributes[$prefix . 'global'];
            $visible = $raw_attributes[$prefix . 'visible'];
            $use_variation = isset($raw_attributes[$prefix . 'variation']) ? $raw_attributes[$prefix . 'variation'] : '';

            $attribute_id = 0;

            // Get ID if is a global attribute.
            if ($global === 'yes') {
                $attribute_id = $this->get_attribute_taxonomy_id($name);
            }

            if ($attribute_id) {
                $attribute_name = wc_attribute_taxonomy_name_by_id($attribute_id);
            } else {
                $attribute_name = sanitize_title($name);
            }

            // Check if attribute handle variations.
            if (isset($parent_attributes[$attribute_name]) && !$parent_attributes[$attribute_name]->get_variation() && $use_variation !== 'no') {
                // Re-create the attribute to CRUD save and generate again.
                $parent_attributes[$attribute_name] = clone $parent_attributes[$attribute_name];
                $parent_attributes[$attribute_name]->set_variation(1);

                $require_save = true;
            }
        }

        // Save variation attributes.
        if ($require_save) {
            $parent->set_attributes(array_values($parent_attributes));
            $parent->save();
        }

        return $parent_attributes;
    }

    /**
     * Get attribute taxonomy ID from the imported data.
     * If does not exists register a new attribute.
     *
     * @param  string $raw_name Attribute name.
     * @return int
     * @throws \Exception If taxonomy cannot be loaded.
     */
    public function get_attribute_taxonomy_id($raw_name)
    {
        global $wpdb, $wc_product_attributes;

        // These are exported as labels, so convert the label to a name if possible first.
        $attribute_labels = wp_list_pluck(wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name');
        $attribute_name   = array_search($raw_name, $attribute_labels, true);

        if (!$attribute_name) {
            $attribute_name = wc_sanitize_taxonomy_name($raw_name);
        }

        $attribute_id = wc_attribute_taxonomy_id_by_name($attribute_name);

        // Get the ID from the name.
        if ($attribute_id) {
            return $attribute_id;
        }

        // If the attribute does not exist, create it.
        $attribute_id = wc_create_attribute(
            array(
                'name'         => $raw_name,
                'slug'         => $attribute_name,
                'type'         => 'select',
                'order_by'     => 'menu_order',
                'has_archives' => false,
            )
        );

        if (is_wp_error($attribute_id)) {
            throw new \Exception($attribute_id->get_error_message(), 400);
        }

        // Register as taxonomy while importing.
        $taxonomy_name = wc_attribute_taxonomy_name($attribute_name);
        register_taxonomy(
            $taxonomy_name,
            apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, array('product')),
            apply_filters(
                'woocommerce_taxonomy_args_' . $taxonomy_name,
                array(
                    'labels'       => array(
                        'name' => $raw_name,
                    ),
                    'hierarchical' => true,
                    'show_ui'      => false,
                    'query_var'    => true,
                    'rewrite'      => false,
                )
            )
        );

        // Set product attributes global.
        $wc_product_attributes = array();

        foreach (wc_get_attribute_taxonomies() as $taxonomy) {
            $wc_product_attributes[wc_attribute_taxonomy_name($taxonomy->attribute_name)] = $taxonomy;
        }

        return $attribute_id;
    }

    /**
     * Process related products
     *
     * @param ParsedData $data
     * @param string $field_id
     * @return void
     */
    private function set_related_products($data, $field_id)
    {
        $group = 'linked-products';
        $raw_data_value = $data_value = $data->getValue($group . '.' . $field_id . '.products', $group);
        $type = $data->getValue('linked-products.' . $field_id . '._field_type', $group);


        $data_value = apply_filters("iwp/wc_product_field", $data_value, $raw_data_value, $field_id);
        $data_value = apply_filters("iwp/wc_product_field/{$field_id}", $data_value, $raw_data_value);

        if (!$data_value) {
            return;
        }

        $product_ids = array();
        $parts = explode(',', $data_value);
        $parts = array_filter(array_map('trim', $parts));
        if (!empty($parts)) {

            // TODO: work with products that dont exist yet
            //     $ref_key = sprintf('_iwp_wc_%d', JCI()->importer->get_version());
            //     $iwp_wc = maybe_unserialize(get_post_meta(JCI()->importer->get_ID(), $ref_key, true));
            //     if (!$iwp_wc) {
            //         $iwp_wc = array();
            //     }

            //     if (!isset($iwp_wc[$key])) {
            //         $iwp_wc[$key] = array();
            //     }

            foreach ($parts as $trimmed_sku) {

                if (empty($trimmed_sku)) {
                    continue;
                }
                $id = intval($trimmed_sku);
                if ($type === '_sku') {
                    $id = intval($this->get_product_id_by_sku($trimmed_sku));

                    // TODO: work with products that dont exist yet
                    // if (!$id) {

                    //     if (!isset($iwp_wc[$key][$trimmed_sku])) {
                    //         $iwp_wc[$key][$trimmed_sku] = array();
                    //         $iwp_wc[$key . '_field_type'] = $type;
                    //     }

                    //     $iwp_wc[$key][$trimmed_sku][] = $data['ID'];
                    // }
                } elseif ($type === 'name') {
                    $id = intval($this->get_product_id_by_name($trimmed_sku));
                } elseif ($type === 'slug') {
                    $id = intval($this->get_product_id_by_slug($trimmed_sku));
                }

                if ($id > 0 && !in_array($id, $product_ids, true)) {
                    $product_ids[] = $id;
                }
            }

            // TODO: work with products that dont exist yet
            //     update_post_meta(JCI()->importer->get_ID(), $ref_key, serialize($iwp_wc));
        }

        return $product_ids;
    }

    /**
     * Retrive a list of imported taxonomies via the Taxonomies section of the importer
     *
     * @return mixed
     */
    public function get_importer_taxonomies()
    {
        return $this->_taxonomies;
    }

    public function get_product_id_by_sku($sku)
    {
        return $this->get_product_id_by_field('sku', $sku);
    }

    public function get_product_id_by_name($name)
    {
        return $this->get_product_id_by_field('name', $name);
    }

    public function get_product_id_by_slug($slug)
    {
        return $this->get_product_id_by_field('slug', $slug);
    }

    public function get_product_id_by_field($field, $value)
    {
        if (empty($value) || empty($field)) {
            return false;
        }

        $query_args = array(
            'post_type'      => $this->importer->getSetting('post_type'),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'post_status' => 'any, trash, future'
        );

        switch ($field) {
            case 'sku':
                $query_args['meta_query'] = array(
                    array(
                        'key'   => '_sku',
                        'value' => $value
                    )
                );
                break;
            case 'meta':

                if (!is_array($value)) {
                    return false;
                }

                $meta_query_args = [];

                foreach ($value as $meta_key => $meta_value) {

                    if (empty($meta_key) || empty($meta_value)) {
                        continue;
                    }

                    $meta_query_args[] = array(
                        'key'   => $meta_key,
                        'value' => $meta_value
                    );
                }

                if (empty($meta_query_args)) {
                    return false;
                }

                $query_args['meta_query'] = $meta_query_args;
                break;
            case 'name':
                $query_args['title'] = $value;
                break;
            case 'slug':
                $query_args['name'] = $value;
                break;
            default:
                return false;
        }

        $query = new \WP_Query($query_args);

        if ($query->have_posts()) {
            return $query->posts[0];
        }

        return false;
    }

    private function clear_field_log()
    {
        $this->_fields = [];
    }

    private function log_field($field)
    {
        $ignore_list = [
            'post_title',
            'post_name',
            'post_content',
            'post_excerpt',
            'post_status',
            '_sku' // sku is moved into default group
        ];

        if (!in_array($field, $ignore_list)) {
            $this->_fields[] = $field;
        }
    }

    /**
     * @param string $message
     * @param int $id
     * @param ParsedData $data
     * @return $string
     */
    public function display_record_info($message, $id, $data)
    {
        $output = parent::display_record_info($message, $id, $data);

        if (!empty($this->_fields)) {
            $output .= ' (' . implode(', ', $this->_fields) . ')';
        }

        return $output;
    }


    public function get_permission_fields($importer_model)
    {
        $permission_fields = parent::get_permission_fields($importer_model);

        $permission_fields['woocommerce'] = [

            'product_type' => 'Product Type',
            '_virtual' => 'Virtual',
            '_downloadable' => 'Downloadable',
            '_visibility' => 'Catalog visibility',
            '_product_url' => 'Product URL',
            '_button_text' => 'Product URL Button Text',
            'tax_status' => 'Tax Status',
            'tax_class' => 'Tax Class',

            // advanced
            '_purchase_note' => 'Purchase note',
            '_download_limit' => 'Download Limit',
            '_download_expiry' => 'Download Expiry',
        ];

        $permission_fields['woocommerce_price'] = [

            // Price
            '_regular_price' => 'Regular Price',
            '_sale_price' => 'Sale Price',
            '_sale_price_dates_to' => 'Sale Price To',
            '_sale_price_dates_from' => 'Sale Price From',
        ];

        $permission_fields['woocommerce_inventory'] = [

            // inventory
            '_sku' => 'Product SKU',
            '_stock_status' => 'Stock status',
            '_manage_stock' => 'Manage Stock',
            '_stock' => 'Stock quantity',
            '_backorders' => 'Allow back-orders',
            '_low_stock_amount' => 'Low stock threshold',
            '_sold_individually' => 'Sold individually',
        ];

        $permission_fields['woocommerce_shipping'] = [

            // shipping
            '_weight' => 'Weight',
            '_length' => 'Length',
            '_width' => 'Width',
            '_height' => 'Height',
        ];

        $field_map = $importer_model->getMap();
        if (isset($field_map['attributes._index']) && $field_map['attributes._index'] > 0) {
            $permission_fields['product_attributes'] = [];
            for ($i = 0; $i < $field_map['attributes._index']; $i++) {
                $permission_fields['product_attributes']['product_attributes.' . $i] = 'Attributes Row ' . ($i + 1);
            }
        }

        $field_map = $importer_model->getMap();
        if (isset($field_map['downloads._index']) && $field_map['downloads._index'] > 0) {
            $permission_fields['product_downloads'] = [];
            for ($i = 0; $i < $field_map['downloads._index']; $i++) {
                $permission_fields['product_downloads']['product_downloads.' . $i] = 'Product Downloads Row ' . ($i + 1);
            }
        }

        $field_map = $importer_model->getMap();
        if (isset($field_map['product_gallery._index']) && $field_map['product_gallery._index'] > 0) {
            $permission_fields['product_gallery'] = [];
            for ($i = 0; $i < $field_map['product_gallery._index']; $i++) {
                $permission_fields['product_gallery']['product_gallery.' . $i] = 'Product Gallery Row ' . ($i + 1);
            }
        }

        $permission_fields['woocommerce_related_products'] = [
            'product_upsell' => 'Product Upsells',
            'product_crosssell' => 'Product Cross-sells',
            'product_grouped' => 'Product Grouped',
        ];

        return $permission_fields;
    }

    /**
     * Convert fields/headings to data map
     * 
     * @param mixed $fields
     * @param ImporterModel $importer
     * @return array 
     */
    public function generate_field_map($fields, $importer)
    {
        // Filter stops the enable field of posts showing parent by mistake.
        // due to parent being moved to advanced section
        add_filter('iwp/importer/generate_field_map/parent', '__return_empty_array');

        $result = parent::generate_field_map($fields, $importer);

        remove_filter('iwp/importer/generate_field_map/parent', '__return_empty_array');

        $map = $result['map'];
        $enabled = $result['enabled'];

        $attributes = [];
        $parent = [];
        $attachments = [];
        $linked_products = [];
        $downloadable_files = [];

        foreach ($fields as $index => $field) {

            if (preg_match('/^product_attributes\.(.*?)::(.*?)$/', $field, $matches) === 1) {

                $attribute = $matches[1];
                if (!isset($attributes[$attribute])) {
                    $attributes[$attribute] = [
                        'map' => []
                    ];
                }

                $attributes[$attribute]['map'][$matches[2]] = sprintf('{%d}', $index);

                continue;
            } elseif (preg_match('/^parent\.(.*?)$/', $field, $matches) === 1) {

                // Capture parent
                // parent.id,parent.name,parent.slug
                if (!isset($parent['map'])) {
                    $parent['map'] = [];
                }

                $parent['map'][$matches[1]] = sprintf('{%d}', $index);
                continue;
            } elseif (preg_match('/^product_gallery\.(.*?)$/', $field, $matches) === 1) {

                // Capture featured image.
                // image.id,url, title, alt, caption, description
                if (!isset($attachments['product_gallery'])) {
                    $attachments['product_gallery'] = [
                        'map' => []
                    ];
                }

                $attachments['product_gallery']['map'][$matches[1]] = sprintf('{%d}', $index);
                $attachments['product_gallery']['map']['featured'] = 'yes';

                continue;
            } elseif (preg_match('/^linked_products\.(.*?)::(.*?)$/', $field, $matches) === 1) {

                // Capture featured image.
                // image.id,url, title, alt, caption, description
                if (!isset($linked_products[$matches[1]])) {
                    $linked_products[$matches[1]] = [
                        'map' => []
                    ];
                }

                $linked_products[$matches[1]]['map'][$matches[2]] = sprintf('{%d}', $index);

                continue;
            } elseif (preg_match('/^downloadable_files\.(.*?)$/', $field, $matches) === 1) {

                // downloadable_files
                if (!isset($downloadable_files['map'])) {
                    $downloadable_files['map'] = [];
                }

                $downloadable_files['map'][$matches[1]] = sprintf('{%d}', $index);
                continue;
            }


            switch ($field) {

                    // product field
                case 'custom_fields._downloadable':
                case 'woocommerce.downloadable':
                    $map['post._downloadable'] = sprintf('{%d}', $index);
                    $map['post._downloadable._enable_text'] = 'yes';
                    $enabled[] = 'post._downloadable';
                    break;
                case 'custom_fields._visibility':
                case 'woocommerce.visibility':
                    $map['post._visibility'] = sprintf('{%d}', $index);
                    $map['post._visibility._enable_text'] = 'yes';
                    $enabled[] = 'post._visibility';
                    break;
                case 'custom_fields.product_type':
                case 'woocommerce.product_type':
                    $map['post.product_type'] = sprintf('{%d}', $index);
                    $map['post.product_type._enable_text'] = 'yes';
                    $enabled[] = 'post.product_type';
                    break;
                case 'custom_fields._virtual':
                case 'woocommerce.virtual':
                    $map['post._virtual'] = sprintf('{%d}', $index);
                    $map['post._virtual._enable_text'] = 'yes';
                    $enabled[] = 'post._virtual';
                    break;

                    // price
                case 'custom_fields._regular_price':
                case 'woocommerce.regular_price':
                    $map['price._regular_price'] = sprintf('{%d}', $index);
                    break;
                case 'custom_fields._sale_price':
                case 'woocommerce.sale_price':
                    $map['price.sale._sale_price'] = sprintf('{%d}', $index);

                    if (!in_array('price.sale', $enabled)) {
                        $enabled[] = 'price.sale';
                    }
                    break;
                case 'custom_fields._sale_price_dates_to':
                case 'woocommerce.date_on_sale_to':
                    $map['price.sale._sale_price_dates_to'] = sprintf('{%d}', $index);

                    if (!in_array('price.sale', $enabled)) {
                        $enabled[] = 'price.sale';
                    }
                    break;
                case 'custom_fields._sale_price_dates_from':
                case 'woocommerce.date_on_sale_from':
                    $map['price.sale._sale_price_dates_from'] = sprintf('{%d}', $index);

                    if (!in_array('price.sale', $enabled)) {
                        $enabled[] = 'price.sale';
                    }
                    break;

                    // inventory
                case 'sku':
                case 'custom_fields._sku':
                    $map['inventory._sku'] = sprintf('{%d}', $index);
                    break;
                case 'custom_fields._stock_status':
                case 'woocommerce.stock_status':
                    $map['inventory.stock._stock_status'] = sprintf('{%d}', $index);
                    $map['inventory.stock._stock_status._enable_text'] = 'yes';

                    if (!in_array('inventory.stock', $enabled)) {
                        $enabled[] = 'inventory.stock';
                    }
                    break;
                case 'custom_fields._manage_stock':
                case 'woocommerce.manage_stock':
                    $map['inventory.stock._manage_stock'] = sprintf('{%d}', $index);
                    $map['inventory.stock._manage_stock._enable_text'] = 'yes';

                    if (!in_array('inventory.stock', $enabled)) {
                        $enabled[] = 'inventory.stock';
                    }
                    break;
                case 'custom_fields._stock':
                case 'woocommerce.stock':
                    $map['inventory.stock._stock'] = sprintf('{%d}', $index);

                    if (!in_array('inventory.stock', $enabled)) {
                        $enabled[] = 'inventory.stock';
                    }
                    break;
                case 'custom_fields._backorders':
                case 'woocommerce.backorders':
                    $map['inventory.stock._backorders'] = sprintf('{%d}', $index);
                    $map['inventory.stock._backorders._enable_text'] = 'yes';

                    if (!in_array('inventory.stock', $enabled)) {
                        $enabled[] = 'inventory.stock';
                    }
                    break;
                case 'custom_fields._low_stock_amount':
                case 'woocommerce.low_stock_amount':
                    $map['inventory.stock._low_stock_amount'] = sprintf('{%d}', $index);

                    if (!in_array('inventory.stock', $enabled)) {
                        $enabled[] = 'inventory.stock';
                    }
                    break;
                case 'custom_fields._sold_individually':
                case 'woocommerce.sold_individually':
                    $map['inventory.stock._sold_individually'] = sprintf('{%d}', $index);
                    $map['inventory.stock._sold_individually._enable_text'] = 'yes';

                    if (!in_array('inventory.stock', $enabled)) {
                        $enabled[] = 'inventory.stock';
                    }
                    break;

                    // shipping
                case 'woocommerce.weight':
                case 'custom_fields._weight':
                    $map['shipping.dimensions._weight'] = sprintf('{%d}', $index);

                    if (!in_array('shipping.dimensions', $enabled)) {
                        $enabled[] = 'shipping.dimensions';
                    }
                    break;
                case 'woocommerce.length':
                case 'custom_fields._length':
                    $map['shipping.dimensions._length'] = sprintf('{%d}', $index);

                    if (!in_array('shipping.dimensions', $enabled)) {
                        $enabled[] = 'shipping.dimensions';
                    }
                    break;
                case 'woocommerce.width':
                case 'custom_fields._width':
                    $map['shipping.dimensions._width'] = sprintf('{%d}', $index);

                    if (!in_array('shipping.dimensions', $enabled)) {
                        $enabled[] = 'shipping.dimensions';
                    }
                    break;
                case 'woocommerce.height':
                case 'custom_fields._height':
                    $map['shipping.dimensions._height'] = sprintf('{%d}', $index);

                    if (!in_array('shipping.dimensions', $enabled)) {
                        $enabled[] = 'shipping.dimensions';
                    }
                    break;

                    // advanced
                case 'custom_fields._purchase_note':
                case 'woocommerce.purchase_note':
                    $map['advanced._purchase_note'] = sprintf('{%d}', $index);

                    if (!in_array('advanced._purchase_note', $enabled)) {
                        $enabled[] = 'advanced._purchase_note';
                    }
                    break;
                case 'custom_fields._download_limit':
                case 'woocommerce.download_limit':
                    $map['advanced._download_limit'] = sprintf('{%d}', $index);

                    if (!in_array('advanced._download_limit', $enabled)) {
                        $enabled[] = 'advanced._download_limit';
                    }
                    break;
                case 'custom_fields._download_expiry':
                case 'woocommerce.download_expiry':
                    $map['advanced._download_expiry'] = sprintf('{%d}', $index);

                    if (!in_array('advanced._download_expiry', $enabled)) {
                        $enabled[] = 'advanced._download_expiry';
                    }
                    break;
                case 'custom_fields._product_url':
                case 'woocommerce.product_url':
                    $map['post.external._product_url'] = sprintf('{%d}', $index);

                    if (!in_array('post.external', $enabled)) {
                        $enabled[] = 'post.external';
                    }
                    break;
                case 'custom_fields._button_text':
                case 'woocommerce.button_text':
                    $map['post.external._button_text'] = sprintf('{%d}', $index);

                    if (!in_array('post.external', $enabled)) {
                        $enabled[] = 'post.external';
                    }
                    break;
            }
        }

        // TODO: product attributes INLINE / GLOBAL (starts with pa_ and taxonomy_exists)
        $attributes = apply_filters('iwp/woocommerce/generate_field_map/attributes', $attributes, $fields, $importer);
        if (!empty($attributes)) {

            $attribute_counter = 0;
            $defaults = [
                'row_base' => '',
                'name' => '',
                'terms' => '',
                'global' => '',
                'visible' => '',
                'append' => '',
                'variation' => '',
            ];

            foreach ($attributes as $attribute => $attribute_data) {
                $data = [];

                if (preg_match('/^pa_(.*?)$/', $attribute, $matches) && taxonomy_exists($attribute)) {
                    // global
                    $data['global'] = 'yes';
                    $data['name'] = $matches[1];
                } else {
                    // custom
                    $data['global'] = 'no';
                    $data['name'] = $attribute;
                }

                $data['terms'] = isset($attribute_data['map']['name']) ? $attribute_data['map']['name'] : '';
                $data['visible'] = isset($attribute_data['map']['visible']) ? $attribute_data['map']['visible'] : '';
                $data['variation'] = isset($attribute_data['map']['variation']) ? $attribute_data['map']['variation'] : '';

                if (!empty($data['visible'])) {
                    $data['visible._enable_text'] = 'yes';
                }
                if (!empty($data['variation'])) {
                    $data['variation._enable_text'] = 'yes';
                }

                $data = wp_parse_args($data, $defaults);


                $map = array_merge($map, array_reduce(array_keys($data), function ($carry, $key) use ($data, $attribute_counter) {
                    $carry[sprintf('attributes.%d.%s', $attribute_counter, $key)] = $data[$key];
                    return $carry;
                }, []));

                $attribute_counter++;
            }

            $map['attributes._index'] = $attribute_counter;
        }

        // Parent
        if (!empty($parent)) {

            // parent.id,parent.name,parent.slug
            $enabled_key = 'advanced._parent';
            if (isset($parent['map']['sku'])) {

                $enabled[] = $enabled_key;
                $map['advanced._parent.parent'] = $parent['map']['sku'];
                $map['advanced._parent.parent._enable_text'] = 'yes';
                $map['advanced._parent._parent_type'] = 'sku';
            } elseif (isset($parent['map']['sku'])) {

                $enabled[] = $enabled_key;
                $map['advanced._parent.parent'] = $parent['map']['name'];
                $map['advanced._parent.parent._enable_text'] = 'yes';
                $map['advanced._parent._parent_type'] = 'name';
            } elseif (isset($parent['map']['slug'])) {

                $enabled[] = $enabled_key;
                $map['advanced._parent.parent'] = $parent['map']['slug'];
                $map['advanced._parent.parent._enable_text'] = 'yes';
                $map['advanced._parent._parent_type'] = 'slug';
            } elseif (isset($parent['map']['id'])) {

                $enabled[] = $enabled_key;
                $map['advanced._parent.parent'] = $parent['map']['name'];
                $map['advanced._parent.parent._enable_text'] = 'yes';
                $map['advanced._parent._parent_type'] = 'id';
            }
        }

        // Linked Products
        if (!empty($linked_products)) {

            list($map, $enabled) = $this->generate_linked_products_field_map('crosssell', $map, $enabled, $linked_products);
            list($map, $enabled) = $this->generate_linked_products_field_map('upsell', $map, $enabled, $linked_products);
            list($map, $enabled) = $this->generate_linked_products_field_map('grouped', $map, $enabled, $linked_products);
        }

        // product gallery
        if (!empty($attachments)) {
            $attachment_counter = 0;
            $defaults = [
                'row_base' => '',
                'location' => '',
                'settings._delimiter' => '',
                'settings._download' => 'remote',
                'settings._enable_image_hash' => 'yes',
                'settings._featured' => 'no',
                'settings._ftp_host' => '',
                'settings._ftp_pass' => '',
                'settings._ftp_path' => '',
                'settings._ftp_user' => '',
                'settings._local_url' => '',
                'settings._meta._alt' => '',
                'settings._meta._caption' => '',
                'settings._meta._description' => '',
                'settings._meta._enabled' => 'no',
                'settings._meta._title' => '',
                'settings._remote_url' => '',
            ];

            foreach ($attachments as $attachment_data) {

                $data = [];

                if (!isset($attachment_data['map']['url'])) {
                    continue;
                }

                // location
                $data['location'] = $attachment_data['map']['url'];

                if (isset($attachment_data['map']['featured'])) {
                    $data['settings._featured'] = $attachment_data['map']['featured'];
                }

                // Meta
                if (isset($attachment_data['map']['title'])) {
                    $data['settings._meta._title'] = $attachment_data['map']['title'];
                    $data['settings._meta._enabled'] = 'yes';
                }
                if (isset($attachment_data['map']['alt'])) {
                    $data['settings._meta._alt'] = $attachment_data['map']['alt'];
                    $data['settings._meta._enabled'] = 'yes';
                }
                if (isset($attachment_data['map']['description'])) {
                    $data['settings._meta._description'] = $attachment_data['map']['description'];
                    $data['settings._meta._enabled'] = 'yes';
                }
                if (isset($attachment_data['map']['caption'])) {
                    $data['settings._meta._caption'] = $attachment_data['map']['caption'];
                    $data['settings._meta._enabled'] = 'yes';
                }

                $data = wp_parse_args($data, $defaults);

                $map = array_merge($map, array_reduce(array_keys($data), function ($carry, $key) use ($data, $attachment_counter) {
                    $carry[sprintf('product_gallery.%d.%s', $attachment_counter, $key)] = $data[$key];
                    return $carry;
                }, []));

                $attachment_counter++;
            }

            $map['product_gallery._index'] = $attachment_counter;
        }

        // Product downlaods
        if (!empty($downloadable_files)) {

            if (isset($downloadable_files['map']['name'])) {
                $map['downloads.0.name'] = $downloadable_files['map']['name'];
            }
            if (isset($downloadable_files['map']['file'])) {
                $map['downloads.0.file'] = $downloadable_files['map']['file'];
            }

            $map['downloads._index'] = 1;
        }

        return [
            'map' => $map,
            'enabled' => $enabled
        ];
    }

    public function generate_linked_products_field_map($field, $map, $enabled, $linked_products)
    {
        if (!isset($linked_products[$field])) {
            return [$map, $enabled];
        }

        if (isset($linked_products[$field]['map']['sku'])) {
            $map[sprintf('linked-products.%s._field_type', $field)] = '_sku';
            $map[sprintf('linked-products.%s.products', $field)] = $linked_products[$field]['map']['sku'];
            // $map[sprintf('linked-products.%s._field_type._enable_text', $field)] = 'yes';
        } elseif (isset($linked_products[$field]['map']['name'])) {
            $map[sprintf('linked-products.%s._field_type', $field)] = 'name';
            $map[sprintf('linked-products.%s.products', $field)] = $linked_products[$field]['map']['name'];
            // $map[sprintf('linked-products.%s._field_type._enable_text', $field)] = 'yes';
        } elseif (isset($linked_products[$field]['map']['slug'])) {
            $map[sprintf('linked-products.%s._field_type', $field)] = 'slug';
            $map[sprintf('linked-products.%s.products', $field)] = $linked_products[$field]['map']['slug'];
            // $map[sprintf('linked-products.%s._field_type._enable_text', $field)] = 'yes';
        } elseif (isset($linked_products[$field]['map']['id'])) {
            $map[sprintf('linked-products.%s._field_type', $field)] = 'id';
            $map[sprintf('linked-products.%s.products', $field)] = $linked_products[$field]['map']['id'];
            // $map[sprintf('linked-products.%s._field_type._enable_text', $field)] = 'yes';
        }

        $enabled[] = sprintf('linked-products.%s', $field);

        return [$map, $enabled];
    }

    public function generate_field_map_remove_wc_custom_fields($custom_fields)
    {

        foreach (
            [
                '_virtual',
                '_downloadable',
                '_visibility',
                'product_type',
                '_regular_price',
                '_sale_price',
                '_sale_price_dates_to',
                '_sale_price_dates_from',
                '_sku',
                '_stock_status',
                '_manage_stock',
                '_stock',
                '_backorders',
                '_low_stock_amount',
                '_sold_individually',
                '_weight',
                '_length',
                '_width',
                '_height',
                '_purchase_note',
                '_download_limit',
                '_download_expiry',
                '_upsell_ids',
                '_crosssell_ids',
                '_price',
            ] as $key
        ) {
            if (isset($custom_fields[$key])) {
                unset($custom_fields[$key]);
            }
        }

        return $custom_fields;
    }
}
