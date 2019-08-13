<?php

class AW_Onpulse_Model_Aggregator extends Mage_Core_Model_Abstract
{
    public function Aggregate() {
        Mage::dispatchEvent('onpulse_aggregate_data',array('aggregator'=>$this));
        return $this;
    }
}