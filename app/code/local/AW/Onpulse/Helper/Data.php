<?php

class AW_Onpulse_Helper_Data extends Mage_Core_Helper_Abstract
{
    const RECENT_ORDERS_COUNT = 5;
    const PRECISION = 2;

    public $dateTimeFormat = null;

    public function getPriceFormat($price)
    {
        $price = sprintf("%01.2f", $price);
        return $price;
    }

    private $_countries = array();

    public function escapeHtml($data,$allowedTags = NULL) {
        if(AW_All_Helper_Versions::convertVersion(Mage::getVersion())<1401) {
            $data = htmlspecialchars($data);
        } else {
            $data = parent::escapeHtml($data);
        }
        return $data;
    }
    private function _getItemOptions($item)
    {
        $result = array();
        if ($options = $item->getProductOptions()) {
            if (isset($options['options'])) {
                $result = array_merge($result, $options['options']);
            }
            if (isset($options['additional_options'])) {
                $result = array_merge($result, $options['additional_options']);
            }
            if (isset($options['attributes_info'])) {
                $result = array_merge($result, $options['attributes_info']);
            }
        }
        return $result;
    }

    private function _getAddresInfoArray($customer, $addresType = 'billing')
    {

        if ($customer->getData("default_{$addresType}")) {

            //Prevent Notice if can't find country name by code
            $country = $customer->getData("{$addresType}_country_id");
            if (isset($this->_countries[$customer->getData("{$addresType}_country_id")])) {
                $country = $this->_countries[$customer->getData("{$addresType}_country_id")];
            }
            return array(
                'first_name' => $customer->getData("{$addresType}_firstname"),
                'last_name' => $customer->getData("{$addresType}_lastname"),
                'postcode' => $customer->getData("{$addresType}_postcode"),
                'city' => $customer->getData("{$addresType}_city"),
                'street' => $customer->getData("{$addresType}_street"),
                'telephone' => $this->escapeHtml($customer->getData("{$addresType}_telephone")),
                'region' => $customer->getData("{$addresType}_region"),
                'country' => $country,
            );
        }
        return array();
    }

    private function _getAddresInfoFromOrderToArray($order)
    {
        //Prevent Notice if can't find country name by code
        $country = $order->getData("country_id");
        if (isset($this->_countries[$order->getData("country_id")])) {
            $country = $this->_countries[$order->getData("country_id")];
        }
        return array(
            'first_name' => $order->getData("firstname"),
            'last_name' => $order->getData("lastname"),
            'postcode' => $order->getData("postcode"),
            'city' => $order->getData("city"),
            'street' => $order->getData("street"),
            'telephone' => $this->escapeHtml($order->getData("telephone")),
            'region' => $order->getData("region"),
            'country' => $country,
        );
    }

    private function _getCustomersRecentOrders($customer)
    {
        if(AW_All_Helper_Versions::convertVersion(Mage::getVersion())<1401) {
            $orderCollection=Mage::getModel('awonpulse/aggregator_components_order')->getCollectionForOldMegento();
        } else {
        /** @var $orderCollection Mage_Sales_Model_Resource_Order_Collection */
        $orderCollection = Mage::getModel('sales/order')->getCollection();
        $orderCollection->addAddressFields()
            ->addAttributeToSelect('*')
            ->addOrder('entity_id', 'DESC');
        }
        $orderCollection->addAttributeToFilter('customer_id', array('eq' => $customer->getId()))
            ->setPageSize(self::RECENT_ORDERS_COUNT);
        return $orderCollection;
    }

    private function _getProductsArrayFromOrder($order)
    {
        $products = array();

        foreach ($order->getItemsCollection() as $item) {
            $product = array();
            if ($item->getParentItem()) continue;
            if ($_options = $this->_getItemOptions($item)) {
                foreach ($_options as $_option) {
                    $product['options'][$_option['label']] = $_option['value'];
                }
            }
            $product['name'] = $this->escapeHtml($item->getName());
            $product['price'] = $this->getPriceFormat($item->getBaseRowTotal());
            $product['qty'] = round($item->getQtyOrdered(), self::PRECISION);
            $products[] = $product;

        }
        return $products;
    }

