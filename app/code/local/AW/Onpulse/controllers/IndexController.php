<?php

class AW_Onpulse_IndexController extends Mage_Core_Controller_Front_Action
{

    protected $_accessDeniedAlias = 'awonpulse';
    protected $_config = array();

    protected function _isAllowed()
    {
        //if strpos gives boolean result it means what alias is denied
        return is_bool(
            strpos(
                $this->getRequest()->getRequestString(),
                $this->_accessDeniedAlias
            )
        );
    }

    protected function _isAllowedByDirectLink()
    {
        if ($this->_config['ddl']) {
            return false;
        }
        //if strpos gives non boolean result it means what qrhash in url - OK
        return !is_bool(
            strpos(
                $this->getRequest()->getRequestString(),
                $this->_config['qrhash']
            )
        );
    }

    protected function _isAllowedByKeyHash()
    {
        $key = $this->getRequest()->getParam('key');
        return $key && $key == $this->_config['hash'];
    }

    public function indexAction()
    {
        if (!$this->_isAllowed()) {
            $this->_forward('noRoute');
            return $this;
        }
        $this->_config = Mage::getModel('awonpulse/credentials')->readConfig();


        //First of all check Direct link login by QR code
        $noRouteFlag = !$this->_isAllowedByDirectLink();

        //Second step: check login by key and hash
        if ($noRouteFlag) {
            $noRouteFlag = !$this->_isAllowedByKeyHash();
        }

        if ($noRouteFlag) {
            $this->_forward('noRoute');
            return $this;
        }

        $type = $this->getRequest()->getParam('type', null);
        if ($type === "search") {
            return $this->_search();
        }

        $aggregator = Mage::getSingleton('awonpulse/aggregator')->Aggregate();
        $output = Mage::helper('awonpulse')->processOutput($aggregator);
        return $this->getResponse()->setBody(serialize($output));
    }

    protected function _search()
    {
        $query = $this->getRequest()->getParam('query', null);
        $entity = $this->getRequest()->getParam('entity', null);
        if (null === $query || null === $entity) {
            $this->_forward('noRoute');
            return $this;
        }

        $aggregator = Mage::getSingleton('awonpulse/aggregator')->Aggregate();
        switch($entity) {
            case 'customer':
                Mage::getModel('awonpulse/aggregator_components_customer')->pushSearchedData($aggregator, $query);
                break;
            case 'order':
                Mage::getModel('awonpulse/aggregator_components_order')->pushSearchedData($aggregator, $query);
                break;
        }
        $output = Mage::helper('awonpulse')->processOutput($aggregator);
        return $this->getResponse()->setBody(serialize($output));
    }
}