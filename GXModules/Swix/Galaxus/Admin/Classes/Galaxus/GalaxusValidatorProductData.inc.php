<?php

class GalaxusValidatorProductData extends GalaxusValidator
{
    protected $fieldValidators = [
        'ProviderKey' => [
            'NotEmpty',
            'StringLengthMax' => 100,
            'ASCII_32_126',
        ],
        'Gtin' => [
            'NotEmpty',
            'Gtin'
        ],
        'BrandName' => [
            'NotEmpty',
            'StringLengthMax' => 100,
        ],
        'ProductCategory' => [
            'NotEmpty',
            'StringLengthMax' => 200,
        ],
        'ProductTitle_de' => [
            'NotEmpty',
            'StringLengthMax' => 100,
        ],
        'LongDescription_de' => [
            'StringLengthMax' => 4000
        ],
        'CountryOfOrigin' => [
            'StringLength' => 2,
        ],
    ];
}