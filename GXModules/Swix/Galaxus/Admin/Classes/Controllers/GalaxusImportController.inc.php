<?php

class GalaxusImportController extends AdminHttpViewController
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
		//$this->jsBaseUrl           = '../GXModules/Gambio/GoogleAdWords/Build/Admin/Javascript';
		$this->stylesBaseUrl       = '../GXModules/Swix/Galaxus/Admin/Styles';
		$this->templatesBaseUrl    = DIR_FS_CATALOG . 'GXModules/Swix/Galaxus/Admin/Html';
		$this->languageTextManager = MainFactory::create('LanguageTextManager', 'swix_galaxus_import',
		                                                 $_SESSION['languages_id']);
		$this->db                  = StaticGXCoreLoader::getDatabaseQueryBuilder();

        $this->configuration = MainFactory::create('GalaxusConfigurationStorage');
	}
	
	
	/**
	 * Displays the Google AdWords overview page.
	 *
	 * @return \AdminLayoutHttpControllerResponse|bool
	 */
	public function actionDefault()
    {
        $title = new NonEmptyStringType($this->languageTextManager->get_text('page_title'));
        $template = new ExistingFile(new NonEmptyStringType($this->templatesBaseUrl . '/galaxus_import_overview.html'));

        $token = LogControl::get_secure_token();
        $token = md5($token);

        $data = MainFactory::create('KeyValueCollection', [
            'cronjob_url' => HTTP_SERVER . DIR_WS_CATALOG . 'request_port.php?module=GalaxusOrderImport&token=' . $token,
            'testmode' => $this->configuration->get('order_import/testmode') == '1',
            'active' => $this->configuration->get('order_import/active') == '1',
        ]);

        $assetsArray = [
            /*MainFactory::create('Asset', $this->stylesBaseUrl . '/galaxus_import.css'),
            MainFactory::create('Asset', $this->stylesBaseUrl . '/partials/connect_account.css'),
            MainFactory::create('Asset', $this->jsBaseUrl . '/vendor/iframe_resizer.js'),
            MainFactory::create('Asset', $this->jsBaseUrl . '/extensions/resize_iframe.js'),
            MainFactory::create('Asset', 'google_adwords.lang.inc.php'),*/
        ];

        $assets = MainFactory::create('AssetCollection', $assetsArray);

        return MainFactory::create('AdminLayoutHttpControllerResponse', $title, $template, $data, $assets);
    }

    public function actionImport()
    {
        try {
            $galaxusImportOrders = MainFactory::create('GalaxusImportOrders');
            $galaxusImportOrders->import();

            $GLOBALS['messageStack']->add_session('Galaxus Bestellungen importiert', 'success');
        } catch(Exception $e) {
            $GLOBALS['messageStack']->add_session($e->getMessage(), 'error');
        }

        return MainFactory::create('RedirectHttpControllerResponse', xtc_href_link('admin.php?do=GalaxusImport'));
    }
}