<?php

require_once DIR_FS_CATALOG . '/vendor/autoload.php';
require_once(DIR_FS_INC . 'xtc_format_price_order.inc.php');
require_once(DIR_FS_CATALOG . 'gm/inc/set_shipping_status.php');
require_once(DIR_FS_INC . 'xtc_php_mail.inc.php');
if (!function_exists('xtc_address_format')) {
    require_once(DIR_FS_INC . 'xtc_address_format.inc.php');
}

use phpseclib\Net\SFTP;

class GalaxusImportOrders
{
    protected $config;
    protected $importPath;
    protected $archivePath;
    protected $orderResponseExportPath;

    protected $currentOrderFileName;
    protected $currentOrderFilePath;
    protected $currentOrderXml;

    protected $currentOrderResponseFileName;
    protected $currentOrderResponseFilePath;
    protected $orderResponseXML;

    protected $gambioOrderId;

    /** @var OrderReadService $orderReadService */
    protected $orderReadService;

    /** @var OrderWriteService $orderWriteService */
    protected $orderWriteService;

    /** @var OrderStatusHistoryStorage $orderStatusHistoryStorage */
    protected $orderStatusHistoryStorage;

    /** @var ProductStockService $productStockService */
    protected $productStockService;

    protected $coo_properties;

    public function __construct()
    {
        $this->config = MainFactory::create('GalaxusConfigurationStorage');

        $this->importPath = DIR_FS_CATALOG . 'import/galaxus/orders/';
        $this->archivePath = DIR_FS_CATALOG . 'import/galaxus/orders_archive/';
        $this->orderResponseExportPath = DIR_FS_CATALOG . 'export/galaxus/order_response/';

        $this->orderReadService = StaticGXCoreLoader::getService('OrderRead');
        $this->orderWriteService = StaticGXCoreLoader::getService('OrderWrite');
        $this->orderStatusHistoryStorage = MainFactory::create(OrderStatusHistoryStorage::class, StaticGXCoreLoader::getDatabaseQueryBuilder());

        $this->productStockService = MainFactory::create('ProductStockService');

        $this->coo_properties = MainFactory::create_object('PropertiesControl');
    }

    public function import()
    {
        $this->init();
        $this->downloadOrderFiles();
        $this->processOrderFiles();
    }

    protected function init()
    {
        if (!is_dir($this->importPath)) {
            mkdir($this->importPath, 0755, true);
        }

        if (!is_dir($this->archivePath)) {
            mkdir($this->archivePath, 0755, true);
        }

        if (!is_dir($this->orderResponseExportPath)) {
            mkdir($this->orderResponseExportPath, 0755, true);
        }
    }

    protected function downloadOrderFiles()
    {
        $sftp = new SFTP($this->config->get('order_import/ftp/server'), $this->config->get('order_import/ftp/port'));
        $ftp_folder = $this->config->get('order_import/testmode') == '1' ? $this->config->get('order_import/ftp/folder_test') : $this->config->get('order_import/ftp/folder_prod');
        $ftp_folder .= 'dg2partner/';

        if ($sftp->login($this->config->get('order_import/ftp/username'), $this->config->get('order_import/ftp/password'))) {

            if (!$sftp->chdir($ftp_folder)) {
                throw new Exception('FTP Ordner ' . $ftp_folder . ' nicht vorhanden!');
            }

            foreach($sftp->nlist($ftp_folder) as $filename) {
                if ($sftp->is_file($ftp_folder . $filename)) {
                    if($sftp->get($ftp_folder . $filename, $this->importPath . $filename) === true) {
                        $sftp->delete($ftp_folder . $filename);
                    }
                }
            }
        } else {
            $sftp->disconnect();
            throw new Exception('FTP Verbindung zu Galaxus funktioniert nicht!');
        }

        $sftp->disconnect();
    }

    protected function processOrderFiles()
    {
        $dirElements = scandir($this->importPath);

        foreach($dirElements as $dirElement) {
            if (is_file($this->importPath . $dirElement)) {
                $this->currentOrderFileName = $dirElement;
                $this->currentOrderFilePath = $this->importPath . $dirElement;
                $this->processOrderFile();
            }
        }
    }

