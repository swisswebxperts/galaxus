<?php

class GalaxusOrderImportAjaxHandler extends AjaxHandler
{
    public function get_permission_status($customerId = null)
    {
        return true;
    }

    public function proceed()
    {
        /** @var GalaxusImportOrders $galaxusImportOrders */
        $galaxusImportOrders = MainFactory::create('GalaxusImportOrders');
        $galaxusImportOrders->import();

        $this->sendAllUnsentDeliveryNotifications();

        return true;
    }

    protected function sendAllUnsentDeliveryNotifications()
    {
        $galaxusConfig = MainFactory::create('GalaxusConfigurationStorage');

        if ($galaxusConfig->get('order_import/send_dispatch_notification') !== '1') {
            return;
        }

        $orderStatus = $galaxusConfig->get('order_import/dispatch_notification_order_status');

        $result = xtc_db_query("SELECT
                      o.orders_id
                    FROM orders o
                    WHERE orders_id IN (SELECT
                        avs.container_id
                      FROM addon_values_storage avs
                      WHERE avs.container_type = 'OrderInterface'
                      AND avs.addon_key = 'galaxus_delr_sent'
                      AND avs.addon_value = '')
                    AND orders_status = " . $orderStatus);

        $unsentOrderIds = [];
        while($row = xtc_db_fetch_array($result)) {
            $unsentOrderIds[] = (int)$row['orders_id'];
        }

        if (count($unsentOrderIds) > 0) {
            /** @var OrderReadService $orderReadService */
            $orderReadService = StaticGXCoreLoader::getService('OrderRead');
            $galaxusExportDelivery = MainFactory::create('GalaxusExportDelivery', $galaxusConfig);

            foreach($unsentOrderIds as $orderId) {
                $order = $orderReadService->getOrderById(new IdType($orderId));
                $galaxusExportDelivery->exportDelivery($order);
            }


        }
    }
}