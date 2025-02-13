<?php

class GalaxusExportPriceData extends GalaxusExport
{
    public function __construct($config)
    {
        parent::__construct($config);

        $this->exportFileName = 'PriceData_' . strtolower($config->get('product_export/file_suffix')) . '.csv';
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
            'VatRatePercentage',
            'SuggestedRetailPriceIncludingVat_CHF',
            'PurchasePriceExcludingFee',
            //'FeeExcludingVat_type'
            'PurchasePriceExcludingVat',
            'SalesPriceExcludingVat',
            'SalesPriceIncludingVat',
            'Currency'
        ];
    }

    protected function getDefaultColumn()
    {
        $columnTitles = $this->getColumns();
        $column = [];

        foreach($columnTitles as $title) {
            $column[$title] = '';
        }

        $column['Currency'] = $this->config->get('product_export/currency');

        return $column;
    }

    protected function exportProductWithProperties($product)
    {

        $taxRate = $this->getTaxRate($product['products_tax_class_id']);

        foreach($product['properties'] as $property) {

            $providerKey = $product['products_id'];
            if (strlen($providerKey) > 0) {
                $providerKey .= '_';
            }
            $providerKey .= $property['products_properties_combis_id'];

            $priceExcl = $product['products_price'];

            if ($property['combi_price_type'] == 'fix') {
                $priceExcl += $property['combi_price'];
            } elseif ($property['combi_price_type'] == 'calc') {

                foreach($property['values'] as $value) {
                    $priceExcl += $value['value_price'];
                }
            }

            $shippingCost = $this->getShippingCost($priceExcl);

            $data = $this->getDefaultColumn();

            $data['ProviderKey'] = $providerKey;
            $data['VatRatePercentage'] = round($taxRate, 2);
            $data['SalesPriceExcludingVat'] = round($priceExcl + $shippingCost, 4);

            if ($this->config->get('product_export/round_5_cent') == '1') {
                $data['SalesPriceExcludingVat'] = $this->round5Cent($data['SalesPriceExcludingVat'], $data['VatRatePercentage']);
            }

            $this->writeLine($data);
        }
    }

    protected function exportProductWithAttributes($product)
    {
        $taxRate = $this->getTaxRate($product['products_tax_class_id']);

        foreach($product['attributes'] as $attribute) {
            $providerKey = $product['products_id'];

            if (strlen($providerKey) > 0) {
                $providerKey .= '_';
            }
            $providerKey .= $attribute['products_attributes_id'];

            $priceExcl = $product['products_price'];

            if ($attribute['price_prefix'] == '+') {
                $priceExcl += $attribute['options_values_price'];
            } elseif ($attribute['price_prefix'] == '-') {
                $priceExcl -= $attribute['options_values_price'];
            }

            $shippingCost = $this->getShippingCost($priceExcl);

            $data = $this->getDefaultColumn();

            $data['ProviderKey'] = $providerKey;
            $data['VatRatePercentage'] = round($taxRate, 2);
            $data['SalesPriceExcludingVat'] = round($priceExcl + $shippingCost, 4);

            if ($this->config->get('product_export/round_5_cent') == '1') {
                $data['SalesPriceExcludingVat'] = $this->round5Cent($data['SalesPriceExcludingVat'], $data['VatRatePercentage']);
            }

            $this->writeLine($data);
        }
    }

    protected function exportProductNormal($product)
    {
        $price = $product['special_price'] === false ? $product['products_price'] : $product['special_price'];
        $taxRate = $this->getTaxRate($product['products_tax_class_id']);

        $shippingCost = $this->getShippingCost($price);
        $data = $this->getDefaultColumn();

        $data['ProviderKey'] = $product['products_id'];
        $data['VatRatePercentage'] = round($taxRate, 2);
        $data['SalesPriceExcludingVat'] = round($price + $shippingCost, 4);

        if ($this->config->get('product_export/round_5_cent') == '1') {
            $data['SalesPriceExcludingVat'] = $this->round5Cent($data['SalesPriceExcludingVat'], $data['VatRatePercentage']);
        }

        if ($product['galaxus_price_override'] > 0) {
            $data['SalesPriceExcludingVat'] = round($product['galaxus_price_override'] / ((100 + $taxRate) / 100), 4);
        }

        $this->writeLine($data);
    }

    protected function getTaxRate($tax_rate_class_id)
    {
        return xtc_get_tax_rate($tax_rate_class_id);
    }

    public function getShippingCost($price)
    {
        $shippingCost = 0;
        $value = 0;

        $stages = $this->config->get('product_export/shipping');

        foreach($stages as $stage) {
            if ($price >= $stage[0]) {
                $value = $stage[1];
            }
        }

        if ($value !== 0) {

            if (strpos($value, '%') !== false) {
                $shippingCost = $price * ((float)$value / 100);
            } else {
                $shippingCost = (float)$value;
            }
        }

        return $shippingCost;
    }

    public function round5Cent($unroundedPrice, $tax)
    {
        $taxFactor = $tax / 100 + 1;
        $roundedPrice = round(($unroundedPrice * $taxFactor + 0.000001) * 20) / 20;

        return round($roundedPrice / $taxFactor, 4);
    }
}