    protected function processOrderFile()
    {
        $this->loadOrderDataFromFile();
        $this->writeOrder();
        $this->updateStock();
        $this->sendOrderEmailConfirmation();
        $this->sendOrderResponse();
        $this->archiveOrderFile();
        $this->deleteOrderFile();
    }

    protected function loadOrderDataFromFile()
    {
        $data = file_get_contents($this->currentOrderFilePath);
        $this->currentOrderXml = new SimpleXMLElement($data);
    }

    protected function getGalaxusOrderId()
    {
        return (int)$this->currentOrderXml->ORDER_HEADER->ORDER_INFO->ORDER_ID;
    }

    protected function writeOrder()
    {
        $buyerXML = $this->getPartyByRole('buyer');
        $deliveryXML = $this->getPartyByRole('delivery');

        $purchaseDate = $this->createPurchaseDate();

        $this->gambioOrderId = $this->orderWriteService->createNewStandaloneOrder(
            new StringType((string)$buyerXML->PARTY_ID),
            new EmailStringType((string)$buyerXML->ADDRESS->EMAIL),
            new StringType((string)$deliveryXML->ADDRESS->PHONE),
            new StringType(''),
            $this->createAddressBlock($buyerXML),
            $this->createAddressBlock($buyerXML),
            $this->createAddressBlock($deliveryXML),
            $this->createOrderItemCollection(),
            $this->createOrderTotalCollection(),
            new OrderShippingType(new StringType(''), new StringType('')),
            new OrderPaymentType(new StringType('Galaxus'), new StringType('Galaxus')),
            new CurrencyCode(new StringType($this->getCurrencyCode())),
            new LanguageCode(new StringType($this->getLanguageCode())),
            new DecimalType(0),
            new StringType((string)$this->getGalaxusOrderId()),
            new IntType(1),
            new KeyValueCollection($this->createAddonValues())
        );

        $order = $this->orderReadService->getOrderById(new IdType($this->gambioOrderId));
        $order->setPurchaseDateTime($purchaseDate);

        if ($this->config->get('order_import/order_status') != '0') {
            $order->setStatusId(new IdType((int) $this->config->get('order_import/order_status')));
            $this->orderStatusHistoryStorage->addStatusUpdate(new IdType($order->getOrderId()),
                new IdType((int) $this->config->get('order_import/order_status')),
                new StringType(''),
                new BoolType(true),
                new IdType(0));
        }

        $this->orderWriteService->updateOrder($order);
    }

    protected function createAddressBlock($addressXml)
    {
        $customerFirstname = '';
        $customerLastname = '';
        $customerCompany = '';

        if ($addressXml->ADDRESS->CONTACT_DETAILS->count() > 0) {
            $customerFirstname = (string)$addressXml->ADDRESS->CONTACT_DETAILS->FIRST_NAME;
            $customerLastname = (string)$addressXml->ADDRESS->CONTACT_DETAILS->CONTACT_NAME;

            if ((string)$addressXml->ADDRESS->NAME != (string)$addressXml->ADDRESS->CONTACT_DETAILS->FIRST_NAME . ' ' . (string)$addressXml->ADDRESS->CONTACT_DETAILS->CONTACT_NAME) {
                $customerCompany = (string)$addressXml->ADDRESS->NAME;
            }
        } else {
            $customerCompany = (string)$addressXml->ADDRESS->NAME;
        }

        $gender                = MainFactory::create('CustomerGender', '');
        $firstName             = MainFactory::create('CustomerFirstname', $customerFirstname);
        $lastName              = MainFactory::create('CustomerLastname', $customerLastname);
        $company               = MainFactory::create('CustomerCompany', $customerCompany);
        $B2BStatus             = MainFactory::create('CustomerB2BStatus', false);
        $street                = MainFactory::create('CustomerStreet', (string)$addressXml->ADDRESS->STREET);
        $houseNumber           = MainFactory::create('CustomerHouseNumber', '');
        $additionalAddressInfo = MainFactory::create('CustomerAdditionalAddressInfo', '');
        $suburb                = MainFactory::create('CustomerSuburb', '');
        $postCode              = MainFactory::create('CustomerPostcode', (string)$addressXml->ADDRESS->ZIP);
        $city                  = MainFactory::create('CustomerCity', (string)$addressXml->ADDRESS->CITY);

        /** @var CountryService $countryService */
        $countryService     = StaticGXCoreLoader::getService('Country');
        $country = $countryService->getCountryByIso2((string)$addressXml->ADDRESS->COUNTRY_CODED);
        $zone = $countryService->getUnknownCountryZoneByName('');

        return MainFactory::create('AddressBlock',
            $gender,
            $firstName,
            $lastName,
            $company,
            $B2BStatus,
            $street,
            $houseNumber,
            $additionalAddressInfo,
            $suburb,
            $postCode,
            $city,
            $country,
            $zone
        );
    }

