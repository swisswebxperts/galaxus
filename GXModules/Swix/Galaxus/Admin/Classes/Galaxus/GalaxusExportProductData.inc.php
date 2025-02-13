<?php

class GalaxusExportProductData extends GalaxusExport
{
    protected $importRegister;
    protected $maxCategoryLevels;

    public function __construct($config)
    {
        parent::__construct($config);

        $this->exportFileName = 'ProductData_' . strtolower($config->get('product_export/file_suffix')) . '.csv';
        $this->maxCategoryLevels = $this->getMaxCategoryLevels();
        $this->importRegister = [];
    }

    public function exportProduct($product)
    {
        if (count($product['properties']) > 0) {
            $this->exportProductWithProperties($product);
        } else if (count($product['attributes']) > 0) {
            $this->exportProductWithAttributes($product);
        } else {
            $this->exportProductNormal($product);
        }
    }

    protected function getColumns()
    {
        $columns = [
            'ProviderKey',
            'Gtin',
            'ManufacturerKey',
            'BrandName',
            'WarrantyPeriod',
            'DeadOnArrivalPeriod',
            'ReturnType',
        ];

        for($i = 1; $i <= $this->maxCategoryLevels; $i++) {
            $columns[] = 'CategoryGroup_' . $i;
        }

        $columns = array_merge($columns, [
            'ProductCategory',
            'WeightG',
            'LengthM',
            'WidthM',
            'HeightM',
            'MinimumAge_country',
            'TARESCode',
            'TARICCode',
            'CountryOfOrigin',
            'ReleaseDate_country',
            'ProductSuperType',
            'ProductTitle_de',
            'ShortDescription_de',
            'LongDescription_de'
        ]);

        return $columns;
    }

    protected function getDefaultColumn()
    {
        $columnTitles = $this->getColumns();
        $column = [];

        foreach($columnTitles as $title) {
            $column[$title] = '';
        }

        return $column;
    }

    protected function exportProductWithProperties($product)
    {
        foreach($product['properties'] as $property) {

            $providerKey = $product['products_id'];

            if (strlen($providerKey) > 0) {
                $providerKey .= '_';
            }
            $providerKey .= $property['products_properties_combis_id'];

            $productCategory = '';
            if (count($product['category']) > 0) {
                $productCategory = $product['category']['categories_name'];
            }

            $weightG = $product['products_weight'] * 1000;

            if ($product['use_properties_combis_weight'] == '1') {
                $weightG += $property['combi_weight'] * 1000;
            }

            $gtin = (string)$property['combi_ean'];

            $manufacturerKey = '';
            if (strlen($gtin) == 0) {
                $manufacturerKey = $providerKey;
            }

            $data = $this->getDefaultColumn();

            $data['ProviderKey'] = $providerKey;
            $data['Gtin'] = $gtin;
            $data['BrandName'] = $product['brand_name'];
            $data['ManufacturerKey'] = $manufacturerKey;

            for($i = 1; $i <= $this->maxCategoryLevels; $i++) {
                if (isset($product['categories'][$i - 1])) {
                    $data['CategoryGroup_' . $i] = $product['categories'][$i - 1];
                } else {
                    $data['CategoryGroup_' . $i] = '';
                }
            }
            
            $data['ProductCategory'] = $productCategory;
            $data['WeightG'] = $weightG;
            $data['ProductTitle_de'] = $product['products_name'];
            $data['ShortDescription_de'] = html_entity_decode(strip_tags($product['products_short_description']));
            $data['LongDescription_de'] = html_entity_decode(strip_tags($product['products_description']));
            $data['CountryOfOrigin'] = $product['country_of_origin'];

            $data = $this->filterData($data);
            $this->writeLine($data);

            $this->importRegister($providerKey, $product['products_name']);
        }
    }

