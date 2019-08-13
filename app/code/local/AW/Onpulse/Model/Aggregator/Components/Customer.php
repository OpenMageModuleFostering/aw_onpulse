<?php

class AW_Onpulse_Model_Aggregator_Components_Customer extends AW_Onpulse_Model_Aggregator_Component
{
    const COUNT_CUSTOMERS = 30;

    public function pushData($event = null)
    {
        $customerCollection = $this->_getCustomerCollection();
        $aggregator = $event->getEvent()->getAggregator();
        $aggregator->setData('clients', $customerCollection->load());
    }

    public function pushSearchedData($aggregator, $query)
    {
        $customerCollection = $this->_getCustomerCollection();
        if (strpos($query, '@') !== FALSE) {
            $customerCollection->addAttributeToFilter('email', array('like' => '%' . $query . '%'));
        } else {
            $countryOptionArray = Mage::helper('directory')->getCountryCollection()->toOptionArray(false);
            $countryResultIdList = array();
            foreach ($countryOptionArray as $option) {
                if (stripos($option['label'], $query) !== FALSE) {
                    $countryResultIdList[] = $option['value'];
                }
            }
            $customerCollection->addAttributeToFilter(array(
                array('attribute' => 'email', 'like' => '%' . $query . '%'),
                array('attribute' => 'firstname', 'like' => '%' . $query . '%'),
                array('attribute' => 'lastname', 'like' => '%' . $query . '%'),
                array('attribute' => 'billing_postcode', 'like' => '%' . $query . '%'),
                array('attribute' => 'billing_country_id', 'in' => $countryResultIdList)
            ));
        }
        $aggregator->setData('clients', $customerCollection->load());
    }

    /**
     * @return Mage_Customer_Model_Resource_Customer_Collection
     */
    protected function _getCustomerCollection()
    {
        /** @var $customerCollection Mage_Customer_Model_Resource_Customer_Collection */
        $customerCollection = Mage::getModel('customer/customer')->getCollection()
            ->setPageSize(self::COUNT_CUSTOMERS)
            ->addNameToSelect()
            ->addAttributeToSelect('email')
            ->addAttributeToSelect('created_at')
            ->joinAttribute('billing_firstname', 'customer_address/firstname', 'default_billing', null, 'left')
            ->joinAttribute('billing_lastname', 'customer_address/lastname', 'default_billing', null, 'left')
            ->joinAttribute('billing_postcode', 'customer_address/postcode', 'default_billing', null, 'left')
            ->joinAttribute('billing_city', 'customer_address/city', 'default_billing', null, 'left')
            ->joinAttribute('billing_street', 'customer_address/street', 'default_billing', null, 'left')
            ->joinAttribute('billing_telephone', 'customer_address/telephone', 'default_billing', null, 'left')
            ->joinAttribute('billing_region', 'customer_address/region', 'default_billing', null, 'left')
            ->joinAttribute('billing_country_id', 'customer_address/country_id', 'default_billing', null, 'left')
            ->joinAttribute('shipping_firstname', 'customer_address/firstname', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_lastname', 'customer_address/lastname', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_postcode', 'customer_address/postcode', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_city', 'customer_address/city', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_street', 'customer_address/street', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_telephone', 'customer_address/telephone', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_region', 'customer_address/region', 'default_shipping', null, 'left')
            ->joinAttribute('shipping_country_id', 'customer_address/country_id', 'default_shipping', null, 'left')
        ;
        $customerCollection->getSelect()->order('entity_id DESC');
        return $customerCollection;
    }
}
