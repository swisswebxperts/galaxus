<?php

require_once DIR_FS_CATALOG . '/vendor/autoload.php';

use phpseclib\Net\SFTP;

class GalaxusExportDelivery
{
    protected $config;

    protected $deliveryNotificationExportPath;
    protected $currentDeliveryNotificationFileName;
    protected $currentDeliveryNotificationFilePath;

    protected $notificationXml;

    /** @var GXEngineOrder $order */
    protected $order;
    protected $galaxusOrderXML;
    protected $galaxusOrderFileName;
    protected $allowedShipmentTypes = ['swisspost'];

    public function __construct(GalaxusConfigurationStorage $config)
    {
        $this->config = $config;

        $this->deliveryNotificationExportPath = DIR_FS_CATALOG . 'export/galaxus/delivery_notification/';
    }

    public function exportDelivery($order)
    {
        $this->order = $order;

        $this->init();
        $this->loadGalaxusOrderXML();
        $this->loadGalaxusOrderFileName();
        $this->createDeliveryNotification();
        $this->createDeliveryNotificationFilePath();
        $this->saveDeliveryNotification();
        $this->uploadDeliveryNotificationFile();
        $this->writeDELRSentStatus();
    }

    public function init()
    {
        if (!is_dir($this->deliveryNotificationExportPath)) {
            mkdir($this->deliveryNotificationExportPath, 0755, true);
        }
    }

    protected function loadGalaxusOrderXML()
    {
        $this->galaxusOrderXML = new SimpleXMLElement($this->order->getAddonValue(new StringType('galaxus_order')));
    }

    protected function loadGalaxusOrderFileName()
    {
        $this->galaxusOrderFileName = $this->order->getAddonValue(new StringType('galaxus_order_file'));
    }

    protected function createDeliveryNotification()
    {
        $this->createDispatchNotification();
        $this->createDispatchNotificationHeader();
        $this->createDispatchNotificationItemList();
    }

    protected function createDispatchNotification()
    {
        $this->notificationXml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><DISPATCHNOTIFICATION xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.opentrans.org/XMLSchema/2.1" version="2.1"></DISPATCHNOTIFICATION>');
    }

    protected function createDispatchNotificationHeader()
    {
        $now = new DateTime();
        $this->notificationXml->DISPATCHNOTIFICATION_HEADER = '';

        $header = $this->notificationXml->DISPATCHNOTIFICATION_HEADER;
        $header->CONTROL_INFO->GENERATION_DATE = $now->format('Y-m-d\TH:i:s');

        $header->DISPATCHNOTIFICATION_INFO = '';
        $info = $header->DISPATCHNOTIFICATION_INFO;

        $info->DISPATCHNOTIFICATION_ID = $this->order->getOrderId();
        $info->DISPATCHNOTIFICATION_DATE = $now->format('Y-m-d\TH:i:s');

        $info->PARTIES->PARTY->PARTY_ROLE = 'delivery';

        if ((string)$this->order->getDeliveryAddress()->getCompany() == '') {
            $info->PARTIES->PARTY->ADDRESS->NAME = $this->order->getDeliveryAddress()->getFirstname() . ' ' . $this->order->getDeliveryAddress()->getLastname();
            $info->PARTIES->PARTY->ADDRESS->NAME->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');
        } else {
            $info->PARTIES->PARTY->ADDRESS->NAME = (string)$this->order->getDeliveryAddress()->getCompany();
            $info->PARTIES->PARTY->ADDRESS->NAME->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');
            $info->PARTIES->PARTY->ADDRESS->NAME2 = $this->order->getDeliveryAddress()->getFirstname() . ' ' . $this->order->getDeliveryAddress()->getLastname();
            $info->PARTIES->PARTY->ADDRESS->NAME2->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');
        }

        $info->PARTIES->PARTY->ADDRESS->CONTACT_DETAILS->FIRST_NAME = (string)$this->order->getDeliveryAddress()->getFirstname();
        $info->PARTIES->PARTY->ADDRESS->CONTACT_DETAILS->FIRST_NAME->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');
        $info->PARTIES->PARTY->ADDRESS->CONTACT_DETAILS->CONTACT_NAME = (string)$this->order->getDeliveryAddress()->getLastname();
        $info->PARTIES->PARTY->ADDRESS->CONTACT_DETAILS->CONTACT_NAME->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

        $street = (string)$this->order->getDeliveryAddress()->getStreet();
        if ((string)$this->order->getDeliveryAddress()->getHouseNumber() != '') {
            $street .= ' ' . $this->order->getDeliveryAddress()->getHouseNumber();
        }

        $info->PARTIES->PARTY->ADDRESS->STREET = $street;
        $info->PARTIES->PARTY->ADDRESS->STREET->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

        $info->PARTIES->PARTY->ADDRESS->ZIP = (string)$this->order->getDeliveryAddress()->getPostcode();
        $info->PARTIES->PARTY->ADDRESS->ZIP->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

        $info->PARTIES->PARTY->ADDRESS->CITY = (string)$this->order->getDeliveryAddress()->getCity();
        $info->PARTIES->PARTY->ADDRESS->CITY->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

        $info->PARTIES->PARTY->ADDRESS->COUNTRY = (string)$this->order->getDeliveryAddress()->getCountry()->getName();
        $info->PARTIES->PARTY->ADDRESS->COUNTRY->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

        $info->PARTIES->PARTY->ADDRESS->COUNTRY_CODED = strtoupper((string)$this->order->getDeliveryAddress()->getCountry()->getIso2());
        $info->PARTIES->PARTY->ADDRESS->COUNTRY_CODED->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

        /** @var ParcelTrackingCode $parcelTrackingCodeItem */
        $trackingCodesData = $this->getTrackingCodesData($this->order->getOrderId());

        if (count($trackingCodesData) > 0) {
            $trackingCode = $trackingCodesData[0];

            $shipment_carrier = $trackingCode['shipment_type'];
            if (!in_array($shipment_carrier, $this->allowedShipmentTypes)) {
                throw new \Exception('Shipment Carrier invalid');
            }

            /** @var ParcelTrackingCode $trackingCode */
            $info->SHIPMENT_ID = $trackingCode['tracking_code'];
            $info->SHIPMENT_CARRIER = $shipment_carrier;
        } else {
            $info->SHIPMENT_ID = $this->getNoTrackingCodeText();
            $info->SHIPMENT_CARRIER = 'swisspost';
        }
    }

