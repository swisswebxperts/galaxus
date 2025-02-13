<?php

class GalaxusArticleExportAjaxHandler extends AjaxHandler
{
    public function get_permission_status($customerId = null)
    {
        return true;
    }

    public function proceed()
    {
        if (!defined('HTTPS_CATALOG_SERVER')) {
            define('HTTPS_CATALOG_SERVER', HTTPS_SERVER);
        }

        $galaxusExportManager = MainFactory::create('GalaxusExportManager');
        $galaxusExportManager->export();

        return true;
    }
}