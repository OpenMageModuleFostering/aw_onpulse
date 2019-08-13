<?php

class AW_Onpulse_Model_Aggregator_Components_Statistics extends AW_Onpulse_Model_Aggregator_Component
{
    const COUNT_CUSTOMERS = 5;
    const MYSQL_DATE_FORMAT = 'Y-d-m';

    private function _getShiftedDate()
    {
        $timeShift = Mage::app()->getLocale()->date()->get(Zend_Date::TIMEZONE_SECS);
        $now = date(self::MYSQL_DATE_FORMAT, time() + $timeShift);
        $now = new Zend_Date($now);
        return $now;
    }

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
        $dashboard['bestsellers'] = $this->_getBestsellers($today);
        $aggregator->setData('dashboard',$dashboard);

    }

    private function _getByers($date) {
        /** @var $todayRegistered Mage_Customer_Model_Resource_Customer_Collection */
        $todayRegistered = Mage::getModel('customer/customer')->getCollection();
        $todayRegistered->addAttributeToFilter('created_at', array(
            'from' => $date->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),
            'to' => $date->addDay(1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
        ));
        $todayRegistered->addAttributeToSelect('*');

        $date->addDay(-1);
        /* @var $collection Mage_Reports_Model_Mysql4_Order_Collection */
        $customerArray = array();
        $todayOrders = Mage::getModel('sales/order')->getCollection();
        $todayOrders->addAttributeToFilter('created_at', array(
            'from' => $date->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),
            'to' => $date->addDay(1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
        ));
        foreach ($todayOrders as $order) {
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
        $yesterdayCustomers = $this->_getByers($date->addDay(-2));

        return array('online_visistors' => $online, 'today_customers' => $todayCustomers, 'yesterday_customers' => $yesterdayCustomers);
    }

    private function _getBestsellers($date)
    {
        /** @var  $date Zend_Date */
        $orderstatus = Mage::getStoreConfig('awonpulse/general/ordersstatus');
        $orderstatus = explode(',', $orderstatus);
        if (count($orderstatus)==0){
            $orderstatus = array(Mage_Sales_Model_Order::STATE_COMPLETE);
        }
        //Collect all orders for last 30 days
        /** @var  $orders Mage_Sales_Model_Resource_Order_Collection */
        $orders = Mage::getResourceModel('sales/order_collection');
        $orders->addAttributeToFilter('created_at', array(
            'from' => $date->addDay(-30)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),
            'to'=>$date->addDay(31)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
        ))->addAttributeToSelect('*')
            ->addAttributeToFilter('status', array('in' => $orderstatus));
        $items = array();

        /** @var $order Mage_Sales_Model_Order */
        foreach($orders as $order) {
            $orderItems = $order->getAllVisibleItems();
            if(count($orderItems)>0) {
                foreach($orderItems as $orderItem) {
                    $key = array_key_exists($orderItem->getProduct()->getId(),$items);
                    if($key === false) {
                        $items[$orderItem->getProduct()->getId()] = array(
                            'name'=>$orderItem->getProduct()->getName(),
                            'qty'=>0,
                            'amount' => 0
                        );
                    }
                    $items[$orderItem->getProduct()->getId()]['qty'] += $orderItem->getQtyOrdered();
                    $items[$orderItem->getProduct()->getId()]['amount'] += $orderItem->getBaseRowTotal()-$orderItem->getBaseDiscountInvoiced();
                }
            }
        }
        if(count($items) > 0) {
            foreach ($items as $id => $row) {

                $name[$id]  = $row['name'];
                $qty[$id] = $row['qty'];
            }
            array_multisort($qty, SORT_DESC, $name, SORT_ASC, $items);
        }
        return $items;
    }


    private function _getOrders($date)
    {

        //collect yesterday orders count
        $ordersstatus = Mage::getStoreConfig('awonpulse/general/ordersstatus');
        $ordersstatus = explode(',', $ordersstatus);
        if (count($ordersstatus)==0){
           $ordersstatus = array(Mage_Sales_Model_Order::STATE_COMPLETE);
        }
        /** @var $yesterdayOrders Mage_Sales_Model_Resource_Order_Collection */
        $yesterdayOrders = Mage::getResourceModel('sales/order_collection');

        $yesterdayOrders->addAttributeToFilter('created_at', array(
            'from' => $date->addDay(-1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),
            'to'=>$date->addDay(1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
        ))->addAttributeToSelect('*')
            ->addAttributeToFilter('status', array('in' => $ordersstatus));


        //collect today orders count

        /** @var $yesterdayOrders Mage_Sales_Model_Resource_Order_Collection */
        $todayOrders = Mage::getResourceModel('sales/order_collection');
        $todayOrders->addAttributeToFilter('created_at', array('from' => $date->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)))
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', array('in' => $ordersstatus));

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
        $ordersstatus = Mage::getStoreConfig('awonpulse/general/ordersstatus');
        $ordersstatus = explode(',', $ordersstatus);
        if (count($ordersstatus)==0){
            $ordersstatus = array(Mage_Sales_Model_Order::STATE_COMPLETE);
        }
        $shiftedDate = $this->_getShiftedDate();
        $shiftedDate->addDay(1);
        $date->addDay(1);
        $copyDate = clone $date;
        $revenue = array();
        for($i=0;$i<15;$i++){
            /** @var $yesterdayOrders Mage_Sales_Model_Resource_Order_Collection */
            $orders = Mage::getModel('sales/order')->getCollection();
            $orders->addAttributeToFilter('created_at', array('from' => $date->addDay(-1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),'to'=>$date->addDay(1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)))
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('status', array('in' => $ordersstatus));
            $date->addDay(-1);
            $shiftedDate->addDay(-1);
            $revenue[$i]['revenue']=0;
            $revenue[$i]['date']=$shiftedDate->toString(Varien_Date::DATE_INTERNAL_FORMAT);
            if($orders->getSize() > 0){
                foreach($orders as $order){
                        $revenue[$i]['revenue']+=$order->getBaseGrandTotal();
                }
            }
        }
        $daysFrom1st=$copyDate->get(Zend_Date::DAY);
        $orders = Mage::getModel('sales/order')->getCollection();
        $orders->addAttributeToFilter('created_at', array('from' => $copyDate->addDay(-$daysFrom1st)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),'to'=>$copyDate->addDay($daysFrom1st)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)))
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', array('in' => $ordersstatus));
        $thisMonthSoFar = 0;
        if($orders->getSize() > 0){
            foreach($orders as $order){
                $thisMonthSoFar+=$order->getBaseGrandTotal();
            }
        }
        $thisMonthForecast = 0;
        $numberDaysInMonth = $copyDate->get(Zend_Date::MONTH_DAYS);
        $thisMonthAvg = $thisMonthSoFar / $daysFrom1st;
        $thisMonthForecast = $thisMonthAvg * $numberDaysInMonth;
        $thisMonth = array();
        $thisMonth['thisMonthSoFar'] = Mage::helper('awonpulse')->getPriceFormat($thisMonthSoFar);
        $thisMonth['thisMonthAvg'] = Mage::helper('awonpulse')->getPriceFormat($thisMonthAvg);
        $thisMonth['thisMonthForecast'] = Mage::helper('awonpulse')->getPriceFormat($thisMonthForecast);

        return array('revenue'=>$revenue, 'thisMonth'=>$thisMonth);
    }
}
