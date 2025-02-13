<?php

class SwixGalaxusSendOrderContentView extends SwixGalaxusSendOrderContentView_parent
{
    public function get_mail_content_array()
    {
        /** @var OrderReadService $orderReadService */
        $orderReadService = StaticGXCoreLoader::getService('OrderRead');
        $order = $orderReadService->getOrderById(new IdType($this->order_id));

        if ($order->getAddonValues()->keyExists('galaxus_order')) {
            $orderXml = new SimpleXMLElement($order->getAddonValue(new StringType('galaxus_order')));

            $this->set_content_data('galaxus_order_id', (string)$orderXml->ORDER_HEADER->ORDER_INFO->ORDER_ID);
            $this->set_content_data('galaxus_delivery_note_required', (string)$orderXml->ORDER_HEADER->ORDER_INFO->HEADER_UDX->{'UDX.DG.PHYSICAL_DELIVERY_NOTE_REQUIRED'} === 'true');
        }

        return parent::get_mail_content_array();
    }
}