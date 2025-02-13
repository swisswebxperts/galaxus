<?php

class SwixGalaxusAdminOrderActionExtenderComponent extends SwixGalaxusAdminOrderActionExtenderComponent_parent
{
    public function set_data($p_key, $p_value)
    {
        if ($p_key == 'order_updated' && isset($this->v_data_array['POST']['action']) && $this->v_data_array['POST']['action'] == 'gm_multi_status'
            && isset($this->v_data_array['POST']['gm_multi_status'][0])) {

            $orderId = $this->v_data_array['POST']['gm_multi_status'][0];
            $statusId = $this->v_data_array['POST']['gm_status'];

            /** @var OrderReadService $orderReadService */
            $orderReadService = StaticGXCoreLoader::getService('OrderRead');
            $order = $orderReadService->getOrderById(new IdType($orderId));

            if ($order->getAddonValues()->keyExists('galaxus_order')) {
                $galaxusConfig = MainFactory::create('GalaxusConfigurationStorage');
                if ($galaxusConfig->get('order_import/send_dispatch_notification') == '1' && $statusId == $galaxusConfig->get('order_import/dispatch_notification_order_status')) {
                    $galaxusExportDelivery = MainFactory::create('GalaxusExportDelivery', $galaxusConfig);
                    $galaxusExportDelivery->exportDelivery($order);
                }
            }
        }

        return parent::set_data($p_key, $p_value);
    }
}