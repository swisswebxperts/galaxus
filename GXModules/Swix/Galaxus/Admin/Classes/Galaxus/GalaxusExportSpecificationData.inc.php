<?php

class GalaxusExportSpecificationData extends GalaxusExport
{

    public function __construct($config)
    {
        parent::__construct($config);

        $this->exportFileName = 'SpecificationData_' . strtolower($config->get('product_export/file_suffix')) . '.csv';
    }

    protected function getColumns()
    {
        return [
            'ProviderKey',
            'SpecificationKey',
            'SpecificationGroup_DE',
            'SpecificationKey_DE',
            'SpecificationValue_DE',
            'SpecificationGroup_FR',
            'SpecificationKey_FR',
            'SpecificationValue_FR',
            'SpecificationGroup_EN',
            'SpecificationKey_EN',
            'SpecificationValue_EN',
            'SpecificationGroup_IT',
            'SpecificationKey_IT',
            'SpecificationValue_IT',
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

            foreach($property['values'] as $value) {

                $data = $this->getDefaultColumn();

                $data['ProviderKey'] = $providerKey;
                $data['SpecificationGroup_DE'] = $value['properties_name'];
                $data['SpecificationKey_DE'] = $value['properties_name'];
                $data['SpecificationValue_DE'] = $value['values_name'];

                $this->writeLine($data);
            }
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

            $data = $this->getDefaultColumn();

            $data['ProviderKey'] = $providerKey;
            $data['SpecificationGroup_DE'] = $attribute['products_options_name'];
            $data['SpecificationKey_DE'] = $attribute['products_options_name'];
            $data['SpecificationValue_DE'] = $attribute['products_options_values_name'];

            $this->writeLine($data);
        }
    }

    protected function exportProductNormal($product)
    {
        // Keine Spezifikationen
    }
}