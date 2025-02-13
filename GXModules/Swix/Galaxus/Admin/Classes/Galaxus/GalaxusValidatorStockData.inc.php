<?php

class GalaxusValidatorStockData extends GalaxusValidator
{
    protected $fieldValidators = [
        'ProviderKey' => [
            'NotEmpty',
            'StringLengthMax' => 100,
            'ASCII_32_126',
        ],
    ];
}