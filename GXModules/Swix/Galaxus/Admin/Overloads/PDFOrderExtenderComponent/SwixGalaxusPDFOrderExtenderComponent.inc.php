<?php

class SwixGalaxusPDFOrderExtenderComponent extends SwixGalaxusPDFOrderExtenderComponent_parent
{
    function extendGmOrderPdfValues($gm_order_pdf_values)
    {
        /** @var OrderReadService $orderReadService */
        $orderReadService = StaticGXCoreLoader::getService('OrderRead');
        $order = $orderReadService->getOrderById(new IdType($this->v_data_array['order_id']));

        if ($order->getAddonValues()->keyExists('galaxus_order')) {
            $galaxusOrderXml = new SimpleXMLElement($order->getAddonValue(new StringType('galaxus_order')));
            $galauxsOrderId = (int)$galaxusOrderXml->ORDER_HEADER->ORDER_INFO->ORDER_ID;
            $gm_order_pdf_values['GALAXUS_ORDER_ID'] = $galauxsOrderId;
        }

        return parent::extendGmOrderPdfValues($gm_order_pdf_values);
    }
}