    public function processOutput($data)
    {
        $this->dateTimeFormat = Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
        //var_dump($this->dateTimeFormat);die;
        $clients = $data->getData('clients');
        $orders = $data->getData('orders');
        $dashboard = $data->getData('dashboard');
        $processedClients = array();
        $processedDashboardClients = array();
        $processedOrders = array();
        foreach (Mage::helper('directory')->getCountryCollection() as $country) {
            $this->_countries[$country->getId()] = $country->getName();
        }

        if ($clients->getSize())
            foreach ($clients as $customer) {
                $processedClients[] = $this->processCustomerToArray($customer, true);

            }

        if($orders->getSize())
        foreach($orders as $order) {
            $processedOrders[] = $this->processOrderToArray($order);
        }
        //var_dump($processedOrders);
//die;

        $processedDashboardClientsToday = array();
        $processedDashboardClientsYesterday = array();
        if ($dashboard['customers']['today_customers']['registered']->getSize()) {
            foreach ($dashboard['customers']['today_customers']['registered'] as $customer) {
                $processedDashboardClientsToday[] = $this->processCustomerToArray($customer, true);
            }
        }

        if ($dashboard['customers']['yesterday_customers']['registered']->getSize()) {
            foreach ($dashboard['customers']['yesterday_customers']['registered'] as $customer) {
                $processedDashboardClientsYesterday[] = $this->processCustomerToArray($customer, true);
            }
        }
        $dashboard['customers']['today_customers']['registered'] = count($processedDashboardClientsToday);
        $dashboard['customers']['yesterday_customers']['registered'] = count($processedDashboardClientsYesterday);

        return array(
            'connector_version' => (string)Mage::getConfig()->getNode()->modules->AW_Onpulse->version,
            'clients' => $processedClients,
            'orders' => $processedOrders,
            'dashboard' => $dashboard,
            'storename' => strip_tags(Mage::getStoreConfig('general/store_information/name')),
            'curSymbol' => Mage::app()->getLocale()->currency(Mage::app()->getStore()->getBaseCurrencyCode())->getSymbol(),
        );
    }


    public function processOrderToArray($order)
    {

        $customer = '';
        if ($order->getCustomerId()) {
            $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
            if ($customer)
                $customer = $this->processCustomerToArray($customer);
        }
        if(!$order->getGiftCardsAmount()) {
            $order->setGiftCardsAmount(0);
        }

        $orderInfo = array(
            'increment_id' => $order->getIncrementId(),
            'creation_date' => $order->getCreatedAtFormated($this->dateTimeFormat)->toString($this->dateTimeFormat),
            'customer_firstname' => $this->escapeHtml($order->getCustomerFirstname()),
            'customer_lastname' => $this->escapeHtml($order->getCustomerLastname()),
            'customer_email' => $order->getCustomerEmail(),
            'status_code' => $order->getStatus(),
            'status' => htmlspecialchars($order->getStatusLabel()),
            'subtotal' => $this->getPriceFormat($order->getBaseSubtotal()),
            'discount' => $this->getPriceFormat($order->getBaseDiscountAmount()),
            'grand_total' => $this->getPriceFormat($order->getBaseGrandTotal()),
            'shipping_amount' => $this->getPriceFormat($order->getBaseShippingAmount()),
            'tax' => $this->getPriceFormat($order->getBaseTaxAmount()),
            'gift_cards_amount' => $this->getPriceFormat($order->getGiftCardsAmount()),
            'currency' => Mage::app()->getLocale()->currency(Mage::app()->getStore()->getBaseCurrencyCode())->getSymbol(),

            //-----------------------------------------------------
            'items' => $this->_getProductsArrayFromOrder($order),
            'customer' => $customer,
            'billing' => $this->_getAddresInfoFromOrderToArray($order->getBillingAddress()),
        );

        if (!$order->getIsVirtual()) {
            $orderInfo['shipping'] = $this->_getAddresInfoFromOrderToArray($order->getShippingAddress());
        }

        return $orderInfo;
    }


    public function processCustomerToArray($customer, $additional = false)
    {
        $client = array();

        $client['id'] = $customer->getId();
        $client['first_name'] = $this->escapeHtml($customer->getFirstname());
        $client['last_name'] = $this->escapeHtml($customer->getLastname());
        $client['email'] = $customer->getEmail();
        //$client['date_registered'] = $customer->getCreatedAt();
        $client['date_registered'] = Mage::app()->getLocale()->date($customer->getCreatedAt())->toString($this->dateTimeFormat);

        $client['country'] = '';
        if ($customer->getData('billing_country_id')) {
            $client['country'] = $this->_countries[$customer->getData('billing_country_id')];
        }

        $client['phone'] = '';
        if ($customer->getData('billing_telephone')) {
            $client['phone'] = $this->escapeHtml($customer->getData('billing_telephone'));
        }

        if ($additional) {
            // Format billing address data
            $client['billing'] = $this->_getAddresInfoArray($customer, 'billing');

            // Format shipping address data
            $client['shipping'] = $this->_getAddresInfoArray($customer, 'shipping');

            $orders = $this->_getCustomersRecentOrders($customer);
            $customerOrders = array();
            if ($orders->getSize()) {
                foreach ($orders as $order) {
                    $customerOrders[] = $this->processOrderToArray($order);
                }
            }
            $client['orders'] = $customerOrders;
        }
        return $client;
    }
}