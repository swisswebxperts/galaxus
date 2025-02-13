<?php

require_once DIR_FS_CATALOG . '/vendor/autoload.php';

use phpseclib\Net\SFTP;

class GalaxusExportManager
{
    protected $productIds;
    protected $exportHandlers;
    protected $exportPath;
    protected $logPath;
    protected $categories;
    protected $config;

    public function __construct()
    {
        $this->config = MainFactory::create('GalaxusConfigurationStorage');

        $this->exportHandlers = [];
        $this->exportHandlers[] = MainFactory::create('GalaxusExportProductData', $this->config);
        $this->exportHandlers[] = MainFactory::create('GalaxusExportMediaData', $this->config);
        $this->exportHandlers[] = MainFactory::create('GalaxusExportPriceData', $this->config);
        $this->exportHandlers[] = MainFactory::create('GalaxusExportStockData', $this->config);
        $this->exportHandlers[] = MainFactory::create('GalaxusExportSpecificationData', $this->config);

        $this->exportPath = $this->config->get('product_export/export_path');
        $this->logPath = $this->config->get('product_export/logfile');

        $this->loadCategories();
    }

    protected function loadCategories()
    {
        $result = xtc_db_query("SELECT c.categories_id, cd.categories_name FROM categories c 
                                  LEFT JOIN categories_description cd ON c.categories_id = cd.categories_id
                                  WHERE c.categories_status = 1
                                  AND cd.language_id = 2
                                  ORDER BY c.parent_id");
        $this->categories = [];

        while($row = xtc_db_fetch_array($result)) {
            $this->categories[$row['categories_id']] = $row['categories_name'];
        }
		
		xtc_db_free_result($result);
    }

    public function export()
    {
        if ($this->config->get('product_export/active') == '0') {
            throw new Exception('Der Artikelexport zu Galaxus ist nicht eingeschaltet.');
        }

		$this->exportBackup();

        $this->initLogFile();
        $this->loadProducts();
        $this->initHandlers();

        foreach ($this->productIds as $productId) {
            $product = $this->loadProduct($productId);
            $this->exportProduct($product);
        }

        $this->finalizeHandlers();

        if ($this->config->get('product_export/ftp/enable') == '1') {
            $this->uploadFiles();
        }
    }

    protected function initLogFile()
    {
        $today = new DateTime();
        file_put_contents($this->logPath, $today->format('Y-m-d H:i:s') . "\n");
    }

    protected function loadProducts()
    {
        if ($this->config->get('product_export/temporary_disabled') == '1') {
            $this->productIds = [];
            return;
        }

        $result = xtc_db_query("SELECT
              p.products_id
            FROM products p
              LEFT JOIN addon_values_storage avs
                ON p.products_id = avs.container_id
            WHERE products_status = 1
            AND avs.container_type = 'ProductInterface'
            AND avs.addon_key = 'swix_galaxus_enabled'
            AND avs.addon_value = 1
            ORDER BY p.products_id");

        $this->productIds = [];

        while ($row = xtc_db_fetch_array($result)) {
            $this->productIds[] = $row['products_id'];
        }
		
		xtc_db_free_result($result);
    }

    protected function loadProduct($id)
    {
        $result = xtc_db_query("SELECT
              *
            FROM products p
              LEFT JOIN products_description pd
                ON p.products_id = pd.products_id
              LEFT JOIN products_item_codes pic
                ON p.products_id = pic.products_id
              LEFT JOIN manufacturers m
                ON p.manufacturers_id = m.manufacturers_id
            WHERE p.products_id = $id
            AND pd.language_id = 2 LIMIT 1");

        $product = xtc_db_fetch_array($result);

        $product['category'] = $this->loadProductCategory($id);
        $product['categories'] = $this->loadProductCategoriesNames($id);
        $product['images'] = $this->loadImages($id);
        $product['attributes'] = $this->loadProductAttributes($id);
        $product['properties_defaults'] = $this->loadProductPropertiesDefaults($id);
        $product['properties'] = $this->loadProductProperties($id);
        $product['brand_name'] = strlen($product['brand_name']) > 0 ? $product['brand_name'] : $product['manufacturers_name'];
        $product['special_price'] = $this->loadSpecialPrice($id);
        $product['galaxus_price_override'] = $this->loadPriceOverride($id);
        $product['country_of_origin'] = $this->loadCountryOfOrigin($id);

		xtc_db_free_result($result);

        return $product;
    }

    protected function loadProductProperties($id)
    {
        $result = xtc_db_query("SELECT * FROM products_properties_combis ppc
                                  WHERE ppc.products_id = $id
                                  ORDER BY ppc.sort_order, ppc.combi_model");

        $properties = [];

        while ($property = xtc_db_fetch_array($result)) {
            $property['values'] = $this->getPropertiesCombiesValues($property['products_properties_combis_id']);
            $property['images'] = $this->getPropertiesCombiesImages($property['products_properties_combis_id']);
            $properties[] = $property;
        }

		xtc_db_free_result($result);

        return $properties;
    }

    protected function loadSpecialPrice($id)
    {
        $result = xtc_db_query("SELECT specials_new_products_price FROM specials WHERE products_id = " . $id . " AND status = 1 AND CURRENT_DATE BETWEEN begins_date AND expires_date;");

        $specials = false;
        if (xtc_db_num_rows($result) > 0) {
            $row = xtc_db_fetch_array($result);
            $specials = $row['specials_new_products_price'];
        }

		xtc_db_free_result($result);

        return $specials;
    }

    protected function loadPriceOverride($id)
    {
        $result = xtc_db_query("SELECT avs.addon_value 
                                FROM addon_values_storage avs 
                                WHERE avs.container_type = 'ProductInterface' 
                                AND avs.container_id = " . $id . " 
                                AND avs.addon_key = 'swix_galaxus_price_override'");

        if (xtc_db_num_rows($result) > 0) {
            $row = xtc_db_fetch_array($result);

			xtc_db_free_result($result);

            if ((float)$row['addon_value'] > 0) {
                return (float)$row['addon_value'];
            }
        }

        return 0;
    }

    protected function loadCountryOfOrigin($id)
    {
        $result = xtc_db_query("SELECT avs.addon_value 
                                FROM addon_values_storage avs 
                                WHERE avs.container_type = 'ProductInterface' 
                                AND avs.container_id = " . $id . " 
                                AND avs.addon_key = 'swix_galaxus_country_of_origin'");

        if (xtc_db_num_rows($result) > 0) {
            $row = xtc_db_fetch_array($result);

            if (strlen($row['addon_value']) > 0) {
                return $row['addon_value'];
            }
        }

        return '';
    }

    protected function getPropertiesCombiesValues($combis_id)
    {
        $result = xtc_db_query("SELECT
                                  *
                                FROM products_properties_combis_values ppcv
                                  LEFT JOIN properties_values pv
                                    ON ppcv.properties_values_id = pv.properties_values_id
                                  LEFT JOIN properties_values_description pvd ON pv.properties_values_id = pvd.properties_values_id
                                  LEFT JOIN properties p
                                    ON pv.properties_id = p.properties_id
                                  LEFT JOIN properties_description pd
                                    ON p.properties_id = pd.properties_id
                                WHERE ppcv.products_properties_combis_id = $combis_id
                                AND pvd.language_id = 2
                                AND pd.language_id = 2
                                ORDER BY pv.sort_order");
        $values = [];

        while ($row = xtc_db_fetch_array($result)) {
            $values[] = $row;
        }
		
		xtc_db_free_result($result);
		
        return $values;
    }

    protected function getPropertiesCombiesImages($combis_id)
    {
        $result = xtc_db_query("SELECT product_image_list_image_local_path FROM product_image_list_combi pilc
                                  LEFT JOIN product_image_list_image pili ON pilc.product_image_list_id = pili.product_image_list_id
                                  WHERE pilc.products_properties_combis_id = " . $combis_id . "
                                  ORDER BY pili.product_image_list_image_sort_order");
        $images = [];

        while ($row = xtc_db_fetch_array($result)) {
            $images[] = $row['product_image_list_image_local_path'];
        }

		xtc_db_free_result($result);

        return $images;
    }

    protected function loadProductCategory($id)
    {
        $result = xtc_db_query("SELECT * FROM products_to_categories ptc
                                  LEFT JOIN categories_description cd ON ptc.categories_id = cd.categories_id
                                  WHERE ptc.products_id = $id
                                  AND ptc.categories_id > 0
                                  AND cd.language_id = 2");

        if ($result->num_rows > 0) {
			$row = xtc_db_fetch_array($result);
			xtc_db_free_result($result);
			
            return $row;
        }

        return [];
    }

    protected function loadProductCategoriesNames($id)
    {
        $categoryNames = [];
        $result = xtc_db_query("SELECT ci.categories_index FROM categories_index ci WHERE ci.products_id = " . $id);
        if (xtc_db_num_rows($result)) {
            $row = xtc_db_fetch_array($result);

            $paths = explode('--', $row['categories_index']);

            foreach($paths as $category_id) {
                $category_id = str_replace('-', '', $category_id);
                if (isset($this->categories[$category_id])) {
                    $categoryNames[] = $this->categories[$category_id];
                }
            }
        }

		xtc_db_free_result($result);

        return $categoryNames;
    }

    protected function loadImages($id)
    {
        $result = xtc_db_query("SELECT * FROM products_images WHERE products_id = $id AND gm_show_image = 1 ORDER BY image_nr");

        $images = [];

        while($row = xtc_db_fetch_array($result)) {
            $images[] = $row['image_name'];
        }

		xtc_db_free_result($result);

        return $images;
    }

    protected function loadProductAttributes($id)
    {
        $result = xtc_db_query("SELECT
                                  *
                                FROM products_attributes pa
                                  LEFT JOIN products_options po ON pa.options_id = po.products_options_id
                                  LEFT JOIN products_options_values pov ON pa.options_values_id = pov.products_options_values_id
                                WHERE pa.products_id = $id
                                  AND po.language_id = 2
                                  AND pov.language_id = 2   
                                  ORDER BY pa.attributes_model");

        $attributes = [];

        while ($row = xtc_db_fetch_array($result)) {
            $attributes[] = $row;
        }

		xtc_db_free_result($result);

        return $attributes;
    }

    protected function loadProductPropertiesDefaults($id)
    {
        $result = xtc_db_query("SELECT * FROM products_properties_combis_defaults ppcd WHERE ppcd.products_id = $id");

        if ($result->num_rows > 0) {
			$row = xtc_db_fetch_array($result);
			
			xtc_db_free_result($result);
			
            return $row;
        }

        return false;
    }

    protected function initHandlers()
    {
        foreach($this->exportHandlers as $exportHandler)
        {
            $exportHandler->setExportPath($this->exportPath);
            $exportHandler->setExportManager($this);
            $exportHandler->init();
        }
    }

    protected function finalizeHandlers()
    {
        foreach($this->exportHandlers as $exportHandler)
        {
            $exportHandler->finalize();
        }
    }

    protected function exportProduct($product)
    {
        foreach($this->exportHandlers as $exportHandler) {
            $exportHandler->exportProduct($product);
        }
    }
	
	protected function exportBackup()
	{
		$date = new DateTime();
		$exportBackupPath = $this->exportPath . '_old_' . $date->format('YmdHis') . '/';
		
		if (!is_dir($exportBackupPath)) {
			mkdir($exportBackupPath, 0755, true);
		}
		
		$files = scandir($this->exportPath);
		foreach($files as $file) {
			if (is_file($this->exportPath . $file)) {
				copy($this->exportPath . $file, $exportBackupPath . $file);
			}
		}
	}

    protected function uploadFiles()
    {
        $sftp = new SFTP($this->config->get('product_export/ftp/server'), $this->config->get('product_export/ftp/port'));
        $ftp_folder = $this->config->get('product_export/testmode') == '1' ? $this->config->get('product_export/ftp/folder_test') : $this->config->get('product_export/ftp/folder_prod');

        if ($sftp->login($this->config->get('product_export/ftp/username'), $this->config->get('product_export/ftp/password'))) {

            if (!$sftp->is_dir($ftp_folder)) {
                $sftp->mkdir($ftp_folder, -1, true);
            }

            $sftp->chdir($ftp_folder);

            $files = scandir($this->exportPath);
            foreach($files as $file) {
                if (is_file($this->exportPath . $file)) {
                    $sftp->put($file, file_get_contents($this->exportPath . $file));
                }
            }
        } else {
            $sftp->disconnect();
            die('FTP Verbindung zu Galaxus funktioniert nicht!');
        }

        $sftp->disconnect();
    }

    public function log($text)
    {
        file_put_contents($this->logPath, $text . "\n", FILE_APPEND);
    }
}