    protected function createOrderItemCollection()
    {
        $itemsArray = [];

        /** @var \ProductReadService $productReadService */
        $productReadService = StaticGXCoreLoader::getService('ProductRead');

        foreach($this->currentOrderXml->ORDER_ITEM_LIST->ORDER_ITEM as $orderItemXml) {

            $single_price = (float)$orderItemXml->PRODUCT_PRICE_FIX->PRICE_AMOUNT + (float)$orderItemXml->PRODUCT_PRICE_FIX->TAX_DETAILS_FIX->TAX_AMOUNT;
            $tax_rate = round($single_price * 100 / (float)$orderItemXml->PRODUCT_PRICE_FIX->PRICE_AMOUNT - 100, 1);

            $supplierPID = (string)$orderItemXml->PRODUCT_ID->SUPPLIER_PID;
            $productsIdParts = explode('_', $supplierPID);
            $products_id = $productsIdParts[0];
            $products_combies_id = count($productsIdParts) > 1 ? $productsIdParts[1] : false;

            $product = $productReadService->getProductById(new IdType($products_id));

            /** @var OrderItem $orderItem */
            $orderItem = MainFactory::create('OrderItem', new StringType((string)$orderItemXml->PRODUCT_ID->DESCRIPTION_SHORT));
            $orderItem->setProductModel(new StringType($product->getProductModel()));
            $orderItem->setQuantity(new DecimalType((float)$orderItemXml->QUANTITY));
            $orderItem->setPrice(new DecimalType($single_price));
            $orderItem->setTax(new DecimalType($tax_rate));
            $orderItem->setTaxAllowed(new BoolType(true));
            $orderItem->setAddonValue(new StringType('productId'), new StringType((string)$products_id));

            if ($products_combies_id !== false) {
                $orderItemAttributesArray = [];
                $this->_addProperties($products_combies_id, $orderItemAttributesArray);

                if (count($orderItemAttributesArray)) {
                    $orderItemAttributeCollection = MainFactory::create('OrderItemAttributeCollection',
                        $orderItemAttributesArray);

                    $orderItem->setAttributes($orderItemAttributeCollection);
                }
            }

            $itemsArray[] = $orderItem;
        }

        return MainFactory::create('OrderItemCollection', $itemsArray);
    }

    protected function _addProperties($combisId, array &$orderItemAttributesArray)
    {
        if (!empty($combisId)) {
            $propertiesArray = $this->coo_properties->get_properties_combis_details($combisId,
                $_SESSION['languages_id']);

            /** @var OrderObjectService $orderObjectService */
            $orderObjectService = StaticGXCoreLoader::getService('OrderObject');

            foreach ($propertiesArray as $property) {
                /** @var OrderItemProperty $orderItemProperty */
                $orderItemProperty = $orderObjectService->createOrderItemPropertyObject(new StringType($property['properties_name']),
                    new StringType($property['values_name']));
                $orderItemProperty->setCombisId(new IdType($combisId));
                $orderItemProperty->setPrice(new DecimalType($property['value_price']));

                if (isset($property['value_price_type'])) {
                    $orderItemProperty->setPriceType(new StringType($property['value_price_type']));
                }

                $orderItemAttributesArray[] = $orderItemProperty;
            }
        }
    }

