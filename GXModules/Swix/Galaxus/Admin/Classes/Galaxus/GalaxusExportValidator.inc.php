<?php

require_once DIR_FS_CATALOG . '/vendor/autoload.php';

class GalaxusExportValidator
{
    protected $config;
    protected $exportPath;
    protected $fileNames;
    protected $validators;

    public function __construct()
    {
        $this->config = MainFactory::create('GalaxusConfigurationStorage');
        $this->exportPath = $this->config->get('product_export/export_path');
    }

    public function validate()
    {
        $this->loadFileNames();
        $this->loadValidators();
        $this->runValidators();
        $this->getErrors();
    }

    protected function loadFileNames()
    {
        $fileNames = scandir($this->exportPath);

        foreach($fileNames as $fileName) {
            if (is_file($this->exportPath . $fileName)) {
                $this->fileNames[] = $fileName;
            }
        }
    }

    protected function loadValidators()
    {
        foreach($this->fileNames as $fileName) {
            $parts = explode('_', $fileName);
            $validatorClassName = 'GalaxusValidator' . $parts[0];

            if (class_exists($validatorClassName)) {
                $validator = MainFactory::create($validatorClassName);
                $validator->setFilePath($this->exportPath . $fileName);
                $this->validators[] = $validator;
            }
        }
    }

    protected function runValidators()
    {
        foreach($this->validators as $validator) {
            $validator->validate();
        }
    }

    public function getErrors()
    {
        $errors = [];
        foreach($this->validators as $validator) {
            $errors[$validator->getName()] = $validator->getErrors();
        }

        return $errors;
    }
}