<?php

class SwixGalaxusAdminEditProductExtenderComponent extends SwixGalaxusAdminEditProductExtenderComponent_parent
{
    function proceed()
    {
        parent::proceed();

        $swix_galaxus_enabled = false;
        $swix_galaxus_price = 0;
        $swix_galaxus_price_override = null;
        $swix_galaxus_country_of_origin = null;

        if(isset($this->v_data_array['GET']['pID'])) {

            $productId = (int)$this->v_data_array['GET']['pID'];

            /** @var ProductReadService $productReadService */
            $productReadService = StaticGXCoreLoader::getService('ProductRead');
            $product = $productReadService->getProductById(new IdType($productId));

            $special_price = $this->loadSpecialPrice($productId);

            $swix_galaxus_price = $special_price !== false ? $special_price : $product->getPrice();

            $configuration = MainFactory::create('GalaxusConfigurationStorage');
            $galaxusPriceData = MainFactory::create('GalaxusExportPriceData', $configuration);
            $shippingCost = $galaxusPriceData->getShippingCost($swix_galaxus_price);

            $swix_galaxus_price = round($swix_galaxus_price + $shippingCost, 4);
            $tax_rate = xtc_get_tax_rate($product->getTaxClassId());

            if ($configuration->get('product_export/round_5_cent') == '1') {
                $swix_galaxus_price = $galaxusPriceData->round5Cent($swix_galaxus_price, $tax_rate);
            }

            $swix_galaxus_price = number_format(xtc_add_tax($swix_galaxus_price, $tax_rate), 2);

            try {
                $swix_galaxus_enabled = (bool)$product->getAddonValue(new StringType('swix_galaxus_enabled'));
            } catch (InvalidArgumentException $e) {

            }

            try {
                $swix_galaxus_price_override = $product->getAddonValue(new StringType('swix_galaxus_price_override'));
            } catch (InvalidArgumentException $e) {

            }

            try {
                $swix_galaxus_country_of_origin = $product->getAddonValue(new StringType('swix_galaxus_country_of_origin'));
            } catch (InvalidArgumentException $e) {

            }
        }

        $contentView = MainFactory::create('ContentView');
        $contentView->set_template_dir(DIR_FS_CATALOG . 'GXModules/Swix/Galaxus/Admin/Html/');
        $contentView->set_content_template('galaxus_product_configuration.html');
        $contentView->set_flat_assigns(true);
        $contentView->set_caching_enabled(false);
        $contentView->set_content_data('swix_galaxus_enabled', $swix_galaxus_enabled);
        $contentView->set_content_data('swix_galaxus_price', $swix_galaxus_price);
        $contentView->set_content_data('swix_galaxus_price_override', $swix_galaxus_price_override);
        $contentView->set_content_data('swix_galaxus_country_of_origin', $swix_galaxus_country_of_origin);

        $this->v_output_buffer['bottom']['swix_galaxus'] = [
            'title' => 'Galaxus Einstellungen',
            'content' => $contentView->get_html()
        ];
    }

    protected function loadSpecialPrice($id)
    {
        $result = xtc_db_query("SELECT specials_new_products_price FROM specials WHERE products_id = " . $id . " AND status = 1 AND CURRENT_DATE BETWEEN begins_date AND expires_date;");

        $specials = false;
        if (xtc_db_num_rows($result) > 0) {
            $row = xtc_db_fetch_array($result);
            $specials = $row['specials_new_products_price'];
        }

        return $specials;
    }
}