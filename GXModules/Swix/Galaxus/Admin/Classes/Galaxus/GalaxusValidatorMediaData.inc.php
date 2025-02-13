<?php

class GalaxusValidatorMediaData extends GalaxusValidator
{
    protected $fieldValidators = [
        'ProviderKey' => [
            'NotEmpty',
            'StringLengthMax' => 100,
            'ASCII_32_126',
        ],
        'MainImageURL' => [
            'NotEmpty',
            'StringLengthMax' => 300,
        ],
        'ImageUrl_1' => [
            'StringLengthMax' => 300,
        ],
        'ImageUrl_2' => [
            'StringLengthMax' => 300,
        ],
        'ImageUrl_3' => [
            'StringLengthMax' => 300,
        ],
        'ImageUrl_4' => [
            'StringLengthMax' => 300,
        ],
        'ImageUrl_5' => [
            'StringLengthMax' => 300,
        ],
        'ImageUrl_6' => [
            'StringLengthMax' => 300,
        ],
        'ImageUrl_7' => [
            'StringLengthMax' => 300,
        ],
        'ImageUrl_8' => [
            'StringLengthMax' => 300,
        ],
        'ImageUrl_9' => [
            'StringLengthMax' => 300,
        ],
        'ImageUrl_10' => [
            'StringLengthMax' => 300,
        ],
    ];
}