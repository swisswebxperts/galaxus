<?php

class SwixGalaxusOrderExtenderComponent extends SwixGalaxusOrderExtenderComponent_parent
{
    public function proceed()
    {
        parent::proceed();

        $this->addGalaxusDetails();
    }

    protected function addGalaxusDetails()
    {
        $languageTextManager = MainFactory::create_object('LanguageTextManager', ['swix_galaxus_order_detail']);

        /** @var OrderReadService $orderRead */
        $orderRead = StaticGXCoreLoader::getService('OrderRead');
        $order = $orderRead->getOrderById(new IdType($_GET['oID']));

        /** @var ContentView $layoutContentView */
        $layoutContentView = MainFactory::create_object('ContentView');
        $layoutContentView->set_caching_enabled(false);
        $layoutContentView->set_flat_assigns(true);
        $layoutContentView->set_template_dir(DIR_FS_CATALOG . 'GXModules/Swix/Galaxus/Admin/Html/');
        $layoutContentView->set_content_template('galaxus_order_detail.html');

        if ($order->getAddonValues()->keyExists('galaxus_order')) {
            $galaxus_order = new SimpleXMLElement($order->getAddonValue(new StringType('galaxus_order')));
            $layoutContentView->set_content_data('delivery_note_requested',
                $galaxus_order->ORDER_HEADER->ORDER_INFO->HEADER_UDX->{'UDX.DG.PHYSICAL_DELIVERY_NOTE_REQUIRED'} == 'true');
        }

        $layoutContentView->set_content_data('comment', $order->getComment());

        $this->addContentToCollection('below_product_data', $layoutContentView->get_html(), $languageTextManager->get_text('title'));

    }
}