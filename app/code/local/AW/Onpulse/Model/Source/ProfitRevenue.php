<?php

class AW_Onpulse_Model_Source_ProfitRevenue
{
    const REVENUE_VALUE = 0;
    const PROFIT_VALUE = 1;
    const REVENUE_LABEL = 'Revenue';
    const PROFIT_LABEL = 'Profit';

    public function toOptionArray()
    {
        return array(
            array(
                'value' => self::REVENUE_VALUE,
                'label' => Mage::helper('awonpulse')->__('Revenue'),
            ),
            array(
                'value' => self::PROFIT_VALUE,
                'label' => Mage::helper('awonpulse')->__('Profit'),
            ),
        );
    }
}