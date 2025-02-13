<?php

class GalaxusExport
{
    protected $exportPath;
    protected $exportFileName;
    protected $exportFileHandle;
    protected $exportManager;
    protected $config;

    public function __construct(GalaxusConfigurationStorage $config)
    {
        $this->config = $config;
    }

    public function setExportPath($exportPath)
    {
        $this->exportPath = $exportPath;
    }

    public function setExportManager($exportManager)
    {
        $this->exportManager = $exportManager;
    }

    public function init()
    {
        if (!is_dir($this->exportPath)) {
            mkdir($this->exportPath, 0755, true);
        }

        $this->exportFileHandle = fopen($this->exportPath . 'temp_' . $this->exportFileName, 'w');
        $this->writeLine($this->getColumns());
    }

    public function finalize()
    {
        fclose($this->exportFileHandle);
        rename($this->exportPath . 'temp_' . $this->exportFileName, $this->exportPath . $this->exportFileName);
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

    protected function writeLine($line)
    {
        $line = $this->arrayToCsv($line);
        fwrite($this->exportFileHandle, $line . "\n");
    }

    protected function arrayToCsv( array &$fields, $delimiter = ';', $enclosure = '"', $encloseAll = false, $nullToMysqlNull = false ) {
        $delimiter_esc = preg_quote($delimiter, '/');
        $enclosure_esc = preg_quote($enclosure, '/');

        $output = [];
        foreach ( $fields as $field ) {
            if ($field === null && $nullToMysqlNull) {
                $output[] = 'NULL';
                continue;
            }

            // Enclose fields containing $delimiter, $enclosure or whitespace
            if ( $encloseAll || preg_match( "/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field ) || (is_string($field) && strlen($field) > 0 ) ) {
                $output[] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
            }
            else {
                $output[] = $field;
            }
        }

        return implode( $delimiter, $output );
    }

    protected function filterData($data)
    {
        return $data;
    }

    protected function shortenString($string, $length)
    {
        if(strlen($string) > $length) {
            $string = substr($string, 0, $length);
        }

        return $string;
    }

    protected function getColumns()
    {
        // overload
    }

    protected function log($text)
    {
        $this->exportManager->log($text);
    }
}