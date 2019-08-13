<?php

class AW_Onpulse_Model_Aggregator_Components_Statistics extends AW_Onpulse_Model_Aggregator_Component
{
    /**
     * How much last registered customers is to display
     */
    const COUNT_CUSTOMERS = 5;

    const MYSQL_DATE_FORMAT = 'Y-d-m';

    const BESTSELLERS_DAYS_PERIOD = 15;

    /**
     * @return Zend_Date
     */
    private function _getShiftedDate()
    {
        $timeShift = Mage::app()->getLocale()->date()->get(Zend_Date::TIMEZONE_SECS);
        $now = date(self::MYSQL_DATE_FORMAT, time() + $timeShift);
        $now = new Zend_Date($now);
        return $now;
    }

    /**
     * @return Zend_Date
     */
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

    /**
     * @param $event
     */
    public function pushData($event)
    {
        $aggregator = $event->getEvent()->getAggregator();
        $dashboard = array();
        $today = $this->_getCurrentDate();

        //Load sales revenue
        $dashboard['sales']     = $this->_getSales(clone $today);

        //Load last orders
        $dashboard['orders']    = $this->_getOrders(clone $today);

        //Load last customer registrations
        $dashboard['customers'] = $this->_getCustomers(clone $today);

        //Load best selling products
        $dashboard['bestsellers'] = $this->_getBestsellers(clone $today);

        $aggregator->setData('dashboard',$dashboard);
    }

    /**
     * @param Zend_Date $date
     *
     * @return array
     */
    private function _getByers(Zend_Date $date) {
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

    /**
     * @param Zend_Date $date
     *
     * @return array
     */
    private function _getCustomers(Zend_Date $date)
    {
        //collect online visitors
        $online = Mage::getModel('log/visitor_online')
            ->prepare()
            ->getCollection()->addFieldToFilter('remote_addr',array('neq'=>Mage::helper('core/http')->getRemoteAddr(true)))->getSize();
        $todayCustomers = $this->_getByers($date);
        $yesterdayCustomers = $this->_getByers($date->addDay(-2));

        return array('online_visistors' => $online, 'today_customers' => $todayCustomers, 'yesterday_customers' => $yesterdayCustomers);
    }

    /**
     * @param Zend_Date $date
     *
     * @return array
     */
    private function _getBestsellers(Zend_Date $date)
    {
        $orderstatus = explode(',', Mage::getStoreConfig('awonpulse/general/ordersstatus',Mage::app()->getDefaultStoreView()->getId()));
        if (count($orderstatus)==0){
            $orderstatus = array(Mage_Sales_Model_Order::STATE_COMPLETE);
        }
        //Collect all orders for last N days
        /** @var  $orders Mage_Sales_Model_Resource_Order_Collection */
        $orders = Mage::getResourceModel('sales/order_collection');
        $orders->addAttributeToFilter('created_at', array(
            'from' => $date->addDay(-self::BESTSELLERS_DAYS_PERIOD)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
        ))->addAttributeToFilter('status', array('in' => $orderstatus));

        $orderIds =  Mage::getSingleton('core/resource')->getConnection('sales_read')->query($orders->getSelect()->resetJoinLeft())->fetchAll(PDO::FETCH_COLUMN,0);
        unset($orders);

        $orders = Mage::getModel('sales/order_item')->getCollection()
            ->addFieldToFilter('order_id',array('in'=>$orderIds))
            ->addFieldToFilter('parent_item_id', array('null'=> true));
        $orders =  Mage::getSingleton('core/resource')->getConnection('sales_read')->query($orders->getSelect()->resetJoinLeft())->fetchAll();

        $items = array();

        /** @var $order Mage_Sales_Model_Order */
        foreach($orders as $orderItem) {
                    $key = array_key_exists($orderItem['product_id'],$items);
                    if($key === false) {
                        $items[$orderItem['product_id']] = array(
                            'name'=>Mage::helper('awonpulse')->escapeHtml($orderItem['name']),
                            'qty'=>0,
                            'amount' => 0
                        );
                    }
                    $items[$orderItem['product_id']]['qty'] += $orderItem['qty_ordered'];
                    $items[$orderItem['product_id']]['amount'] += Mage::helper('awonpulse')->getPriceFormat($orderItem['base_row_total']-$orderItem['base_discount_invoiced']);
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


    /**
     * @param Zend_Date $date
     *
     * @return array
     */
    private function _getOrders(Zend_Date $date)
    {

        //collect yesterday orders count
        $ordersstatus = Mage::getStoreConfig('awonpulse/general/ordersstatus',Mage::app()->getDefaultStoreView()->getId());
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

    /**
     * @param Zend_Date $date
     *
     * @return array
     */
    private function _getSales(Zend_Date $date)
    {

        $ordersstatus = Mage::getStoreConfig('awonpulse/general/ordersstatus',Mage::app()->getDefaultStoreView()->getId());
        $ordersstatus = explode(',', $ordersstatus);
        if (count($ordersstatus)==0){
            $ordersstatus = array(Mage_Sales_Model_Order::STATE_COMPLETE);
        }
        $shiftedDate = $this->_getShiftedDate();
        $date->addDay(1);
        $copyDate = clone $date;
        $numberDaysInMonth = $copyDate->get(Zend_Date::MONTH_DAYS);
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
        /** @var  $copyDate Zend_Date */
        $daysFrom1st=$copyDate->get(Zend_Date::DAY);

        $orders = Mage::getModel('sales/order')->getCollection();
        $orders->addAttributeToFilter('created_at', array('from' => $copyDate->addDay(-($daysFrom1st))->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)))
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', array('in' => $ordersstatus));
        $thisMonthSoFar = 0;
        if($orders->getSize() > 0){
            foreach($orders as $order){
                $thisMonthSoFar+=$order->getBaseGrandTotal();
            }
        }

        $thisMonthAvg = $thisMonthSoFar /($daysFrom1st);

        $thisMonthForecast = $thisMonthAvg * $numberDaysInMonth;
        $thisMonth = array();
        $thisMonth['thisMonthSoFar'] = Mage::helper('awonpulse')->getPriceFormat($thisMonthSoFar);
        $thisMonth['thisMonthAvg'] = Mage::helper('awonpulse')->getPriceFormat($thisMonthAvg);
        $thisMonth['thisMonthForecast'] = Mage::helper('awonpulse')->getPriceFormat($thisMonthForecast);

        return array('revenue'=>$revenue, 'thisMonth'=>$thisMonth);
    }
}
