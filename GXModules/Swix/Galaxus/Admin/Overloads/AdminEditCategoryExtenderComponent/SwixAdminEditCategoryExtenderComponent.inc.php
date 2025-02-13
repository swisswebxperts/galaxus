<?php

class SwixAdminEditCategoryExtenderComponent extends SwixAdminEditCategoryExtenderComponent_parent
{
    function proceed()
    {
        parent::proceed();

        $contentView = MainFactory::create('ContentView');
        $contentView->set_template_dir(DIR_FS_CATALOG . 'GXModules/Swix/Galaxus/Admin/Html/');
        $contentView->set_content_template('galaxus_category_configuration.html');
        $contentView->set_flat_assigns(true);
        $contentView->set_caching_enabled(false);

        $this->v_output_buffer['left']['swix_galaxus'] = [
            'title' => 'Galaxus Einstellungen',
            'content' => $contentView->get_html(),
        ];
    }
}