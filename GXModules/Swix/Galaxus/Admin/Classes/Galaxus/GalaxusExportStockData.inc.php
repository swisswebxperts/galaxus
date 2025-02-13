<?php

class GalaxusExportStockData extends GalaxusExport
{

    public function __construct($config)
    {
        parent::__construct($config);

        $this->exportFileName = 'StockData_' . strtolower($config->get('product_export/file_suffix')) . '.csv';
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
        return [
            'ProviderKey',
            'QuantityOnStock',
            'RestockTime',
            'RestockDate',
            'MinimumOrderQuantity_dd',
            'ShipmentType'
        ];
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

            if ($product['use_properties_combis_quantity'] == 1) {
                $stock = round($property['combi_quantity'], 0);
            } else {
                $stock = round($product['products_quantity'], 0);
            }

            if ($stock < 0) {
                $stock = 0;
            }

            $restockTime = '';
            if ($stock == 0) {
                $restockTime = 18;
            }

            $data = $this->getDefaultColumn();

            $data['ProviderKey'] = $providerKey;
            $data['QuantityOnStock'] = $stock;
            $data['RestockTime'] = $restockTime;

            /*$restockDate = new DateTime();
            $restockDate->add(new DateInterval('P18D'));
            $data['RestockDate'] = $restockDate->format('Y-m-d');*/

            $data['MinimumOrderQuantity_dd'] = round($product['gm_min_order'], 0);
            $data['ShipmentType'] = 2;

            $this->writeLine($data);
        }
    }

    protected function exportProductWithAttributes($product)
    {
        foreach($product['attributes'] as $attribute) {
            $providerKey = $product['products_id'];

            if (strlen($providerKey) > 0) {
                $providerKey .= '-';
            }
            $providerKey .= $attribute['products_attributes_id'];

            $restockTime = '';
            if ($attribute['attributes_stock'] <= 0) {
                $restockTime = 18;
            }

            $data = $this->getDefaultColumn();

            $data['ProviderKey'] = $providerKey;
            $data['QuantityOnStock'] = round($attribute['attributes_stock'], 0);
            $data['RestockTime'] = $restockTime;

            /*$restockDate = new DateTime();
            $restockDate->add(new DateInterval('P18D'));
            $data['RestockDate'] = $restockDate->format('Y-m-d');*/

            $data['MinimumOrderQuantity_dd'] = round($product['gm_min_order'], 0);
            $data['ShipmentType'] = 2;

            $this->writeLine($data);
        }
    }

    protected function exportProductNormal($product)
    {
        $restockTime = '';


        if ($product['products_quantity'] <= 0) {
            $restockTime = 18;
        }

        $data = $this->getDefaultColumn();

        $data['ProviderKey'] = $product['products_id'];
        $data['QuantityOnStock'] = round($product['products_quantity'], 0);
        $data['RestockTime'] = $restockTime;

        /*$restockDate = new DateTime();
        $restockDate->add(new DateInterval('P18D'));
        $data['RestockDate'] = $restockDate->format('Y-m-d');*/

        $data['MinimumOrderQuantity_dd'] = round($product['gm_min_order'], 0);
        $data['ShipmentType'] = 2;

        $this->writeLine($data);
    }

    protected function getTaxRate($tax_rate_class_id)
    {
        return xtc_get_tax_rate($tax_rate_class_id);
    }
}