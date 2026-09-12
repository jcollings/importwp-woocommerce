<?php

namespace ImportWPAddon\WooCommerce\Importer\Mapper;

use ImportWP\Common\Importer\Mapper\UserMapper;
use ImportWP\Common\Importer\MapperInterface;
use ImportWP\Common\Importer\ParsedData;

class CustomerMapper extends UserMapper implements MapperInterface
{
    /**
     * Only used for backwards compatibility with ImportWP
     * @var string[]
     */
    protected $_unique_fields = ['ID', 'user_email', 'user_login'];

    public function insert(ParsedData $data)
    {
        $fields = $data->getData('default');

        if (empty($fields['role'])) {
            $fields['role'] = 'customer';
            $data->replace($fields, 'default');
        }

        return parent::insert($data);
    }
}
