<?php

class GalaxusValidator
{
    protected $filePath;
    protected $fieldValidators = [];
    protected $currentRow;
    protected $currentRowNumber;
    protected $currentColumn;
    protected $errors = [];
    protected $textManager;

    public function __construct()
    {
        $this->textManager = MainFactory::create_object('LanguageTextManager', ['swix_galaxus_validation_errors', $_SESSION['languages_id']]);
    }

    public function getName()
    {
        return basename($this->filePath);
    }

    public function setFilePath($filePath)
    {
        $this->filePath = $filePath;
    }

    public function validate()
    {
        $file = fopen($this->filePath, 'r');

        $columns = fgetcsv($file, 0, ';', $enclosure = '"');
        $this->currentRowNumber = 1;
        while($data = fgetcsv($file, 0, ';', $enclosure = '"')) {
            $this->currentRowNumber++;
            $this->currentRow = array_combine($columns, $data);
            $this->validateDataRow();
        }
        fclose($file);
    }

    protected function validateDataRow()
    {
        foreach($this->currentRow as $key => $value) {
            $this->currentColumn = $key;
            if (isset($this->fieldValidators[$key])) {
                $this->validateDataValue($this->fieldValidators[$key], $value);
            }
        }
    }

    protected function validateDataValue($validators, $dataValue)
    {
        foreach($validators as $key => $value) {
            $validatorParams = null;

            if (is_numeric($key)) {
                $validatorName = $value;
            } else {
                $validatorName = $key;
                $validatorParams = $value;
            }

            $validatorFunctionName = 'validate' . $validatorName;

            if (method_exists($this, $validatorFunctionName)) {
                try {
                    if (is_null($validatorParams)) {
                        call_user_func([$this, $validatorFunctionName], $dataValue);
                    } else {
                        call_user_func([$this, $validatorFunctionName], $dataValue, $validatorParams);
                    }
                } catch(GalaxusValidatorException $e) {
                    $this->addError($this->currentRowNumber, $this->currentColumn, $e->getMessage());
                }
            } else {
                throw new Exception('Validator ' . $validatorName . ' doesn\'t exist.');
            }
        }
    }

    protected function validateNotEmpty($dataValue)
    {
        if (strlen($dataValue) <= 0) {
            throw new GalaxusValidatorException($this->currentColumn . ': ' . $this->textManager->get_text('not_existing'));
        }
    }

    protected function validateStringLengthMax($dataValue, $stringLength)
    {
        $text = str_replace("\n\r", "\n", $dataValue);
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        if (mb_strlen($text) > $stringLength) {
            throw new GalaxusValidatorException($this->currentColumn . ': ' . $this->textManager->get_text('string_length_bigger_than') . " $stringLength");
        }
    }

    protected function validateStringLength($dataValue, $stringLength)
    {
        $text = str_replace("\n\r", "\n", $dataValue);
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        if (mb_strlen($text) !== $stringLength) {
            throw new GalaxusValidatorException($this->currentColumn . ': ' . $this->textManager->get_text('string_length') . " $stringLength");
        }
    }

    protected function validateASCII_32_126($dataValue)
    {
        if(preg_match('/[^\x20-\x7e]/', $dataValue)) {
            throw new GalaxusValidatorException($this->currentColumn . ': ' . $this->textManager->get_text('invalid_characters'));
        }
    }

    protected function validateGtin($gtin)
    {
        if (strlen($gtin) > 0) {
            if (in_array(strlen($gtin), [8,12,13,14])) {
                $this->checkGtinDigit($gtin);
            } else {
                if (strlen($gtin) == 11) {
                    throw new GalaxusValidatorException($this->textManager->get_text('gtin_11_digits'));
                } else {
                    throw new GalaxusValidatorException($this->textManager->get_text('gtin_format_invalid'));
                }
            }
        }
    }

    protected function checkGtinDigit($gtin)
    {
        $digits = str_split(strrev(substr($gtin, 0, -1)));
        $checkDigit = substr($gtin, -1);

        $sum = 0;
        $odd = true;
        foreach ($digits as $digit) {
            if ($odd) {
                $sum += $digit * 3;
            } else {
                $sum += $digit;
            }

            $odd = !$odd;
        }

        $checkDigitCalculated = (string)(10 - ($sum % 10));
        $checkDigitCalculated = $checkDigitCalculated[strlen($checkDigitCalculated) - 1];

        if ($checkDigitCalculated != $checkDigit) {
            throw new GalaxusValidatorException($this->textManager->get_text('gtin_check_invalid'));
        }
    }

    protected function addError($row, $column, $error)
    {
        $this->errors[$error][] = [
            'line' => $row,
            'products_id' => $this->currentRow['ProviderKey'],
            'products_name' => $this->currentRow['ProductTitle_de']
        ];
    }

    public function getErrors()
    {
        return $this->errors;
    }
}