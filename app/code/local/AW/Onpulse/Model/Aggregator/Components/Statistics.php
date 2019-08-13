<?php

class AW_Onpulse_Model_Aggregator_Components_Statistics extends AW_Onpulse_Model_Aggregator_Component
{
    const COUNT_CUSTOMERS = 5;
    const MYSQL_DATE_FORMAT = 'Y-m-d';

    private function _getCurrentDate()
    {
        $now = Mage::app()->getLocale()->date();
        $dateObj = Mage::app()->getLocale()->date(null, null, Mage::app()->getLocale()->getDefaultLocale(), false);

        //set default timezone for store (admin)
        $dateObj->setTimezone(Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE));

        //set begining of day
        $dateObj->setHour(00);
        $dateObj->setMinute(00);
        $dateObj->setSecond(00);

        //set date with applying timezone of store
        $dateObj->set($now, Zend_Date::DATE_SHORT, Mage::app()->getLocale()->getDefaultLocale());

        //convert store date to default date in UTC timezone without DST
        $dateObj->setTimezone(Mage_Core_Model_Locale::DEFAULT_TIMEZONE);

        return $dateObj;
    }
    public function pushData($event = null)
    {
        $aggregator = $event->getEvent()->getAggregator();
        $dashboard = array();
        $today = $this->_getCurrentDate();
        $dashboard['sales']     = $this->_getSales($today);
        $today = $this->_getCurrentDate();
        $dashboard['orders']    = $this->_getOrders($today);
        $today = $this->_getCurrentDate();
        $dashboard['customers'] = $this->_getCustomers($today);
        $aggregator->setData('dashboard',$dashboard);
    }

    private function _getByers($date) {
        /** @var $todayRegistered Mage_Customer_Model_Resource_Customer_Collection */
        $todayRegistered = Mage::getModel('customer/customer')->getCollection();
        $todayRegistered->addAttributeToFilter('created_at', array('from' => $date->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)));
        $todayRegistered->addAttributeToSelect('*');

        /* @var $collection Mage_Reports_Model_Mysql4_Order_Collection */
        //$collection = Mage::getResourceModel('reports/order_collection');
        $customerArray = array();
        $todayOrders = Mage::getModel('sales/order')->getCollection();
        $todayOrders->addAttributeToFilter('created_at', array('from' => $date->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)));
        foreach ($todayOrders as $order) {
            //$order->getCustomerId();
            if ($order->getCustomerId()){
                $customerArray[] = $order->getCustomerId();
            }
        }
        $customerArray = array_unique($customerArray);
        $buyers = count($customerArray);
        return array(
            'buyers'=>$buyers,
            'registered'=>$todayRegistered,
        );
    }

    private function _getCustomers($date)
    {

        //collect online visitors
        $online = Mage::getModel('log/visitor_online')
            ->prepare()
            ->getCollection()->getSize();
        $todayCustomers = null;
        $yesterdayCustomers = null;
        $todayCustomers = $this->_getByers($date);
        $yesterdayCustomers = $this->_getByers($date->addDay(-1));
        //var_dump($yesterdayCustomers);
        /*$collection
            ->groupByCustomer()
            ->addAttributeToFilter('created_at', array('gteq' => $date->toString(Varien_Date::DATE_INTERNAL_FORMAT)))
            ->addOrdersCount()
            ->joinCustomerName();
        $buyers = 0;
        foreach ($collection as $item) {
            $buyers++;
        }*/
        return array('online_visistors' => $online, 'today_customers' => $todayCustomers, 'yesterday_customers' => $yesterdayCustomers);
    }

    private function _getOrders($date)
    {

        //collect yesterday orders count

        /** @var $yesterdayOrders Mage_Sales_Model_Resource_Order_Collection */
        $yesterdayOrders = Mage::getResourceModel('sales/order_collection');
       // echo 'request'.$yesterdayOrders->getSelect().'<br>';die;
        $yesterdayOrders->addAttributeToFilter('created_at', array(
            'from' => $date->addDay(-1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),
            'to'=>$date->addDay(1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
        ))->addAttributeToSelect('*')
            ->addAttributeToFilter('state', array('eq' => Mage_Sales_Model_Order::STATE_COMPLETE));


        //collect today orders count

        /** @var $yesterdayOrders Mage_Sales_Model_Resource_Order_Collection */
        $todayOrders = Mage::getResourceModel('sales/order_collection');
        $todayOrders->addAttributeToFilter('created_at', array('from' => $date->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)))
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('state', array('eq' => Mage_Sales_Model_Order::STATE_COMPLETE));

        //collect max, min, average orders
        $order = array();
        if ($todayOrders->getSize()) {
            $order['max']       = 0;
            $order['min']       = 999999999999999;
            $order['average']   = 0;
            $ordersSum          = 0;

            foreach ($todayOrders as $item) {

                if ($item->getBaseGrandTotal() > $order['max']) {
                    $order['max'] = Mage::helper('awonpulse')->getPriceFormat($item->getBaseGrandTotal());
                }

                if ($item->getBaseGrandTotal() < $order['min']) {
                    $order['min'] = Mage::helper('awonpulse')->getPriceFormat($item->getBaseGrandTotal());
                }

                $ordersSum += Mage::helper('awonpulse')->getPriceFormat($item->getBaseGrandTotal());

            }
            $order['average'] = Mage::helper('awonpulse')->getPriceFormat($ordersSum / $todayOrders->getSize());
        } else {
            $order['max']       = 0;
            $order['min']       = 0;
            $order['average']   = 0;
        }

        return array('yesterday_orders' => $yesterdayOrders->getSize(), 'today_orders' => $todayOrders->getSize(), 'orders_totals' => $order);
    }

    private function _getSales($date)
    {

        $date->addDay(1);
        $revenue = array();
        for($i=0;$i<15;$i++){

            /** @var $yesterdayOrders Mage_Sales_Model_Resource_Order_Collection */
            $orders = Mage::getModel('sales/order')->getCollection();
            $orders->addAttributeToFilter('created_at', array('from' => $date->addDay(-1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),'to'=>$date->addDay(1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)))
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('state', array('eq' => Mage_Sales_Model_Order::STATE_COMPLETE));
            $date->addDay(-1);
            $revenue[$i]['revenue']=0;
            $revenue[$i]['date']=$date->toString(Varien_Date::DATE_INTERNAL_FORMAT);
            foreach($orders as $order){
                    $revenue[$i]['revenue']+=$order->getBaseGrandTotal();


            }
        }
        return array('revenue'=>$revenue);

    }
}
