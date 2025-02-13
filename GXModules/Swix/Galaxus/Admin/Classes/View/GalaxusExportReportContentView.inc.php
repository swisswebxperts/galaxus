<?php

class GalaxusExportReportContentView extends ContentView
{
    protected $config;
    protected $errors;

    public function __construct($config)
    {
        parent::__construct();

        $this->set_template_dir( DIR_FS_CATALOG . 'GXModules/Swix/Galaxus/Admin/Html/');
        $this->set_content_template('galaxus_export_report.html');
        $this->set_caching_enabled(false);
        $this->set_flat_assigns(true);

        $this->config = $config;
    }

    public function setErrors($errors)
    {
        $this->errors = $errors;
    }

    public function prepare_data()
    {
        $this->setExportMetaData();
    }

    public function setExportMetaData()
    {
        $exportMetaData = [];
        $path = $this->config->get('product_export/export_path');
        $files = scandir($path);

        foreach($files as $file) {
            if (is_file($path . $file)) {
                $metadata = pathinfo($path . $file);
                $metadata['change_date'] = filemtime($path . $file);
                $metadata['records_count'] = $this->countRecords($path . $file);
                $metadata['errors'] = [];

                if (isset($this->errors[$file])) {
                    $metadata['errors'] = $this->errors[$file];
                }

                $exportMetaData[]  = $metadata;
            }
        }

        $this->content_array['export_meta_data'] = $exportMetaData;
    }

    protected function countRecords($filepath)
    {
        $rowCount=0;
        if (($fp = fopen($filepath, "r")) !== FALSE) {
            while(!feof($fp)) {
                $data = fgetcsv($fp , 0 , ';' , '"', '"' );
                if(empty($data)) continue; //empty row
                $rowCount++;
            }
            fclose($fp);
        }
        return $rowCount - 1;
    }
}