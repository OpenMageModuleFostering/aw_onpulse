<?php

class AW_Onpulse_Helper_Data extends Mage_Core_Helper_Abstract
{
    const RECENT_ORDERS_COUNT = 5;
    const PRECISION = 2;

    public $dateTimeFormat = null;

    const EE_PLATFORM = 100;
    const PE_PLATFORM = 10;
    const CE_PLATFORM = 0;

    const ENTERPRISE_DETECT_COMPANY = 'Enterprise';
    const ENTERPRISE_DETECT_EXTENSION = 'Enterprise';
    const ENTERPRISE_DESIGN_NAME = "enterprise";
    const PROFESSIONAL_DESIGN_NAME = "pro";

    protected static $_platform = -1;

    /**
     * Checks which edition is used
     * @return int
     */
    public static function getPlatform()
    {
        if (self::$_platform == -1) {
            $pathToClaim = BP . DS . "app" . DS . "etc" . DS . "modules" . DS . self::ENTERPRISE_DETECT_COMPANY . "_" . self::ENTERPRISE_DETECT_EXTENSION .  ".xml";
            $pathToEEConfig = BP . DS . "app" . DS . "code" . DS . "core" . DS . self::ENTERPRISE_DETECT_COMPANY . DS . self::ENTERPRISE_DETECT_EXTENSION . DS . "etc" . DS . "config.xml";
            $isCommunity = !file_exists($pathToClaim) || !file_exists($pathToEEConfig);
            if ($isCommunity) {
                self::$_platform = self::CE_PLATFORM;
            } else {
                $_xml = @simplexml_load_file($pathToEEConfig,'SimpleXMLElement', LIBXML_NOCDATA);
                if(!$_xml===FALSE) {
                    $package = (string)$_xml->default->design->package->name;
                    $theme = (string)$_xml->install->design->theme->default;
                    $skin = (string)$_xml->stores->admin->design->theme->skin;
                    $isProffessional = ($package == self::PROFESSIONAL_DESIGN_NAME) && ($theme == self::PROFESSIONAL_DESIGN_NAME) && ($skin == self::PROFESSIONAL_DESIGN_NAME);
                    if ($isProffessional) {
                        self::$_platform = self::PE_PLATFORM;
                        return self::$_platform;
                    }
                }
                self::$_platform = self::EE_PLATFORM;
            }
        }
        return self::$_platform;
    }



    public function getPriceFormat($price)
    {
        $price = sprintf("%01.2f", $price);
        return $price;
    }

    private $_countries = array();

    public function escapeHtml($data,$allowedTags = NULL) {
        if(version_compare(Mage::getVersion(),'1.4.1','<')) {
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
                'first_name' => $this->escapeHtml($customer->getData("{$addresType}_firstname")),
                'last_name' => $this->escapeHtml($customer->getData("{$addresType}_lastname")),
                'postcode' => $this->escapeHtml($customer->getData("{$addresType}_postcode")),
                'city' => $this->escapeHtml($customer->getData("{$addresType}_city")),
                'street' => $this->escapeHtml($customer->getData("{$addresType}_street")),
                'telephone' => $this->escapeHtml($this->escapeHtml($customer->getData("{$addresType}_telephone"))),
                'region' => $this->escapeHtml($customer->getData("{$addresType}_region")),
                'country' => $this->escapeHtml($country),
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
            'first_name' => $this->escapeHtml($order->getData("firstname")),
            'last_name' => $this->escapeHtml($order->getData("lastname")),
            'postcode' => $this->escapeHtml($order->getData("postcode")),
            'city' => $this->escapeHtml($order->getData("city")),
            'street' => $this->escapeHtml($order->getData("street")),
            'telephone' => $this->escapeHtml($order->getData("telephone")),
            'region' => $this->escapeHtml($order->getData("region")),
            'country' => $this->escapeHtml($country),
        );
    }

    private function _getCustomersRecentOrders($customer)
    {
        if(version_compare(Mage::getVersion(),'1.4.1','<')) {
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
            'price_format' =>Mage::app()->getLocale()->getJsPriceFormat(),
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