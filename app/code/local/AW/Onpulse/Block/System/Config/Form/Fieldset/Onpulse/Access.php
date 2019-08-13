<?php

class AW_Onpulse_Block_System_Config_Form_Fieldset_Onpulse_Access
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);
        $html .= $this->setTemplate('aw_onpulse/access.phtml')->_toHtml();
        $html .= $this->_getFooterHtml($element);
        return $html;
    }

    public function getGenerateUrl()
    {
        return $this->getUrl('awonpulse_admin/adminhtml_config/generate', array('section' => 'awonpulse'));
    }
}
