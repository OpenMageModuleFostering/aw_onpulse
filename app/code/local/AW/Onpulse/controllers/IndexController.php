<?php
class AW_Onpulse_IndexController extends Mage_Core_Controller_Front_Action {

    protected $_accessDeniedAlias = 'awonpulse';
    protected $_config = array();

    protected function _isAllowed()
    {
        //if strpos gives boolean result it means what alias is denied
        return is_bool(strpos($this->getRequest()->getRequestString(),$this->_accessDeniedAlias));
    }

    protected function _isAllowedByDirectLink()
    {
        if($this->_config['ddl']) {
            return false;
        }
        //if strpos gives non boolean result it means what qrhash in url - OK
        return !is_bool(strpos($this->getRequest()->getRequestString(),$this->_config['qrhash']));
    }

    protected function _isAllowedByKeyHash()
    {
        $key = $this->getRequest()->getParam('key');
        return $key && $key == $this->_config['hash'];
    }

    public function indexAction()
    {
        if( ! $this->_isAllowed()) {
            return $this->_forward('noRoute');
        }
        $this->_config = Mage::getModel('awonpulse/credentials')->readConfig();


        //First of all check Direct link login by QR code
        $noRouteFlag = !$this->_isAllowedByDirectLink();

        //Second step: check login by key and hash
        if($noRouteFlag) {
            $noRouteFlag = !$this->_isAllowedByKeyHash();
        }

        if($noRouteFlag) {
            return $this->_forward('noRoute');
        }

        $aggregator = Mage::getSingleton('awonpulse/aggregator')->Aggregate();
        $output = Mage::helper('awonpulse')->processOutput($aggregator);
        return $this->getResponse()->setBody(serialize($output));
    }
}