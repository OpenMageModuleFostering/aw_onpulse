<?php

class AW_Onpulse_Model_Credentials extends Mage_Core_Model_Abstract
{
    private $key = null;
    private $hash = null;
    private $qrhash = null;
    private $ddl = null;

    private function _readConfig()
    {
        if(!$this->qrhash) {
            //Read configuration
            if ((Mage::getStoreConfig('awonpulse/general/credurlkey'))&&(Mage::getStoreConfig('awonpulse/general/credhash'))) {
                $this->hash = Mage::getStoreConfig('awonpulse/general/credhash');
                $this->key  = Mage::getStoreConfig('awonpulse/general/credurlkey');
                $this->qrhash = md5($this->key.$this->hash);
            } else {
                return false;
            }
        }
        return true;
    }


    protected function _checkDirectLink()
    {
        $qrcode = null;
        if(!Mage::getStoreConfig('awonpulse/general/ddl')){
            $data = Mage::app()->getFrontController();
            $qrcode = mb_substr($data->getRequest()->getOriginalPathInfo(),-32);
            if(!preg_match('/[a-z0-9]{32}/',$qrcode)) return false;
            //Check request
            //if QRcode authorization
            if($qrcode) {
                if($this->_readConfig()){
                    if($qrcode != $this->qrhash) {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
            return true;
        }
        return false;
    }

    protected function _checkUrlKey()
    {
        $credurlkey = mb_substr(Mage::app()->getFrontController()->getRequest()->getOriginalPathInfo(), 1);
        $credhash = Mage::app()->getFrontController()->getRequest()->getParam('key');
        if($this->_readConfig()){
            if(($this->key!=$credurlkey) || ($this->hash!=$credhash)){
                return false;
            }
        } else {
            return false;
        }
        return true;
    }


    public function checkAuthorization()
    {
        if (Mage::getStoreConfig('advanced/modules_disable_output/AW_Onpulse')) {
            $result = array(
                'result'=>false,
                'error'=>4,
                'message'=>'Connector disabled'
            );
            die('['.serialize($result).']');
        }
        $authFlag = false;
        //Check direct link
        $authFlag = $this->_checkDirectLink();
        //Check url + ket
        if(!$authFlag) {
          $authFlag = $this->_checkUrlKey();
        }
        if(!$authFlag) {
            $result = array(
                'result'=>false,
                'error'=>1,
                'message'=>'Incorrect credentials'
            );
           // echo '['.serialize($result).']';
        } else {
            $aggregator = Mage::getSingleton('awonpulse/aggregator')->Aggregate();
            $output = Mage::helper('awonpulse')->processOutput($aggregator);
            echo serialize($output);
            die;
        }
    }
}