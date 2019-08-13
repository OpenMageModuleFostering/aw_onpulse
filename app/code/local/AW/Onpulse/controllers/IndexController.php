<?php
class AW_Onpulse_IndexController extends Mage_Core_Controller_Front_Action {

    //TODO Remove before publish
    public function indexAction()
    {
        $this->loadLayout();
        $aggregator = Mage::getSingleton('awonpulse/aggregator')->Aggregate();
        $output = Mage::helper('awonpulse')->processOutput($aggregator);
        echo serialize($output);

    }
}