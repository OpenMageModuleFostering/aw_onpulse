<?php

class AW_Onpulse_Model_Aggregator_Components_Order extends AW_Onpulse_Model_Aggregator_Component
{
    const COUNT_CUSTOMERS = 5;

    public function pushData($event = null){
        /** @var $customerCollection Mage_Sales_Model_Resource_Order_Collection */
        $orderCollection = Mage::getModel('sales/order')->getCollection()
            ->addAddressFields()
            ->addAttributeToSelect('*')
            ->addOrder('entity_id','DESC')
            ->setPageSize(self::COUNT_CUSTOMERS);

        $aggregator = $event->getEvent()->getAggregator();

        $aggregator->setData('orders', $orderCollection->load());
    }
}
