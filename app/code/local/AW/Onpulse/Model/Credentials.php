<?php

class AW_Onpulse_Model_Credentials extends Mage_Core_Model_Abstract
{
    private $key = null;
    private $hash = null;
    private $qrhash = null;

    private function _readConfig()
    {
        if(!$this->qrhash) {
            //Read configuration
            if ((Mage::getStoreConfig('awonpulse/general/credurlkey'))&&(Mage::getStoreConfig('awonpulse/general/credhash'))) {
                $this->hash = Mage::getStoreConfig('awonpulse/general/credhash');
                $this->key  = Mage::getStoreConfig('awonpulse/general/credurlkey');
                $this->qrhash = md5($this->key.$this->hash);
                return true;
            }
        } else {
            return true;
        }
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
        $qrcode = null;
        $data = Mage::app()->getFrontController();
        $qrcode = mb_substr($data->getRequest()->getOriginalPathInfo(),-32);
        if(!preg_match('/[a-z0-9]{32}/',$qrcode)) return;
        //Check request
        //if QRcode authorization
        if($qrcode) {
            if($this->_readConfig()){
                if($qrcode != $this->qrhash) {
                    $result = array(
                        'result'=>false,
                        'error'=>1,
                        'message'=>'Incorrect credentials'
                    );
                    die('['.serialize($result).']');
                }
            } else {
                $result = array(
                    'result'=>false,
                    'error'=>1,
                    'message'=>'Incorrect module configuration'
                );
                die('['.serialize($result).']');
            }
        } else {
            $result = array(
                'result'=>false,
                'error'=>1,
                'message'=>'Incorrect authorization data'
            );
            die('['.serialize($result).']');
        }

        $aggregator = Mage::getSingleton('awonpulse/aggregator')->Aggregate();
        $output = Mage::helper('awonpulse')->processOutput($aggregator);
        echo serialize($output);
        die;
    }

}