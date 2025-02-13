<?php

class GalaxusExportMediaData extends GalaxusExport
{

    public function __construct($config)
    {
        parent::__construct($config);

        $this->exportFileName = 'MediaData_' . strtolower($config->get('product_export/file_suffix')) . '.csv';
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
            'MainImageURL',
            'ImageURL_1',
            'ImageURL_2',
            'ImageURL_3',
            'ImageURL_4',
            'ImageURL_5',
            'ImageURL_6',
            'ImageURL_7',
            'ImageURL_8',
            'ImageURL_9',
            'ImageURL_10',
        ];
    }

    protected function exportProductWithProperties($product)
    {
        foreach ($product['properties'] as $property) {

            $providerKey = $product['products_id'];
            if (strlen($providerKey) > 0) {
                $providerKey .= '_';
            }
            $providerKey .= $property['products_properties_combis_id'];

            $data = [
                $providerKey,
            ];

            if (count($property['images']) > 0) {

                for ($i = 0; $i < 11; $i++) {
                    if (isset($property['images'][$i])) {
                        $data[] = HTTPS_CATALOG_SERVER . '/' . $property['images'][$i];
                    } else {
                        $data[] = '';
                    }
                }

            } else {

                $images = $this->getProductImages($product);
                for ($i = 0; $i < 11; $i++) {
                    if (isset($images[$i])) {
                        $data[] = $images[$i];
                    } else {
                        $data[] = '';
                    }
                }
            }

            $this->writeLine($data);
        }
    }

    protected function exportProductWithAttributes($product)
    {
        foreach ($product['attributes'] as $attribute) {

            $providerKey = $product['products_id'];

            if (strlen($providerKey) > 0) {
                $providerKey .= '_';
            }
            $providerKey .= $attribute['products_attributes_id'];

            $data = [
                $providerKey,
            ];

            if ($this->imageExists($attribute['gm_filename'])) {

                $data[] = $this->getFullImagePath($attribute['gm_filename']);
                for ($i = 0; $i < 10; $i++) {
                    $data[] = '';
                }

            } else {

                $images = $this->getProductImages($product);
                for ($i = 0; $i < 11; $i++) {
                    if (isset($images[$i])) {
                        $data[] = $images[$i];
                    } else {
                        $data[] = '';
                    }
                }
            }

            $this->writeLine($data);
        }
    }

    protected function exportProductNormal($product)
    {
        $data = [
            $product['products_id'],
        ];

        $images = $this->getProductImages($product);
        for ($i = 0; $i < 11; $i++) {
            if (isset($images[$i])) {
                $data[] = $images[$i];
            } else {
                $data[] = '';
            }
        }

        $this->writeLine($data);
    }

    protected function imageExists($fileName, $image_path = 'popup_images')
    {
        if (strlen($fileName) == 0) {
            return false;
        }

        return file_exists(DIR_FS_CATALOG . 'images/product_images/' . $image_path . '/' . $fileName);
    }

    protected function getProductImages($product, $image_path = 'popup_images')
    {
        $images = [];

        $imageDir = DIR_FS_CATALOG . 'images/product_images/' . $image_path . '/';

        if (file_exists($imageDir . $product['products_image'])) {
            $images[] = HTTPS_CATALOG_SERVER . '/images/product_images/' . $image_path . '/' . $product['products_image'];
        }

        foreach($product['images'] as $image) {
            if (file_exists($imageDir . $image)) {
                $images[] = HTTPS_CATALOG_SERVER . '/images/product_images/' . $image_path . '/' . $image;
            }
        }

        return $images;
    }

    protected function getFullImagePath($fileName, $image_path = 'popup_images')
    {
        if (strlen($fileName) > 0 && file_exists(DIR_FS_CATALOG . 'images/product_images/' . $image_path . '/' . $fileName)) {
            return HTTPS_CATALOG_SERVER . '/images/product_images/' . $image_path . '/' . $fileName;
        }

        return '';
    }

    protected function writeLine($data)
    {
        if (strlen($data[1]) == 0) {
            return;
        }

        parent::writeLine($data);
    }
}