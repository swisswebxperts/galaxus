<?php
/* TODO: MID-DEVELOPMENT DO NOT USE THIS CLASS */
class GalaxusExportCancelling
{
    /** @var GalaxusConfigurationStorage $config */
    protected $config;
    protected $order;
    protected $galaxusOrderXML;
    protected $galaxusOrderFileName;
    protected $notificationXml;

    /** @var OrderReadService $orderReadService */
    protected $orderReadService;
    protected $cancellingNotificationExportPath;
    protected $currentCancellingNotificationFileName;

    public function __construct()
    {
        $this->config = MainFactory::create('GalaxusConfigurationStorage');
        $this->orderReadService = StaticGXCoreLoader::getService('OrderRead');

        $this->cancellingNotificationExportPath = DIR_FS_CATALOG . 'export/galaxus/cancelling_notification/';
    }

    public function exportCancelling(int $orders_id)
    {
        if ($this->config->get('order_import/send_cancellation_notification') != 1) {
            return;
        }

        $this->order = $this->orderReadService->getOrderById(new IdType($orders_id));

        $this->init();
        $this->loadGalaxusOrderXML();
        $this->loadGalaxusOrderFileName();
        $this->createCancellingNotification();
        $this->createCancellingNotificationFilePath();

        exit;
    }

    public function init()
    {
        if (!is_dir($this->cancellingNotificationExportPath)) {
            mkdir($this->cancellingNotificationExportPath, 0755, true);
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

    protected function createCancellingNotification()
    {
        $this->createSupplierCancelNotification();
        $this->createSupplierCancelNotificationHeader();
        $this->createSupplierCancelNotificationItemList();
    }

    protected function createSupplierCancelNotification()
    {
        $this->notificationXml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><SUPPLIERCANCELNOTIFICATION xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.opentrans.org/XMLSchema/2.1" version="2.1"></SUPPLIERCANCELNOTIFICATION>');
    }

    protected function createSupplierCancelNotificationHeader()
    {
        $now = new DateTime();

        $this->notificationXml->DISPATCHNOTIFICATION_HEADER = '';
        $header = $this->notificationXml->DISPATCHNOTIFICATION_HEADER;

        $header->DISPATCHNOTIFICATION_HEADER->ORDER_ID = (string)$this->galaxusOrderXML->ORDER_HEADER->ORDER_INFO->ORDER_ID;
        $header->DISPATCHNOTIFICATION_HEADER->SUPPLIERCANCELNOTIFICATION_DATE = $now->format('Y-m-d\TH:i:s');
    }

    protected function createSupplierCancelNotificationItemList()
    {
        $this->notificationXml->SUPPLIERCANCELNOTIFICATION_ITEM_LIST = '';

        foreach($this->galaxusOrderXML->ORDER_ITEM_LIST as $orderItem) {

            $notificationItem = $this->notificationXml->SUPPLIERCANCELNOTIFICATION_ITEM_LIST->addChild('SUPPLIERCANCELNOTIFICATION_ITEM');

            $notificationItem->PRODUCT_ID->SUPPLIER_PID = $orderItem->ORDER_ITEM->PRODUCT_ID->SUPPLIER_PID;
            $notificationItem->PRODUCT_ID->SUPPLIER_PID->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

            $notificationItem->PRODUCT_ID->INTERNATIONAL_PID = $orderItem->ORDER_ITEM->PRODUCT_ID->INTERNATIONAL_PID;
            $notificationItem->PRODUCT_ID->INTERNATIONAL_PID->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

            $notificationItem->PRODUCT_ID->BUYER_PID = $orderItem->ORDER_ITEM->PRODUCT_ID->BUYER_PID;
            $notificationItem->PRODUCT_ID->BUYER_PID->addAttribute('xmlns', 'http://www.bmecat.org/bmecat/2005');

            $notificationItem->QUANTITY = $orderItem->ORDER_ITEM->QUANTITY;
        }
    }

    protected function createCancellingNotificationFilePath()
    {
        $now = new DateTime();

        $this->currentCancellingNotificationFileName = str_replace('GORDP_', 'GDELR_', $this->galaxusOrderFileName);
        $this->currentCancellingNotificationFileName = str_replace('.xml', '_' . $this->order->getOrderId() . '_' . $now->format('YmdHi') . '.xml', $this->currentCancellingNotificationFileName);

        $this->currentCancellingNotificationFileName = $this->cancellingNotificationExportPath . $this->currentCancellingNotificationFileName;

        print_r($this->currentCancellingNotificationFileName); exit;
    }

}