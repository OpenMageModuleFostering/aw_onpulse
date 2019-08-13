<?php

class AW_Onpulse_Model_Aggregator_Components_Statistics extends AW_Onpulse_Model_Aggregator_Component
{
    /**
     * How much last registered customers is to display
     */
    const COUNT_CUSTOMERS = 5;

    const MYSQL_DATE_FORMAT = 'Y-d-m';

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
    public function pushData($event = null)
    {
        $aggregator = $event->getEvent()->getAggregator();
        $dashboard = array();
        $today = $this->_getCurrentDate();

        //Load sales revenue
        $dashboard['sales'] = $this->_getSales(clone $today);

        //Load last orders
        $dashboard['orders'] = $this->_getOrders(clone $today);

        //Load last customer registrations
        $dashboard['customers'] = $this->_getCustomers(clone $today);

        //Load last orders
        $dashboard['last_orders'] = $this->_getLastOrders(clone $today);

        //Load best selling products
        $dashboard['bestsellers'] = $this->_getBestsellers(clone $today);

        //Load sales grouped by country
        $dashboard['sales_by_country'] = $this->_getSalesByCountry(clone $today);

        //Load items per order revenue
        $dashboard['items_per_order'] = $this->_getItemsPerOrder(clone $today);

        //Load average order value
        $dashboard['average_order_value'] = $this->_getAverageOrderValue(clone $today);

        //Load signups
        $dashboard['signups'] = $this->_getSignups(clone $today);

        $aggregator->setData('dashboard', $dashboard);
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
            'from' => $date->addDay(-15)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
        ))->addAttributeToFilter('status', array('in' => $orderstatus));

        $orderIds =  Mage::getSingleton('core/resource')->getConnection('sales_read')->query($orders->getSelect()->resetJoinLeft())->fetchAll(PDO::FETCH_COLUMN,0);
        unset($orders);

        $orders = Mage::getModel('sales/order_item')->getCollection()
            ->addFieldToFilter('order_id',array('in'=>$orderIds))
            ->addFieldToFilter('parent_item_id', array('null'=> true));
        $orders =  Mage::getSingleton('core/resource')->getConnection('sales_read')->query($orders->getSelect()->resetJoinLeft())->fetchAll();

        $items = array();

        /** @var $order Mage_Sales_Model_Order */
        foreach ($orders as $orderItem) {
            $key = array_key_exists($orderItem['product_id'], $items);
            if ($key === false) {
                $items[$orderItem['product_id']] = array(
                    'name' => Mage::helper('awonpulse')->escapeHtml($orderItem['name']),
                    'qty' => 0,
                    'amount' => 0
                );
            }
            $items[$orderItem['product_id']]['qty'] += $orderItem['qty_ordered'];
            $items[$orderItem['product_id']]['amount'] += Mage::helper('awonpulse')->getPriceFormat(
                $orderItem['base_row_total'] - $orderItem['base_discount_invoiced']
            );
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
        $orderStatus = Mage::getStoreConfig('awonpulse/general/ordersstatus', Mage::app()->getDefaultStoreView()->getId());
        $orderStatus = explode(',', $orderStatus);
        if (count($orderStatus)==0){
            $orderStatus = array(Mage_Sales_Model_Order::STATE_COMPLETE);
        }
        $salesStatisticUnit = Mage::getStoreConfig(
            'awonpulse/general/show_sales_statistics_as', Mage::app()->getDefaultStoreView()->getId()
        );
        $shiftedDate = $this->_getShiftedDate();
        $date->addDay(1);
        $shiftedDate->addDay(1);
        $copyDate = clone $date;
        $revenue = array();
        for ($i = 0; $i < 32; $i++) {
            /** @var $yesterdayOrders Mage_Sales_Model_Resource_Order_Collection */
            $orders = Mage::getModel('sales/order')->getCollection();
            $orders->addAttributeToFilter('created_at',
                array(
                    'from' => $date->addDay(-1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),
                    'to'   => $date->addDay(1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
                )
            )->addAttributeToSelect('*')
                ->addAttributeToFilter('status', array('in' => $orderStatus))
            ;
            $date->addDay(-1);
            $shiftedDate->addDay(-1);
            $revenue[$i]['revenue'] = 0;
            $revenue[$i]['date'] = $shiftedDate->toString(Varien_Date::DATE_INTERNAL_FORMAT);
            if ($orders->getSize() > 0) {
                foreach ($orders as $order) {
                    if ($salesStatisticUnit == AW_Onpulse_Model_Source_ProfitRevenue::PROFIT_VALUE) {
                        $baseTotalCost = 0;
                        foreach ($order->getItemsCollection() as $item) {
                            $baseTotalCost += $item->getBaseCost();
                        }
                        $revenue[$i]['revenue'] += $order->getBaseSubtotal() - $baseTotalCost;
                    } else {
                        $revenue[$i]['revenue'] += $order->getBaseGrandTotal();
                    }
                }
            }
        }
        /** @var  $copyDate Zend_Date */
        $daysFrom1st = $copyDate->get(Zend_Date::DAY);
        $startDate = clone $copyDate;
        $orders = Mage::getModel('sales/order')->getCollection();
        $orders->addAttributeToFilter('created_at', array('from' => $startDate->addDay(-($daysFrom1st))->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)))
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', array('in' => $orderStatus));
        $thisMonthSoFar = array();
        foreach($orders as $order) {
            if ($salesStatisticUnit == AW_Onpulse_Model_Source_ProfitRevenue::PROFIT_VALUE) {
                $baseTotalCost = 0;
                foreach ($order->getItemsCollection() as $item) {
                    $baseTotalCost += $item->getBaseCost();
                }
                $thisMonthSoFar[] = $order->getBaseSubtotal() - $baseTotalCost;
            } else {
                $thisMonthSoFar[] = $order->getBaseGrandTotal();
            }
        }
        $thisMonthAvg = array_sum($thisMonthSoFar) /($daysFrom1st);

        $weekendConfigStr = Mage::getStoreConfig('general/locale/weekend', Mage::app()->getDefaultStoreView()->getId());
        $weekdayConfig = array();
        if (strlen($weekendConfigStr) > 0) {
            $weekdayConfig = explode(',', $weekendConfigStr);
        }
        $workDayList = array();
        $weekendDayList = array();
        $workDayLeft = 0;
        $weekendDayLeft = 0;
        $copyDate->subDay(intval($copyDate->get(Zend_Date::DAY_SHORT)) - 1);
        for ($i = 0; $i < $copyDate->get(Zend_Date::MONTH_DAYS); $i++) {
            $weekdayDigit = intval($copyDate->get(Zend_Date::WEEKDAY_DIGIT));//from Sunday to Saturday -> from 0 to 6;
            $isWeekday = in_array($weekdayDigit, $weekdayConfig);
            if ($i < $daysFrom1st) {
                if ($isWeekday) {
                    $weekendDayList[] = $revenue[$i]['revenue'];
                } else {
                    $workDayList[] = $revenue[$i]['revenue'];
                }
            } else {
                $isWeekday?$weekendDayLeft++:$workDayLeft++;
            }
            $copyDate->addDay(1);
        }
        $workMedian = $this->_getMedianFromArray($workDayList);
        $weekendMedian = $this->_getMedianFromArray($weekendDayList);
        $thisMonthForecast = array_sum($thisMonthSoFar) + $workMedian * $workDayLeft + $weekendMedian * $weekendDayLeft;

        $thisMonth = array();
        $thisMonth['thisMonthSoFar'] = Mage::helper('awonpulse')->getPriceFormat(array_sum($thisMonthSoFar));
        $thisMonth['thisMonthAvg'] = Mage::helper('awonpulse')->getPriceFormat($thisMonthAvg);
        $thisMonth['thisMonthForecast'] = Mage::helper('awonpulse')->getPriceFormat($thisMonthForecast);

        return array('revenue'=>$revenue, 'thisMonth'=>$thisMonth);
    }

    /**
     * @param Zend_Date $date
     *
     * @return array
     */
    private function _getLastOrders(Zend_Date $date)
    {
        $orderCollection = Mage::getModel('sales/order')->getCollection()
            ->addAddressFields()
            ->addAttributeToSelect('*')
            ->addOrder('entity_id', 'DESC')
            ->setPageSize(3)
        ;
        $processedOrders = array();
        foreach ($orderCollection as $order) {
            $processedOrders[] = Mage::helper('awonpulse')->processOrderToArray($order);
        }
        return $processedOrders;
    }

    /**
     * @param Zend_Date $date
     *
     * @return array
     */
    private function _getItemsPerOrder(Zend_Date $date)
    {
        $orderStatus = Mage::getStoreConfig(
            'awonpulse/general/ordersstatus', Mage::app()->getDefaultStoreView()->getId()
        );
        $orderStatus = explode(',', $orderStatus);
        if (count($orderStatus)==0){
            $orderStatus = array(Mage_Sales_Model_Order::STATE_COMPLETE);
        }
        $shiftedDate = $this->_getShiftedDate();
        $date->addDay(1);
        $shiftedDate->addDay(1);
        $copyDate = clone $date;
        $numberDaysInMonth = $copyDate->get(Zend_Date::MONTH_DAYS);
        $revenue = array();
        $thisMonthAvgList = array();
        for ($i = 0; $i < 15; $i++) {
            /** @var $yesterdayOrders Mage_Sales_Model_Resource_Order_Collection */
            $orders = Mage::getModel('sales/order')->getCollection();
            $orders->addAttributeToFilter(
                'created_at',
                array(
                    'from' => $date->addDay(-1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),
                    'to'   => $date->addDay(1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
                )
            )->addAttributeToSelect('*')
                ->addAttributeToFilter('status', array('in' => $orderStatus))
            ;
            $date->addDay(-1);
            $shiftedDate->addDay(-1);
            $revenue[$i]['revenue'] = 0;
            $revenue[$i]['date'] = $shiftedDate->toString(Varien_Date::DATE_INTERNAL_FORMAT);
            foreach($orders as $order){
                $revenue[$i]['revenue'] += $order->getTotalItemCount();
                $thisMonthAvgList[] = $order->getTotalItemCount();
            }
            if (count($orders) > 0) {
                $revenue[$i]['revenue'] = Mage::helper('awonpulse')->getPriceFormat(
                    $revenue[$i]['revenue']/count($orders)
                );
            }
        }
        $thisMonthAvg = 0;
        if (count($thisMonthAvgList) > 0) {
            $thisMonthAvg = array_sum($thisMonthAvgList) / count($thisMonthAvgList);
        }
        $thisMonth = array();
        $thisMonth['thisMonthAvg'] = Mage::helper('awonpulse')->getPriceFormat($thisMonthAvg);
        return array('revenue' => $revenue, 'thisMonth' => $thisMonth);
    }

    /**
     * @param Zend_Date $date
     *
     * @return array
     */
    private function _getAverageOrderValue(Zend_Date $date)
    {
        $orderStatus = Mage::getStoreConfig(
            'awonpulse/general/ordersstatus', Mage::app()->getDefaultStoreView()->getId()
        );
        $orderStatus = explode(',', $orderStatus);
        if (count($orderStatus)==0){
            $orderStatus = array(Mage_Sales_Model_Order::STATE_COMPLETE);
        }
        $shiftedDate = $this->_getShiftedDate();
        $date->addDay(1);
        $shiftedDate->addDay(1);
        $copyDate = clone $date;
        $numberDaysInMonth = $copyDate->get(Zend_Date::MONTH_DAYS);
        $revenue = array();
        $thisMonthAvgList = array();
        for ($i = 0; $i < 15; $i++) {
            /** @var $yesterdayOrders Mage_Sales_Model_Resource_Order_Collection */
            $orders = Mage::getModel('sales/order')->getCollection();
            $orders->addAttributeToFilter(
                'created_at',
                array(
                    'from' => $date->addDay(-1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),
                    'to'   => $date->addDay(1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
                )
            )->addAttributeToSelect('*')
                ->addAttributeToFilter('status', array('in' => $orderStatus))
            ;
            $date->addDay(-1);
            $shiftedDate->addDay(-1);
            $revenue[$i]['revenue']=0;
            $revenue[$i]['date']=$shiftedDate->toString(Varien_Date::DATE_INTERNAL_FORMAT);
            if($orders->getSize() > 0) {
                foreach($orders as $order){
                    $revenue[$i]['revenue'] += $order->getBaseGrandTotal();
                }
                $revenue[$i]['revenue'] /= $orders->getSize();
            }
            $thisMonthAvgList[] = $revenue[$i]['revenue'];
        }

        $thisMonthAvg = 0;
        if (count($thisMonthAvgList) > 0) {
            $thisMonthAvg = array_sum($thisMonthAvgList) / count($thisMonthAvgList);
        }
        $thisMonth = array();
        $thisMonth['thisMonthAvg'] = Mage::helper('awonpulse')->getPriceFormat($thisMonthAvg);
        return array('revenue' => $revenue, 'thisMonth' => $thisMonth);
    }

    /**
     * @param Zend_Date $date
     *
     * @return array
     */
    private function _getSalesByCountry(Zend_Date $date)
    {
        $orderStatus = explode(
            ',', Mage::getStoreConfig('awonpulse/general/ordersstatus', Mage::app()->getDefaultStoreView()->getId())
        );
        if (count($orderStatus) == 0) {
            $orderStatus = array(Mage_Sales_Model_Order::STATE_COMPLETE);
        }
        /** @var  $orders Mage_Sales_Model_Resource_Order_Collection */
        $orderCollection = Mage::getResourceModel('sales/order_collection');
        $orderCollection
            ->addAttributeToFilter(
                'created_at', array('from' => $date->addDay(-15)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT))
            )
            ->addAttributeToFilter('status', array('in' => $orderStatus))
        ;
        //join billing address to order
        $billingAliasName = 'billing_o_a';
        $orderCollection->getSelect()->joinLeft(
            array($billingAliasName => $orderCollection->getTable('sales/order_address')),
            "(main_table.entity_id = {$billingAliasName}.parent_id"
            . " AND {$billingAliasName}.address_type = 'billing')",
            array(
                'country_id' => $billingAliasName . '.country_id',
            )
        );

        $countryOptionArray = Mage::helper('directory')->getCountryCollection()->toOptionArray(false);
        $countryOptionHash = array();
        foreach ($countryOptionArray as $option) {
            $countryOptionHash[$option['value']] = $option['label'];
        }
        $result = array();
        foreach ($orderCollection as $order) {
            /** @var $order Mage_Sales_Model_Order */
            $countryCode = $order->getData('country_id');
            $item = array(
                'country_label' => $countryOptionHash[$countryCode],
                'qty'           => 1,
                'amount'        => Mage::helper('awonpulse')->getPriceFormat($order->getBaseGrandTotal())
            );
            if (array_key_exists($countryCode, $result)) {
                $item['qty'] += $result[$countryCode]['qty'];
                $item['amount'] += $result[$countryCode]['amount'];
            }
            $result[$countryCode] = $item;
        }

        if(count($result) > 0) {
            $name = array();
            $qty = array();
            foreach ($result as $key => $row) {
                $name[$key]  = $row['country_label'];
                $qty[$key] = $row['qty'];
            }
            array_multisort($qty, SORT_DESC, $name, SORT_ASC, $result);
        }
        return array_values($result);
    }

    /**
     * @param Zend_Date $date
     *
     * @return array
     */
    private function _getSignups(Zend_Date $date)
    {
        $shiftedDate = $this->_getShiftedDate();
        $date->addDay(1);
        $shiftedDate->addDay(1);
        $copyDate = clone $date;
        $numberDaysInMonth = $copyDate->get(Zend_Date::MONTH_DAYS);
        $data = array();
        $thisMonthAvgList = array();
        for ($i = 0; $i < 15; $i++) {
            $customers = Mage::getModel('customer/customer')->getCollection();
            $customers->addAttributeToFilter(
                'created_at',
                array(
                    'from' => $date->addDay(-1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),
                    'to'   => $date->addDay(1)->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
                )
            )->addAttributeToSelect('*');
            $date->addDay(-1);
            $shiftedDate->addDay(-1);
            $data[$i]['data'] = $customers->getSize();
            $data[$i]['date'] = $shiftedDate->toString(Varien_Date::DATE_INTERNAL_FORMAT);
            $thisMonthAvgList[] = $data[$i]['data'];
        }

        $thisMonthAvg = array_sum($thisMonthAvgList) / count($thisMonthAvgList);
        $thisMonth = array();
        $thisMonth['thisMonthAvg'] = Mage::helper('awonpulse')->getPriceFormat($thisMonthAvg);
        return array('data' => $data, 'thisMonth' => $thisMonth);
    }

    /**
     * @param array $data
     *
     * @return float
     */
    private function _getMedianFromArray($data)
    {
        if (count($data) === 0) {
            return 0;
        }
        sort($data);
        $dataCount = count($data);
        $middleValue = (int)floor($dataCount / 2);
        if ($dataCount % 2 === 1) {
            return floatval($data[$middleValue]);
        }
        return ($data[$middleValue - 1] + $data[$middleValue]) / 2;
    }
}