    protected function createOrderTotalCollection()
    {
        $totalsArray = [];

        $subtotal = 0;
        $taxes = [];
        $total = 0;
        $total_netto = 0;

        foreach($this->currentOrderXml->ORDER_ITEM_LIST->ORDER_ITEM as $orderItemXml) {
            $single_price_netto = (float)$orderItemXml->PRODUCT_PRICE_FIX->PRICE_AMOUNT;
            $single_price_brutto = (float)$orderItemXml->PRODUCT_PRICE_FIX->PRICE_AMOUNT + (float)$orderItemXml->PRODUCT_PRICE_FIX->TAX_DETAILS_FIX->TAX_AMOUNT;
            $tax_rate = round($single_price_brutto * 100 / (float)$orderItemXml->PRODUCT_PRICE_FIX->PRICE_AMOUNT - 100, 1);

            $subtotal += $single_price_brutto * (float)$orderItemXml->QUANTITY;
            $total += $single_price_brutto * (float)$orderItemXml->QUANTITY;
            $total_netto += $single_price_netto * (float)$orderItemXml->QUANTITY;
            $taxes[(string)$tax_rate] += (float)$orderItemXml->PRODUCT_PRICE_FIX->TAX_DETAILS_FIX->TAX_AMOUNT * (float)$orderItemXml->QUANTITY;
        }

        /** @var LanguageTextManager $textManager */
        $textManager = MainFactory::create_object('LanguageTextManager', ['order_details', $this->getLanguageId()]);

        $totalsArray[] = MainFactory::create('OrderTotal',
            new StringType($textManager->get_text('MODULE_ORDER_TOTAL_SUBTOTAL_TITLE', 'ot_subtotal') . ':'),
            new DecimalType($subtotal),
            new StringType(xtc_format_price_order($subtotal, 1, $this->getCurrencyCode())),
            new StringType('ot_subtotal'),
            new IntType((int)MODULE_ORDER_TOTAL_SUBTOTAL_SORT_ORDER));

        foreach($taxes as $tax_rate => $tax_value) {
            $totalsArray[] = MainFactory::create('OrderTotal',
                new StringType(sprintf($textManager->get_text('TAX_INFO_INCL', 'general'), $tax_rate . '%') . ':'),
                new DecimalType($tax_value),
                new StringType(xtc_format_price_order($tax_value, 1, $this->getCurrencyCode())),
                new StringType('ot_tax'),
                new IntType((int)MODULE_ORDER_TOTAL_TAX_SORT_ORDER));
        }

        $totalsArray[] = MainFactory::create('OrderTotal',
            new StringType($textManager->get_text('MODULE_ORDER_TOTAL_TOTAL_NETTO_TITLE', 'ot_total_netto') . ':'),
            new DecimalType($total_netto),
            new StringType(xtc_format_price_order($total_netto, 1, $this->getCurrencyCode())),
            new StringType('ot_total_netto'),
            new IntType((int)MODULE_ORDER_TOTAL_TOTAL_NETTO_SORT_ORDER));

        $totalsArray[] = MainFactory::create('OrderTotal',
            new StringType($textManager->get_text('MODULE_ORDER_TOTAL_TOTAL_TITLE', 'ot_total') . ':'),
            new DecimalType($total),
            new StringType(xtc_format_price_order($total, 1, $this->getCurrencyCode())),
            new StringType('ot_total'),
            new IntType((int)MODULE_ORDER_TOTAL_TOTAL_SORT_ORDER));

        return MainFactory::create('OrderTotalCollection', $totalsArray);
    }

    protected function createAddonValues()
    {
        $addonValues = [];
        $addonValues['galaxus_order'] = (string)$this->currentOrderXml->saveXML();
        $addonValues['galaxus_order_file'] = (string)$this->currentOrderFileName;
        $addonValues['galaxus_ordr_sent'] = '';
        $addonValues['galaxus_delr_sent'] = '';

        return $addonValues;
    }

    protected function getCurrencyCode()
    {
        return (string)$this->currentOrderXml->ORDER_HEADER->ORDER_INFO->CURRENCY;
    }

