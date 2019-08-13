<?php
$installer = $this;
$installer->startSetup();
$installer->run("
UPDATE {$this->getTable('core/config_data')} SET path='awonpulse/access/credurlkey' WHERE path='awonpulse/general/credurlkey';
UPDATE {$this->getTable('core/config_data')} SET path='awonpulse/access/credhash' WHERE path='awonpulse/general/credhash';
");
$installer->endSetup();