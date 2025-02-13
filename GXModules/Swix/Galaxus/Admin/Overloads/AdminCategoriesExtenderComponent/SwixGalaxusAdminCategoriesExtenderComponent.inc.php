<?php

class SwixGalaxusAdminCategoriesExtenderComponent extends SwixGalaxusAdminCategoriesExtenderComponent_parent
{
    public function proceed()
    {
        parent::proceed();

        if (isset($this->v_data_array['products_id'])
            && isset($this->v_data_array['GET']['action'])
            && ($this->v_data_array['GET']['action'] === 'update_product' || $this->v_data_array['GET']['action'] === 'insert_product')) {

            $productId = (int)$this->v_data_array['products_id'];
            $productReadService = StaticGXCoreLoader::getService('ProductRead');
            $product = $productReadService->getProductById(new IdType($productId));

            $addonValues = $product->getAddonValues()->getArray();
            $addonValues['swix_galaxus_enabled'] = (string)$this->v_data_array['POST']['swix_galaxus_enabled'];

            $priceOverride = $this->v_data_array['POST']['swix_galaxus_price_override'];

            if ($priceOverride <= 0) {
                $priceOverride = '';
            }

            $addonValues['swix_galaxus_price_override'] = (string)$priceOverride;

            $swix_galaxus_country_of_origin = (string)$this->v_data_array['POST']['swix_galaxus_country_of_origin'];

            if ($swix_galaxus_country_of_origin !== '') {
                $swix_galaxus_country_of_origin = strtoupper(trim($swix_galaxus_country_of_origin));
            }

            $addonValues['swix_galaxus_country_of_origin'] = $swix_galaxus_country_of_origin;

            $addonValueCollection = MainFactory::create('KeyValueCollection', $addonValues);
            $product->addAddonValues($addonValueCollection);

            $productWriteService = StaticGXCoreLoader::getService('ProductWrite');
            $productWriteService->updateProduct($product);
        }

        if (isset($this->v_data_array['categories_id'])
            && isset($this->v_data_array['GET']['action'])
            && ($this->v_data_array['GET']['action'] === 'update_category' || $this->v_data_array['GET']['action'] === 'insert_category')) {

            $categoryId = (int)$this->v_data_array['categories_id'];
            if ($this->v_data_array['POST']['swix_galaxus_enabled'] == 1 && $this->v_data_array['POST']['swix_galaxus_disabled'] == 1) {
                // DO NOTHING
            } else if ($this->v_data_array['POST']['swix_galaxus_enabled'] == 1) {
                $this->setSwixGalaxusEnabledRecursive($categoryId, true);
            } else if ($this->v_data_array['POST']['swix_galaxus_disabled'] == 1) {
                $this->setSwixGalaxusEnabledRecursive($categoryId, false);
            }
        }
    }

    // SWIX start
    protected function setSwixGalaxusEnabledRecursive($categoryId, $swixGalaxusEnabled)
    {
        $this->setSwixGalaxusEnabledProductsOfCategory($categoryId, $swixGalaxusEnabled);
        $result = xtc_db_query("SELECT categories_id FROM categories WHERE parent_id = " . $categoryId);

        while($row = xtc_db_fetch_array($result)) {
            $this->setSwixGalaxusEnabledRecursive($row['categories_id'], $swixGalaxusEnabled);
        }
    }

    protected function setSwixGalaxusEnabledProductsOfCategory($categroyId, $swixGalaxusEnabled)
    {
        $result = xtc_db_query("SELECT products_id FROM products_to_categories WHERE categories_id = $categroyId");

        $productReadService = StaticGXCoreLoader::getService('ProductRead');
        $productWriteService = StaticGXCoreLoader::getService('ProductWrite');

        while($row = xtc_db_fetch_array($result)) {
            $product = $productReadService->getProductById(new IdType($row['products_id']));

            $addonValues = $product->getAddonValues()->getArray();
            $addonValues['swix_galaxus_enabled'] = $swixGalaxusEnabled ? '1': '0';

            $addonValueCollection = MainFactory::create('KeyValueCollection', $addonValues);
            $product->addAddonValues($addonValueCollection);

            $productWriteService->updateProduct($product);
        }
    }
    // SWIX end
}