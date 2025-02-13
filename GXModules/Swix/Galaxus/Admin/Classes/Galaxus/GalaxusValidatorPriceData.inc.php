<?php

class GalaxusValidatorPriceData extends GalaxusValidator
{
    protected $fieldValidators = [
        'ProviderKey' => [
            'NotEmpty',
            'StringLengthMax' => 100,
            'ASCII_32_126',
        ],
        'VatRatePercentage' => [
            'NotEmpty'
        ],
        'SalesPriceExcludingVat' => [
            'NotEmpty'
        ],
        'Currency' => [
            'NotEmpty'
        ]
    ];
}