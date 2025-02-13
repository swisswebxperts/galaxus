<?php

class SwixGalaxusGmOrderPDF extends SwixGalaxusGmOrderPDF_parent
{
    var $galaxus_order_id;

    function __construct($type, $order_right, $order_data, $order_total, $order_info, $pdf_footer, $pdf_fonts, $gm_pdf_values, $gm_order_pdf_values, $gm_use_products_model)
    {
        parent::__construct($type, $order_right, $order_data, $order_total, $order_info, $pdf_footer, $pdf_fonts, $gm_pdf_values, $gm_order_pdf_values, $gm_use_products_model);

        if (isset($gm_order_pdf_values['GALAXUS_ORDER_ID'])) {
            $this->galaxus_order_id = $gm_order_pdf_values['GALAXUS_ORDER_ID'];
        }
    }

    function getBody()
    {
        parent::getBody();

        if ($this->pdf_type == 'packingslip' && is_int($this->galaxus_order_id)) {

            $tempY = parent::GetY();
            $y = parent::GetY() + 5;

            $languageManager = MainFactory::create('LanguageTextManager', 'swix_galaxus');

            parent::SetY($y);
            parent::SetFillColor(222,235,255);
            $tempCellPaddings = parent::getCellPaddings();
            parent::setCellPaddings(3, 3, 3, 3);
            parent::MultiCell($this->pdf_inner_width, 0, $languageManager->get_text('PDF_DELIVERY_GALAXUS_NO') . $this->galaxus_order_id . "\n" . $languageManager->get_text('PDF_DELIVERY_NOTE'),
                 ['LTRB' => ['width' => 0.05]], 'L', true);

            parent::setCellPaddings($tempCellPaddings['L'], $tempCellPaddings['T'], $tempCellPaddings['R'], $tempCellPaddings['B']);
            parent::SetFillColor(0);

            parent::SetY($tempY);
        }

        return;
    }
}