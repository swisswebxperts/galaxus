<?php

class GalaxusConfigurationStorage extends ConfigurationStorage
{
    /**
     * namespace inside the configuration storage
     */
    const CONFIG_STORAGE_NAMESPACE = 'modules/swix/galaxus';

    /**
     * array holding default values to be used in absence of configured values
     */
    protected $default_configuration;

    protected $defaultStatus = [
        [
            'de' => 'Galaxus bezahlt',
            'en' => 'Galaxus payed',
            'color' => '2196F3',
        ], [
            'de' => 'Galaxus versendet',
            'en' => 'Galaxus sent',
            'color' => '45a845',
        ]
    ];

    /**
     * constructor; initializes default configuration
     */
    public function __construct()
    {
        parent::__construct(self::CONFIG_STORAGE_NAMESPACE);

        $this->checkGalaxusStatusExisting();
        $this->setDefaultConfiguration();
    }


    /**
     * fills $default_configuration with initial values
     */
    protected function setDefaultConfiguration()
    {
        $this->default_configuration = [
            'product_export/active' => '0',
            'product_export/testmode' => '1',
            'product_export/temporary_disabled' => '0',
            'product_export/ftp/enable' => '0',
            'product_export/ftp/server' => 'ftp.digitecgalaxus.ch',
            'product_export/ftp/port' => '22',
            'product_export/ftp/username' => '',
            'product_export/ftp/password' => '',
            'product_export/ftp/folder_prod' => '/ProductData/',
            'product_export/ftp/folder_test' => '/ProductDataTest/',
            'product_export/file_suffix' => '',
            'product_export/brand_name' => '',
            'product_export/currency' => $this->getDefaultCurrencyCode(),
            'product_export/round_5_cent' => '0',
            'product_export/shipping' => '0:80%,11:30%,51:20%,101:8%,401:6%',
            'product_export/export_path' => DIR_FS_CATALOG . 'export/galaxus/products/',
            'product_export/logfile' => DIR_FS_CATALOG . 'logfiles/galaxus_export.txt',

            'order_import/active' => '0',
            'order_import/testmode' => '1',
            'order_import/ftp/server' => 'ftp.digitecgalaxus.ch',
            'order_import/ftp/port' => '22',
            'order_import/ftp/username' => '',
            'order_import/ftp/password' => '',
            'order_import/ftp/folder_prod' => '/OrderData/Live/',
            'order_import/ftp/folder_test' => '/OrderData/Test/',
            'order_import/order_status' => $this->getDefaultOrderStatus(0),
            'order_import/send_email_confirmation' => '0',
            'order_import/send_order_response' => '1',
            'order_import/send_dispatch_notification' => '1',
            'order_import/dispatch_notification_order_status' => $this->getDefaultOrderStatus(1),
            'order_import/send_cancellation_notification' => '1',
            ];
    }


    /**
     * returns a single configuration value by its key
     *
     * @param string $key a configuration key (relative to the namespace prefix)
     *
     * @return string configuration value
     */
    public function get($key)
    {
        $value = parent::get($key);

        if ($value === false && array_key_exists($key, $this->default_configuration)) {
            $value = $this->default_configuration[$key];
        }

        if ($key == 'product_export/shipping') {
            $value = $this->getShippingArray($value);
        }

        return $value;
    }

    protected function getShippingArray($value)
    {
        $newValue = [];
        $shipping_parts = explode(',', $value);
        foreach($shipping_parts as $shipping_part) {
            $newValue[] = explode(':', $shipping_part);
        }

        return $newValue;
    }

    protected function getShippingString($valueArrays)
    {
        $tmpValues = [];
        foreach($valueArrays as $valueArray) {

            if ($valueArray[0] != '') {
                $tmpValues[] = implode(':', $valueArray);
            }
        }

        return implode(',', $tmpValues);
    }

