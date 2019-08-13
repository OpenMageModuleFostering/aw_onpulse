<?php

class AW_Onpulse_Model_Credentials extends Mage_Core_Model_Abstract
{
    private $key = null;
    private $hash = null;
    private $qrhash = null;
    private $ddl = null;


    private function _updateURLRedirectEE($idPath,$oldRequestPath, $requestPath)
    {
        $keyRewrite = Mage::getModel('enterprise_urlrewrite/redirect')->loadByRequestPath($oldRequestPath, 0);
        $keyRewrite
            ->setOptions()
            ->setIdPath($idPath)
            ->setTargetPath('awonpulse')
            ->setIdentifier($requestPath)
            ->save();
    }

    private function _updateURLRewrite($idPath,$requestPath)
    {
        if(!$requestPath) {
            return;
        }
        $keyRewrite = Mage::getModel('core/url_rewrite')->loadByIdPath($idPath);
        $oldRequestPath  = $keyRewrite->getRequestPath();
        $defaultStore =  Mage::app()->getStore()->getId();
        if(Mage::app()->getDefaultStoreView() !== null) {
            $defaultStore = Mage::app()->getDefaultStoreView()->getId();
        }
        if(AW_Onpulse_Helper_Data::getPlatform() == AW_Onpulse_Helper_Data::EE_PLATFORM) {
            $this->_updateURLRedirectEE($idPath,$oldRequestPath, $requestPath);
        }
        $keyRewrite
            ->setIsSystem(0)
            ->setStoreId($defaultStore)
            ->setOptions('')
            ->setIdPath($idPath)
            ->setTargetPath('awonpulse')
            ->setRequestPath($requestPath)
            ->save();
    }

    public function readConfig()
    {

        $defaultStore =  Mage::app()->getStore()->getId();
        if(Mage::app()->getDefaultStoreView() !== null) {
            $defaultStore = Mage::app()->getDefaultStoreView()->getId();
        }


        if ((!Mage::getStoreConfig('awonpulse/general/credurlkey', $defaultStore))
            && (!Mage::getStoreConfig('awonpulse/general/credhash', $defaultStore))
        ) {
            Mage::app()->setUpdateMode(false);
            Mage::app()->init('','store');
        }

        //Read configuration
        if ((Mage::getStoreConfig('awonpulse/general/credurlkey', $defaultStore))
            && (Mage::getStoreConfig('awonpulse/general/credhash', $defaultStore))
        ) {
            $this->hash = Mage::getStoreConfig(
                'awonpulse/general/credhash', $defaultStore
            );
            $this->ddl = Mage::getStoreConfig('awonpulse/general/ddl', $defaultStore);
            $this->key = Mage::getStoreConfig(
                'awonpulse/general/credurlkey', $defaultStore
            );
            $this->qrhash = md5($this->key . $this->hash);
        }

        return array(
            'hash' => $this->hash,
            'key' => $this->key,
            'qrhash' => $this->qrhash,
            'ddl' => $this->ddl
        );

    }

    public function updateSettings($observer)
    {
        $this->readConfig();
        $this->_updateURLRewrite('onpulse/qrhash',$this->qrhash);
        $this->_updateURLRewrite('onpulse/key',$this->key);
    }
}