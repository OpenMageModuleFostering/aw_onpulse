<?php

class AW_Onpulse_Block_System_Config_Form_Fieldset_Onpulse_Settings extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);
        $html .=$this->setTemplate('aw_onpulse/settings.phtml')->_toHtml();
        $html .= $this->_getFooterHtml($element);
        return $html;
    }
}