    protected function getLanguageCode()
    {
        switch((string)$this->currentOrderXml->ORDER_HEADER->ORDER_INFO->LANGUAGE) {

            case 'eng':
                $languageCode = 'en';
                break;
            case 'fra':
                $languageCode = 'fr';
                break;
            case 'ita':
                $languageCode = 'it';
                break;
            case 'ger':
            default:
                $languageCode = 'de';
                break;
        }

        $languageKnown = false;
        foreach($this->getLanguages() as $language) {
            if ($languageCode == $language['code']) {
                $languageKnown = true;
                break;
            }
        }

        if (!$languageKnown) {
            $languageCode = 'de';
        }

        return $languageCode;
    }

    protected function createPurchaseDate()
    {
        return new DateTime((string)$this->currentOrderXml->ORDER_HEADER->ORDER_INFO->ORDER_DATE);
    }

    protected function getLanguageId()
    {
        $languageCode = $this->getLanguageCode();
        $languages = $this->getLanguages();

        foreach($languages as $language) {
            if ($languageCode == $language['code']) {
                return $language['languages_id'];
            }
        }

        return $languages[0]['languages_id'];
    }

    protected function getPartyByRole($role)
    {
        foreach($this->currentOrderXml->ORDER_HEADER->ORDER_INFO->PARTIES->PARTY as $party) {

            if ($party->PARTY_ROLE == $role) {
                return $party;
            }
        }

        return false;
    }

    protected function deleteOrderFile()
    {
        unlink($this->currentOrderFilePath);
    }

    protected function sendOrderEmailConfirmation()
    {
        if ($this->config->get('order_import/send_email_confirmation') == '0') {
            return;
        }

        $this->createOrderConfirmation();
        $this->sendOrderConfirmation();
    }

    protected function createOrderConfirmation()
    {
        // Cronjob braucht Zugriff auf Admin Ordner
        if (!defined('_VALID_XTC')) {
            define('_VALID_XTC', true);
        }
        require_once(DIR_FS_CATALOG . 'admin/includes/classes/order.php');
        $coo_recreate_order = MainFactory::create_object('RecreateOrder', [$this->gambioOrderId]);
    }

