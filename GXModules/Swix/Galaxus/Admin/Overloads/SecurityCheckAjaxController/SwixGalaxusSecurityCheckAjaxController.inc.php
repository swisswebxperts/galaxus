<?php

class SwixGalaxusSecurityCheckAjaxController extends SwixGalaxusSecurityCheckAjaxController_parent
{
    public function actionMessages()
    {
        $languageTextManager = MainFactory::create_object('LanguageTextManager');
        $galaxusConfiguration = MainFactory::create('GalaxusConfigurationStorage');

        $galaxusErrors = [];

        if ($galaxusConfiguration->get('product_export/active') == '1' && $galaxusConfiguration->get('product_export/testmode') == '1') {
            $galaxusErrors[] = $languageTextManager->get_text('EXPORT_TESTMODE_WARNING', 'swix_galaxus');
        }

        if ($galaxusConfiguration->get('order_import/active') == '1' && $galaxusConfiguration->get('order_import/testmode') == '1') {
            $galaxusErrors[] = $languageTextManager->get_text('IMPORT_TESTMODE_WARNING', 'swix_galaxus');
        }

        if (count($galaxusErrors) > 0) {
            array_unshift($galaxusErrors, $languageTextManager->get_text('ERROR_GALAXUS_TITLE', 'swix_galaxus'));

            $galaxusErrors[] = '<a href="admin.php?do=Galaxus/Configuration">' . $languageTextManager->get_text('SETTINGS', 'swix_galaxus') . '</a>';
            $GLOBALS['messageStack']->add(implode('<br>', $galaxusErrors));
        }

        return parent::actionMessages();
    }
}