    protected function exportProductWithAttributes($product)
    {
        foreach($product['attributes'] as $attribute) {
            $providerKey = $product['products_id'];

            if (strlen($providerKey) > 0) {
                $providerKey .= '_';
            }
            $providerKey .= $attribute['products_attributes_id'];

            $productCategory = '';
            if (count($product['category']) > 0) {
                $productCategory = $product['category']['categories_name'];
            }

            $weightG = $product['products_weight'] * 1000;

            if ($attribute['weight_prefix'] == '+') {
                $weightG += $attribute['options_values_weight'] * 1000;
            } elseif ($attribute['weight_prefix'] == '-') {
                $weightG -= $attribute['options_values_weight'] * 1000;
            }

            $gtin = (string)$attribute['gm_ean'];

            $manufacturerKey = '';
            if (strlen($gtin) == 0) {
                $manufacturerKey = $providerKey;
            }

            $data = $this->getDefaultColumn();

            $data['ProviderKey'] = $providerKey;
            $data['Gtin'] = $gtin;
            $data['ManufacturerKey'] = $manufacturerKey;
            $data['BrandName'] = $product['brand_name'];

            for($i = 1; $i <= $this->maxCategoryLevels; $i++) {
                if (isset($product['categories'][$i - 1])) {
                    $data['CategoryGroup_' . $i] = $product['categories'][$i - 1];
                } else {
                    $data['CategoryGroup_' . $i] = '';
                }
            }

            $data['ProductCategory'] = $productCategory;
            $data['WeightG'] = $weightG;
            $data['ProductTitle_de'] = $product['products_name'];
            $data['ShortDescription_de'] = html_entity_decode(strip_tags($product['products_short_description']));
            $data['LongDescription_de'] = html_entity_decode(strip_tags($product['products_description']));
            $data['CountryOfOrigin'] = $product['country_of_origin'];

            $data = $this->filterData($data);
            $this->writeLine($data);

            $this->importRegister($providerKey, $product['products_name']);
        }
    }

    protected function exportProductNormal($product)
    {
        $productCategory = '';
        if (count($product['category']) > 0) {
            $productCategory = $product['category']['categories_name'];
        }

        $gtin = (string)$product['products_ean'];

        $manufacturerKey = '';
        if (strlen($gtin) == 0) {
            $manufacturerKey = $product['products_model'];
        }

        $data = $this->getDefaultColumn();

        $data['ProviderKey'] = $product['products_id'];
        $data['Gtin'] = $gtin;
        $data['ManufacturerKey'] = $manufacturerKey;
        $data['BrandName'] = $product['brand_name'];

        for($i = 1; $i <= $this->maxCategoryLevels; $i++) {
            if (isset($product['categories'][$i - 1])) {
                $data['CategoryGroup_' . $i] = $product['categories'][$i - 1];
            } else {
                $data['CategoryGroup_' . $i] = '';
            }
        }

        $data['ProductCategory'] = $productCategory;
        $data['WeightG'] = $product['products_weight'] * 1000;
        $data['ProductTitle_de'] = $product['products_name'];
        $data['ShortDescription_de'] = html_entity_decode(strip_tags($product['products_short_description']));
        $data['LongDescription_de'] = html_entity_decode(strip_tags($product['products_description']));
        $data['CountryOfOrigin'] = $product['country_of_origin'];

        $data = $this->filterData($data);
        $this->writeLine($data);

        $this->importRegister($product['products_model'], $product['products_name']);
    }

    protected function getCategories()
    {

    }

    protected function importRegister($key, $name)
    {
        $this->importRegister[$key][] = $name;
    }

    public function finalize()
    {
        parent::finalize();

        foreach($this->importRegister as $key => $array) {
            if (count($array) > 1) {
                $this->log('Mehrfache Einträge für ' . $key . ':');
                foreach($array as $value) {
                    $this->log($value);
                }
                $this->log('');
            }
        }
    }

    protected function getMaxCategoryLevels()
    {
        $result = xtc_db_query("SELECT count(ptc.categories_id) AS anzahl, ptc.products_id FROM products_to_categories ptc 
                                  LEFT JOIN products p ON ptc.products_id = p.products_id
                                  WHERE p.products_status = 1
                                  GROUP BY ptc.products_id ORDER BY 1 DESC");

        if (xtc_db_num_rows($result) > 0) {
            $row = xtc_db_fetch_array($result);

            return $row['anzahl'];
        } else {
            return 10;
        }
    }

    protected function filterData($data)
    {
        $data = parent::filterData($data);

        $data['ProductTitle_de'] = $this->shortenString($data['ProductTitle_de'], 100);
        $data['ProductTitle_de'] = str_replace($data['BrandName'], '', $data['ProductTitle_de']);
        $data['ProductTitle_de'] = trim($data['ProductTitle_de']);
        $data['ProductTitle_de'] = str_replace('  ', '', $data['ProductTitle_de']);

        if (strlen($data['BrandName']) == 0) {
           $data['BrandName'] = $this->config->get('product_export/brand_name');
        }

        return $data;
    }
}