    protected function sendOrderConfirmation()
    {
        $t_query = xtc_db_query("
										SELECT
											*
										FROM " .
            TABLE_ORDERS . "
										WHERE
											orders_id= '" . (int)$this->gambioOrderId . "'
										LIMIT 1
		");

        $t_row = xtc_db_fetch_array($t_query);

        $t_result = xtc_db_query('SELECT languages_id FROM languages WHERE directory = "' . xtc_db_input($t_row['language']) . '"');
        $t_language_row = xtc_db_fetch_array($t_result);

        $coo_shop_content_control = MainFactory::create_object('ShopContentContentControl');
        $coo_shop_content_control->set_language_id($t_language_row['languages_id']);
        $coo_shop_content_control->set_customer_status_id((int)$t_row['customers_status']);
        $t_mail_attachment_array = [];

        if (gm_get_conf('ATTACH_CONDITIONS_OF_USE_IN_ORDER_CONFIRMATION') == 1)
        {
            $coo_shop_content_control->set_content_group('3');
            $t_mail_attachment_array[] = $coo_shop_content_control->get_file();
        }

        if(gm_get_conf('ATTACH_PRIVACY_NOTICE_IN_ORDER_CONFIRMATION') == 1)
        {
            $coo_shop_content_control->set_content_group('2');
            $t_mail_attachment_array[] = $coo_shop_content_control->get_file();
        }

        if(gm_get_conf('ATTACH_WITHDRAWAL_INFO_IN_ORDER_CONFIRMATION') == '1')
        {
            $coo_shop_content_control->set_content_group(gm_get_conf('GM_WITHDRAWAL_CONTENT_ID'));
            $t_mail_attachment_array[] = $coo_shop_content_control->get_file();
        }

        if(gm_get_conf('ATTACH_WITHDRAWAL_FORM_IN_ORDER_CONFIRMATION') == '1')
        {
            $coo_shop_content_control->set_content_group(gm_get_conf('GM_WITHDRAWAL_CONTENT_ID'));
            $coo_shop_content_control->set_withdrawal_form('1');
            $t_mail_attachment_array[] = $coo_shop_content_control->get_file();
        }

        $purchasedDate = DateTime::createFromFormat('Y-m-d H:i:s', $t_row['date_purchased']);

        if (extension_loaded('intl')) {
            $order_date = utf8_encode_wrapper(DateFormatter::formatAsFullDate($purchasedDate, new LanguageCode(new StringType($this->getLanguageCode()))));
        } else {
            $order_date = utf8_encode_wrapper(strftime(DATE_FORMAT_LONG, $purchasedDate->getTimestamp()));
        }

        $languageManager = MainFactory::create('LanguageTextManager', 'swix_galaxus_import', $this->getLanguageCode());

        $subject = $languageManager->get_text('order_confirmation_subject');
        $subject = str_replace('{$nr}', $this->getGalaxusOrderId(), $subject);
        $subject = str_replace('{$date}', $order_date, $subject);
        $subject = str_replace('{$lastname}', $t_row['customers_lastname'], $subject);
        $subject = str_replace('{$firstname}', $t_row['customers_firstname'], $subject);

        if(xtc_php_mail(
            EMAIL_FROM,
            STORE_NAME,
            EMAIL_FROM,
            '',
            EMAIL_BILLING_FORWARDING_STRING,
            '',
            '',
            $t_mail_attachment_array,
            '',
            $subject,
            $t_row['gm_order_html'],
            $t_row['gm_order_txt']
        ))
        {
            xtc_db_query("
							UPDATE
								" . TABLE_ORDERS . "
							SET
								gm_send_order_status		= '1',
								gm_order_send_date			= NOW()
							WHERE
								orders_id = '" . (int)$this->gambioOrderId . "'
						");
        }
        else
        {
            throw new RuntimeException('The mail could not be sent, check the debug logs for more information.');
        }
    }

    protected function updateStock()
    {

        foreach ($this->currentOrderXml->ORDER_ITEM_LIST->ORDER_ITEM as $orderItemXml) {
            $supplier_pid = (string)$orderItemXml->PRODUCT_ID->SUPPLIER_PID;
            $supplier_pid_parts = explode('_', $supplier_pid);
            $t_product_id = $supplier_pid_parts[0];
            $quantity = (float)$orderItemXml->QUANTITY;

            // TODO: Momentan wird nur das Lager für das Produkt abgezogen
            $p_combis_id = '';
            if (count($supplier_pid_parts) == 2) {
                $p_combis_id = $supplier_pid_parts[1];
            }
            $p_combis_id = '';
            if ($products_combies_id !== false) {
                $p_combis_id = $products_combies_id;
            }

            if (STOCK_LIMITED == 'true') {
                if (empty($p_combis_id) == false) {
                    $t_quantity_change = $quantity * -1;
                    $this->coo_properties->change_combis_quantity($p_combis_id, $t_quantity_change);
                }
            
                $t_stock_result = $this->load_product_information($t_product_id);

                if (xtc_db_num_rows($t_stock_result) > 0) {
                    $t_use_combis_quantity_type = PropertiesCombisAdminControl::DEFAULT_GLOBAL;

                    if (!empty($p_combis_id)) {
                        $coo_combis_admin_control = MainFactory::create_object('PropertiesCombisAdminControl');
                        $t_use_combis_quantity_type = (int)$coo_combis_admin_control->get_use_properties_combis_quantity($t_product_id);
                    }

                    $t_stock_values_array = xtc_db_fetch_array($t_stock_result);

                    $t_product_filename = array_key_exists('products_attributes_filename', $t_stock_values_array)
                        ? $t_stock_values_array['products_attributes_filename']
                        : null;

                    if (PropertiesCombisAdminControl::DEFAULT_GLOBAL === $t_use_combis_quantity_type
                        || $this->productStockService->isChangeProductStock($t_use_combis_quantity_type,
                            $p_combis_id,
                            $t_product_filename)) {

                        $t_stock_left = $t_stock_values_array['products_quantity'] - $quantity;
                        $updateProductShippingStatus = true;
                        $t_products_sql_data_array['products_quantity'] = 'products_quantity - ' . $quantity;
                    } else {
                        $t_stock_left = $t_stock_values_array['products_quantity'];
                    }

                    $t_only_combi_check = !empty($p_combis_id)
                        && ($t_use_combis_quantity_type === PropertiesCombisAdminControl::COMBI_STOCK
                            || ($t_use_combis_quantity_type === PropertiesCombisAdminControl::DEFAULT_GLOBAL
                                && STOCK_CHECK == 'true'
                                && ATTRIBUTE_STOCK_CHECK == 'true')
                        );

                    //change product stock if its needed
                    if (($t_stock_left <= 0) && (STOCK_ALLOW_CHECKOUT == 'false') && GM_SET_OUT_OF_STOCK_PRODUCTS == 'true') {
                        if ($t_only_combi_check) {
                            $t_available_combi_exists = $this->coo_properties->available_combi_exists($t_product_id,
                                $p_combis_id);

                            if (!$t_available_combi_exists) {
                                $t_products_sql_data_array['products_status'] = '0';
                            }
                        } elseif (empty($p_combis_id) || $t_use_combis_quantity_type === PropertiesCombisAdminControl::PRODUCT_STOCK) {
                            $t_products_sql_data_array['products_status'] = '0';
                        }
                    }

                    $t_restock_level_reached = $t_stock_left <= STOCK_REORDER_LEVEL;
                }
            }

            // Update products_ordered (for bestsellers list)
            $t_products_sql_data_array['products_ordered'] = 'products_ordered + ' . (double)$quantity;

            $this->update_product($t_products_sql_data_array, xtc_get_prid($t_product_id));

            if ($updateProductShippingStatus) {
                // set products_shippingtime:
                set_shipping_status($t_product_id);
            }

            /** @var \ProductReadService $productReadService */
            $tproductReadService = StaticGXCoreLoader::getService('ProductRead');
            $product = $tproductReadService->getProductById(new IdType((int)xtc_get_prid($t_product_id)));
            /** @var StockLogger $stockLogger */
            $stockLogger = MainFactory::create('StockLogger');
            $stockLogger->addLogEntry(
                new IdType((int)xtc_get_prid($t_product_id)),
                new DecimalType($product->getQuantity()),
                new NonEmptyStringType('Bestellabschluss'),
                new StringType((string)$t_product_id)
            );
        }
    }

    protected function archiveOrderFile()
    {
        copy($this->currentOrderFilePath, $this->archivePath . $this->currentOrderFileName);
    }

    protected function load_product_information($p_product_id, $p_product_attributes_array = null)
    {
        $c_product_id = (int)$p_product_id;
        if (DOWNLOAD_ENABLED == 'true') {
            // Will work with only one option for downloadable products
            // otherwise, we have to build the query dynamically with a loop
            if (is_array($p_product_attributes_array) && count($p_product_attributes_array) > 0) {
                $t_stock_query = '
										SELECT
											p.products_quantity,
											pad.products_attributes_filename
										FROM
										    ' . TABLE_PRODUCTS . ' p
										LEFT JOIN ' . TABLE_PRODUCTS_ATTRIBUTES . " pa
											ON  p.products_id=pa.products_id
											AND pa.options_id = '" . (int)$p_product_attributes_array[0]['option_id'] . "'
					                        AND pa.options_values_id = '" . (int)$p_product_attributes_array[0]['value_id'] . "'
										LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
										    ON pa.products_attributes_id=pad.products_attributes_id
										WHERE p.products_id = '" . $c_product_id . "'";
            } else {
                $t_stock_query = '
											SELECT
												p.products_quantity,
												pad.products_attributes_filename
											FROM
												' . TABLE_PRODUCTS . ' p
											LEFT JOIN ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa
												ON p.products_id=pa.products_id
											LEFT JOIN ' . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
												ON pa.products_attributes_id=pad.products_attributes_id
											WHERE p.products_id = '" . $c_product_id . "'";
            }
            $t_stock_result = xtc_db_query($t_stock_query, 'db_link', false);
        } else {
            $t_stock_result = xtc_db_query('SELECT products_quantity
												FROM ' . TABLE_PRODUCTS . "
												WHERE products_id = '" . $c_product_id . "'"
                , 'db_link'
                , false);
        }

        return $t_stock_result;
    }

    public function update_product($p_products_sql_data_array, $p_products_id)
    {
        xtc_db_perform(TABLE_PRODUCTS, $p_products_sql_data_array, 'update', 'products_id = "' . (int)$p_products_id . '"', 'db_link', false);
    }

    protected function sendOrderResponse()
    {
        if ($this->config->get('order_import/send_order_response') == '0') {
            return;
        }

        $this->createOrderResponseFileName();
        $this->createOrderResponseFilePath();
        $this->createOrderResponseXml();
        $this->saveOrderResponseXml();
        $this->uploadOrderResponseFile();
        $this->writeORDRSentStatus();
    }

    protected function createOrderResponseFileName()
    {
        $now = new DateTime();

        $filename = str_replace('GORDP_', 'GORDR_', $this->currentOrderFileName);
        $filename = str_replace('.xml', '_' . $this->gambioOrderId . '_' . $now->format('YmdHi') . '.xml', $filename);

        $this->currentOrderResponseFileName = $filename;
    }

    protected function createOrderResponseFilePath()
    {
        $this->currentOrderResponseFilePath = $this->orderResponseExportPath . $this->currentOrderResponseFileName;
    }

    protected function createOrderResponseXml()
    {
        $this->orderResponseXML = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ORDERRESPONSE xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.opentrans.org/XMLSchema/2.1" version="2.1"><ORDERRESPONSE_HEADER><ORDERRESPONSE_INFO><ORDER_ID></ORDER_ID><ORDERRESPONSE_DATE></ORDERRESPONSE_DATE><SUPPLIER_ORDER_ID></SUPPLIER_ORDER_ID></ORDERRESPONSE_INFO></ORDERRESPONSE_HEADER></ORDERRESPONSE>');

        $responseDate = new DateTime();

        $this->orderResponseXML->ORDERRESPONSE_HEADER->ORDERRESPONSE_INFO->ORDER_ID = (string)$this->currentOrderXml->ORDER_HEADER->ORDER_INFO->ORDER_ID;
        $this->orderResponseXML->ORDERRESPONSE_HEADER->ORDERRESPONSE_INFO->ORDERRESPONSE_DATE = (string)$responseDate->format('Y-m-d\TH:i:s');
        $this->orderResponseXML->ORDERRESPONSE_HEADER->ORDERRESPONSE_INFO->SUPPLIER_ORDER_ID = (string)$this->gambioOrderId;
    }

    protected function saveOrderResponseXml()
    {
        $this->orderResponseXML->saveXML($this->currentOrderResponseFilePath);
    }

    protected function uploadOrderResponseFile()
    {
        $sftp = new SFTP($this->config->get('order_import/ftp/server'), $this->config->get('order_import/ftp/port'));
        $ftp_folder = $this->config->get('order_import/testmode') == '1' ? $this->config->get('order_import/ftp/folder_test') : $this->config->get('order_import/ftp/folder_prod');

        $ftp_folder .= 'partner2dg/';

        if ($sftp->login($this->config->get('order_import/ftp/username'), $this->config->get('order_import/ftp/password'))) {

            if (!$sftp->chdir($ftp_folder)) {
                throw new Exception('FTP Ordner ' . $ftp_folder . ' nicht vorhanden!');
            }

            $sftp->put($this->currentOrderResponseFileName, file_get_contents($this->currentOrderResponseFilePath));

        } else {
            $sftp->disconnect();
            throw new Exception('FTP Verbindung zu Galaxus funktioniert nicht!');
        }

        $sftp->disconnect();
    }

    protected function writeORDRSentStatus()
    {
        $order = $this->orderReadService->getOrderById(new IdType($this->gambioOrderId));
        if ($order->getAddonValues()->keyExists('galaxus_ordr_sent') && (string)$order->getAddonValue(new StringType('galaxus_ordr_sent')) !== '') {
            return;
        }

        $date = new DateTime();
        $order->setAddonValue(new StringType('galaxus_ordr_sent'), new StringType($date->format('Y-m-d H:i:s')));
        $this->orderWriteService->updateOrder($order);
    }

    protected function getLanguages()
    {
        $languages = [];

        $result = xtc_db_query("SELECT * FROM languages");

        while($row = xtc_db_fetch_array($result)) {
            $languages[] = $row;
        }

        return $languages;
    }
}