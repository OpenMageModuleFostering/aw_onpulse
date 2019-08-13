<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer = $this->startSetup();
Mage::getModel('awonpulse/credentials')->updateSettings(null);
$installer->endSetup();

