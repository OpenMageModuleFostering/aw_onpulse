<?php

class AW_Onpulse_Model_Source_OrderStatus
    extends Mage_Adminhtml_Model_System_Config_Source_Order_Status
{
    public function toOptionArray()
    {
        $options = parent::toOptionArray();
        array_shift($options);
        return $options;
    }
}