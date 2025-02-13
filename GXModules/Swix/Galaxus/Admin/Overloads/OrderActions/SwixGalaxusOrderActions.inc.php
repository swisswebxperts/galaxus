<?php

class SwixGalaxusOrderActions extends SwixGalaxusOrderActions_parent
{
    public function changeOrderStatus(
        IdType $orderId,
        IdType $statusId,
        StringType $comment,
        BoolType $notifyCustomer,
        BoolType $sendParcelTrackingCode,
        BoolType $sendComment,
        IdType $customerId = null
    ) {
        parent::changeOrderStatus($orderId, $statusId, $comment, $notifyCustomer, $sendParcelTrackingCode, $sendComment, $customerId);

        /** @var OrderReadService $orderReadService */
        $orderReadService = StaticGXCoreLoader::getService('OrderRead');
        $order = $orderReadService->getOrderById($orderId);

        if ($order->getAddonValues()->keyExists('galaxus_order')) {

            $galaxusConfig = MainFactory::create('GalaxusConfigurationStorage');

            if ($galaxusConfig->get('order_import/send_dispatch_notification') == '1' && $statusId == $galaxusConfig->get('order_import/dispatch_notification_order_status')) {
                $galaxusExportDelivery = MainFactory::create('GalaxusExportDelivery', $galaxusConfig);
                $galaxusExportDelivery->exportDelivery($order);
            }
        }
    }
}