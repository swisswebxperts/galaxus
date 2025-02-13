<?php

class GalaxusController extends AdminHttpViewController
{
    /**
     * @var string
     */
    protected $jsBaseUrl;

    /**
     * @var string
     */
    protected $stylesBaseUrl;

    /**
     * @var string
     */
    protected $templatesBaseUrl;

    /**
     * @var \LanguageTextManager
     */
    protected $languageTextManager;

    /**
     * @var \CI_DB_query_builder
     */
    protected $db;

    protected $configuration;

    /**
     * Initialize the controller.
     */
    public function init()
    {
        /*$this->jsBaseUrl           = '../GXModules/Gambio/GoogleAdWords/Build/Admin/Javascript';
        $this->stylesBaseUrl       = '../GXModules/Gambio/GoogleAdWords/Build/Admin/Styles';*/
        $this->templatesBaseUrl = DIR_FS_CATALOG . 'GXModules/Swix/Galaxus/Admin/Html';
        $this->languageTextManager = MainFactory::create('LanguageTextManager', 'swix_galaxus',
            $_SESSION['languages_id']);
        $this->db = StaticGXCoreLoader::getDatabaseQueryBuilder();

        $this->configuration = MainFactory::create('GalaxusConfigurationStorage');
    }

    public function actionDefault()
    {
        $title = new NonEmptyStringType($this->languageTextManager->get_text('page_title'));
        $template = new ExistingFile(new NonEmptyStringType($this->templatesBaseUrl . '/galaxus_overview.html'));

        $token = LogControl::get_secure_token();
        $token = md5($token);

        $galaxusExportValidator = MainFactory::create('GalaxusExportValidator');
        $galaxusExportValidator->validate();

        $galaxusExportReportContentView = MainFactory::create('GalaxusExportReportContentView', $this->configuration);
        $galaxusExportReportContentView->setErrors($galaxusExportValidator->getErrors());

        $data = MainFactory::create('KeyValueCollection', [
            'cronjob_url' => HTTP_SERVER . DIR_WS_CATALOG . 'request_port.php?module=GalaxusArticleExport&token=' . $token,
            'report' => $galaxusExportReportContentView->get_html(),
            'testmode' => $this->configuration->get('product_export/testmode') == '1',
            'active' => $this->configuration->get('product_export/active') == '1',
        ]);

        $assetsArray = [
            /*MainFactory::create('Asset', $this->stylesBaseUrl . '/campaigns_overview.css'),
            MainFactory::create('Asset', $this->stylesBaseUrl . '/partials/connect_account.css'),
            MainFactory::create('Asset', $this->jsBaseUrl . '/vendor/iframe_resizer.js'),
            MainFactory::create('Asset', $this->jsBaseUrl . '/extensions/resize_iframe.js'),
            MainFactory::create('Asset', 'google_adwords.lang.inc.php'),*/
        ];

        $assets = MainFactory::create('AssetCollection', $assetsArray);

        return MainFactory::create('AdminLayoutHttpControllerResponse', $title, $template, $data, $assets);
    }

    public function actionExport()
    {
        try {
            $galaxusExportManager = MainFactory::create('GalaxusExportManager');
            $galaxusExportManager->export();

            $GLOBALS['messageStack']->add_session('Galaxus Daten exportiert', 'success');
        } catch(Exception $e) {
            $GLOBALS['messageStack']->add_session($e->getMessage(), 'error');
        }

        return MainFactory::create('RedirectHttpControllerResponse', xtc_href_link('admin.php?do=Galaxus'));
    }

    public function actionConfiguration()
    {
        $this->languageTextManager = MainFactory::create('LanguageTextManager', 'swix_galaxus_configuration', $_SESSION['languages_id']);

        $title = new NonEmptyStringType($this->languageTextManager->get_text('page_title'));
        $template = new ExistingFile(new NonEmptyStringType($this->templatesBaseUrl . '/galaxus_configuration.html'));

        $token = LogControl::get_secure_token();
        $token = md5($token);

        $data = MainFactory::create('KeyValueCollection', [
            'pageToken' => $_SESSION['coo_page_token']->generate_token(),
            'configuration' => $this->configuration->get_all(),
            'currencies' => $this->getAllCurrencies(),
            'order_statuses' => $this->getAllOrderStatus(),
            'cronjob_url' => HTTP_SERVER . DIR_WS_CATALOG . 'request_port.php?module=Galaxus&token=' . $token,
        ]);

        $assetsArray = [
            /*MainFactory::create('Asset', $this->stylesBaseUrl . '/campaigns_overview.css'),
            MainFactory::create('Asset', $this->stylesBaseUrl . '/partials/connect_account.css'),
            MainFactory::create('Asset', $this->jsBaseUrl . '/vendor/iframe_resizer.js'),
            MainFactory::create('Asset', $this->jsBaseUrl . '/extensions/resize_iframe.js'),
            MainFactory::create('Asset', 'google_adwords.lang.inc.php'),*/
        ];

        $assets = MainFactory::create('AssetCollection', $assetsArray);

        return MainFactory::create('AdminLayoutHttpControllerResponse', $title, $template, $data, $assets);
    }

    public function actionSaveConfiguration()
    {
        $this->languageTextManager = MainFactory::create('LanguageTextManager', 'swix_galaxus_configuration', $_SESSION['languages_id']);

        $this->_validatePageToken();

        $newConfiguration = $this->_getPostData('configuration');

        try {
            foreach ($newConfiguration as $key => $value) {
                $this->configuration->set($key, $value);
            }
        } catch( Exception $e) {
            $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('galaxus_error_saving_configuration') . ': ' . $e->getMessage(),
                'error');
        }

        $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('galaxus_configuration_saved'), 'info');

        return MainFactory::create('RedirectHttpControllerResponse', xtc_href_link('admin.php', 'do=Galaxus/Configuration'));
    }

    public function actionEnableAllProducts()
    {
        xtc_db_query("DELETE
                          FROM addon_values_storage
                        WHERE container_type LIKE 'ProductInterface'
                          AND addon_key = 'swix_galaxus_enabled'");

        xtc_db_query("INSERT INTO addon_values_storage (container_type, container_id, addon_key, addon_value) 
                      SELECT 'ProductInterface', products_id, 'swix_galaxus_enabled', '1' FROM products");

        $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('galaxus_enabled_all_products'), 'error');

        return MainFactory::create('RedirectHttpControllerResponse', xtc_href_link('admin.php', 'do=Galaxus/Configuration'));
    }

    public function actionDisableAllProducts()
    {
        xtc_db_query("DELETE
                          FROM addon_values_storage
                        WHERE container_type LIKE 'ProductInterface'
                          AND addon_key = 'swix_galaxus_enabled'");

        $GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('galaxus_disabled_all_products'), 'error');

        return MainFactory::create('RedirectHttpControllerResponse', xtc_href_link('admin.php', 'do=Galaxus/Configuration'));
    }

    protected function getAllCurrencies()
    {
        $currencies = [];
        $result = xtc_db_query("SELECT * FROM currencies ORDER BY currencies_id");
        while($row = xtc_db_fetch_array($result)) {
            $currencies[] = $row;
        }

        return $currencies;
    }

    protected function getAllOrderStatus()
    {
        $result = xtc_db_query("SELECT * FROM orders_status os WHERE os.language_id = " . $_SESSION['languages_id'] . " ORDER BY 1");

        $orderStatuses = [];
        while($row = xtc_db_fetch_array($result)) {
            $orderStatuses[] = $row;
        }

        return $orderStatuses;
    }
}