    /**
     * Retrieves all keys/values from a given prefix namespace
     *
     * @param string $p_prefix
     *
     * @return array
     */
    public function get_all($p_prefix = '')
    {
        $values = parent::get_all($p_prefix);
        foreach ($this->default_configuration as $key => $default_value) {
            $key_prefix = substr($key, 0, strlen($p_prefix));
            if (!array_key_exists($key, $values) && $key_prefix === $p_prefix) {
                $values[$key] = $default_value;
            }
        }

        if (isset($values['product_export/shipping'])) {
            $values['product_export/shipping'] = $this->getShippingArray($values['product_export/shipping']);
        }

        return $values;
    }

    public function set($p_key, $p_value)
    {
        switch ($p_key) {
            case 'product_export/ftp/server':
            case 'product_export/ftp/port':
            case 'product_export/ftp/username':
            case 'product_export/ftp/password':
            case 'product_export/ftp/folder_prod':
            case 'product_export/ftp/folder_test':
            case 'product_export/file_suffix':
            case 'product_export/brand_name':

            case 'order_import/ftp/server':
            case 'order_import/ftp/port':
            case 'order_import/ftp/username':
            case 'order_import/ftp/password':
            case 'order_import/ftp/folder_prod':
            case 'order_import/ftp/folder_test':
                $value = trim(strip_tags($p_value));
                break;

            case 'product_export/active':
            case 'product_export/testmode':
            case 'product_export/ftp/enable':
            case 'product_export/round_5_cent':

            case 'order_import/active':
            case 'order_import/testmode':
            case 'order_import/send_email_confirmation':
            case 'order_import/send_order_response':
            case 'order_import/send_dispatch_notification':
            case 'order_import/send_cancellation_notification':
                $value = (bool)$p_value ? '1' : '0';
                break;
            case 'product_export/shipping':
                $value = $this->getShippingString($p_value);
                break;

            default:
                $value = $p_value;
        }
        $rc = parent::set($p_key, $value);

        return $rc;
    }

    protected function getDefaultCurrencyCode()
    {
        $result = xtc_db_query("SELECT code FROM currencies WHERE value = 1");
        if (xtc_db_num_rows($result) >= 1) {
            $row = xtc_db_fetch_array($result);

            return $row['code'];
        }

        return null;
    }

    protected function checkGalaxusStatusExisting()
    {
        foreach($this->defaultStatus as $status) {
            if (!$this->statusExists($status)) {
                $this->createStatus($status);
            }
        }
    }

    protected function statusExists($status)
    {
        $result = xtc_db_query("SELECT * FROM orders_status WHERE orders_status_name = '" . $status['de'] ."'");
        return xtc_db_num_rows($result) > 0;
    }

    protected function createStatus($status)
    {
        $new_orders_status_id = $this->getNewOrdersStatusId();

        foreach(xtc_get_languages() as $language) {
            if (isset($status[$language['code']])) {
                $data = [
                    'orders_status_id' => $new_orders_status_id,
                    'language_id' => $language['languages_id'],
                    'orders_status_name' => $status[$language['code']],
                    'color' =>  $status['color'],
                ];
                xtc_db_perform(TABLE_ORDERS_STATUS, $data);
            }
        }
    }

    protected function getNewOrdersStatusId()
    {
        $result = xtc_db_query("SELECT MAX(orders_status_id) + 1 as orders_status_id FROM orders_status");

        if (xtc_db_num_rows($result) > 0) {
            $row = xtc_db_fetch_array($result);
            return $row['orders_status_id'];
        }
        return 1;
    }

    protected function getDefaultOrderStatus($index)
    {
        $result = xtc_db_query("SELECT * FROM orders_status WHERE orders_status_name = '" . $this->defaultStatus[$index]['de'] . "'");
        if (xtc_db_num_rows($result) > 0) {
            $row = xtc_db_fetch_array($result);
            return $row['orders_status_id'];
        }

        return '';
    }
}