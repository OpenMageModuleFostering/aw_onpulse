<?php

class AW_Onpulse_Model_Aggregator_Components_Order extends AW_Onpulse_Model_Aggregator_Component
{
    const COUNT_CUSTOMERS = 5;

    public function getCollectionForOldMegento()
    {
        $collection = Mage::getResourceModel('sales/order_collection')
            ->addAttributeToSelect('*')
            ->joinAttribute('billing_firstname', 'order_address/firstname', 'billing_address_id', null, 'left')
            ->joinAttribute('billing_lastname', 'order_address/lastname', 'billing_address_id', null, 'left')
            ->joinAttribute('shipping_firstname', 'order_address/firstname', 'shipping_address_id', null, 'left')
            ->joinAttribute('shipping_lastname', 'order_address/lastname', 'shipping_address_id', null, 'left')
            ->addExpressionAttributeToSelect('billing_name',
            'CONCAT({{billing_firstname}}, " ", {{billing_lastname}})',
            array('billing_firstname', 'billing_lastname'))
            ->addExpressionAttributeToSelect('shipping_name',
            'CONCAT({{shipping_firstname}}, " ", {{shipping_lastname}})',
            array('shipping_firstname', 'shipping_lastname'));

        return $collection;
    }

    public function pushData($event = null){

        if(version_compare(Mage::getVersion(),'1.4.1','<')) {
            $orderCollection=$this->getCollectionForOldMegento();
        } else {
            $orderCollection = Mage::getModel('sales/order')->getCollection()
                ->addAddressFields()
                ->addAttributeToSelect('*')
                ->addOrder('entity_id','DESC')
                ->setPageSize(self::COUNT_CUSTOMERS);
        }
        $aggregator = $event->getEvent()->getAggregator();

        $aggregator->setData('orders', $orderCollection->load());
    }
}
