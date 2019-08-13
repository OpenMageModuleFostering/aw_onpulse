<?php

include_once("app/code/core/Mage/Adminhtml/controllers/System/ConfigController.php");
class AW_Onpulse_Adminhtml_ConfigController extends Mage_Adminhtml_System_ConfigController
{
    public function generateAction()
    {
        $this->saveAction();
        $session = Mage::getSingleton('adminhtml/session');
        $lastSessionMessage = $session->getMessages()->getLastAddedMessage();
        if (null === $lastSessionMessage) {
            return;
        }
        if ($lastSessionMessage->getType() !== 'success') {
            return;
        }
        $session->getMessages()->clear();
        $session->addSuccess(Mage::helper('awonpulse')->__('New login credentials has been generated.'));
    }
}