    protected function createDispatchNotificationItemList()
    {
        $this->notificationXml->DISPATCHNOTIFICATION_ITEM_LIST = '';

        foreach($this->galaxusOrderXML->ORDER_ITEM_LIST->ORDER_ITEM as $orderItem) {

            $dispatchNotificationItem = $this->notificationXml->DISPATCHNOTIFICATION_ITEM_LIST->addChild('DISPATCHNOTIFICATION_ITEM');

            $dispatchNotificationItem->PRODUCT_ID->SUPPLIER_PID = (string)$orderItem->PRODUCT_ID->SUPPLIER_PID;
            $dispatchNotificationItem->PRODUCT_ID->SUPPLIER_PID->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

            $dispatchNotificationItem->PRODUCT_ID->INTERNATIONAL_PID = (string)$orderItem->PRODUCT_ID->INTERNATIONAL_PID;
            $dispatchNotificationItem->PRODUCT_ID->INTERNATIONAL_PID->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

            $dispatchNotificationItem->PRODUCT_ID->BUYER_PID = (string)$orderItem->PRODUCT_ID->BUYER_PID;
            $dispatchNotificationItem->PRODUCT_ID->BUYER_PID->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

            $dispatchNotificationItem->QUANTITY = (string)$orderItem->QUANTITY;

            $dispatchNotificationItem->ORDER_REFERENCE->ORDER_ID = (string)$this->galaxusOrderXML->ORDER_HEADER->ORDER_INFO->ORDER_ID;
        }
    }

    protected function createDeliveryNotificationFilePath()
    {
        $now = new DateTime();

        $this->currentDeliveryNotificationFileName = str_replace('GORDP_', 'GDELR_', $this->galaxusOrderFileName);
        $this->currentDeliveryNotificationFileName = str_replace('.xml', '_' . $this->order->getOrderId() . '_' . $now->format('YmdHi') . '.xml', $this->currentDeliveryNotificationFileName);

        $this->currentDeliveryNotificationFilePath = $this->deliveryNotificationExportPath . $this->currentDeliveryNotificationFileName;
    }

    protected function saveDeliveryNotification()
    {
        $this->notificationXml->saveXML($this->currentDeliveryNotificationFilePath);
    }

    protected function addCData($name, $value, &$parent) {
        $child = $parent->addChild($name);

        if ($child !== NULL) {
            $child_node = dom_import_simplexml($child);
            $child_owner = $child_node->ownerDocument;
            $child_node->appendChild($child_owner->createCDATASection($value));
        }

        return $child;
    }

    protected function xml_add($root, $new) {
        $node = $root->addChild($new->getName(), (string) $new);
        foreach($new->attributes() as $attr => $value) {
            $node->addAttribute($attr, $value);
        }
        foreach($new->children() as $ch) {
            $this->xml_add($node, $ch);
        }
    }

    protected function uploadDeliveryNotificationFile()
    {
        $sftp = new SFTP($this->config->get('order_import/ftp/server'), $this->config->get('order_import/ftp/port'));
        $ftp_folder = $this->config->get('order_import/testmode') == '1' ? $this->config->get('order_import/ftp/folder_test') : $this->config->get('order_import/ftp/folder_prod');

        $ftp_folder .= 'partner2dg/';

        if ($sftp->login($this->config->get('order_import/ftp/username'), $this->config->get('order_import/ftp/password'))) {

            if (!$sftp->chdir($ftp_folder)) {
                throw new Exception('FTP Ordner ' . $ftp_folder . ' nicht vorhanden!');
            }

            $sftp->put($this->currentDeliveryNotificationFileName, file_get_contents($this->currentDeliveryNotificationFilePath));

        } else {
            $sftp->disconnect();
            throw new Exception('FTP Verbindung zu Galaxus funktioniert nicht!');
        }

        $sftp->disconnect();
    }

    protected function writeDELRSentStatus()
    {
        if ($this->order->getAddonValues()->keyExists('galaxus_delr_sent') && $this->order->getAddonValue(new StringType('galaxus_delr_sent')) !== '') {
            return;
        }

        $date = new DateTime();

        $orderWriteService = StaticGXCoreLoader::getService('OrderWrite');
        $this->order->setAddonValue(new StringType('galaxus_delr_sent'), new StringType($date->format('Y-m-d H:i:s')));
        $orderWriteService->updateOrder($this->order);
    }

    protected function getNoTrackingCodeText()
    {
        return 'per Briefpost';
    }

    protected function getTrackingCodesData($orderId)
    {
        $result = xtc_db_query("SELECT * FROM orders_parcel_tracking_codes WHERE order_id = " . (int)$orderId . " ORDER BY creation_date DESC");

        $trackingCodesData = [];

        while($row = xtc_db_fetch_array($result)) {
            $trackingCodesData[] = $row;
        }

        return $trackingCodesData;
    }
}