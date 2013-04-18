<?php

class My_Reports_Model_Mysql4_Report_Collection
    extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    // vars containing our filters' data

    /**
     * Period type to group results by
     * Could be day, month or year
     * @var string
     */
    protected $_periodType;

    /**
     * 'From Date' filter
     * @var string
     */
    protected $_from;

    /**
     * 'To Date' filter
     * @var string
     */
    protected $_to;

    /**
     * Filter only results where shipping rate is greater than zero
     * @var bool
     */
    protected $_isShippingRateNonZeroOnly       = false;

    /**
     * Count totals (aggregated columns) only
     * @var bool
     */
    protected $_isTotals                        = false;

    /**
     * Aggregated columns to count totals
     * In the format of: 'columnId' => 'total'
     * @var array
     */
    protected $_aggregatedColumns               = array();

    // define basic setup of our collection

    /**
     * We should overwrite constructor to allow custom resources to use
     * The original constructor calls _initSelect by default which isn't suits our 
     * needs, because the totals mode is set after instantiation of
     * the collection object (therefore we will handle this case right before 
     * loading our collection).
     */
    public function __construct($resource = null)
    {
        $this->setModel('adminhtml/report_item');
        $this->setResourceModel('sales/order');
        $this->setConnection($this->getResource()->getReadConnection());
    }

    // add filter methods

    /**
     * Set period type
     * @param string $periodType
     * @return My_Reports_Model_Mysql4_Report_Collection
     */
    public function setPeriodType($periodType)
    {
        $this->_periodType = $periodType;
        return $this;
    }

    /**
     * Set date range to filter on
     * @param string $from
     * @param string $to
     * @return My_Reports_Model_Mysql4_Report_Collection
     */
    public function setDateRange($from, $to)
    {
        $this->_from = $from;
        $this->_to = $to;
        return $this;
    }

    /**
     * Setter/getter method for filtering items only with shipping rate greater than zero
     * @param bool $bool by default null it returns the current state flag
     * @return bool|My_Reports_Model_Mysql4_Report_Collection
     */
    public function isShippingRateNonZeroOnly($bool = null)
    {
        if (is_null($bool)) {
            return $this->_isShippingRateNonZeroOnly;
        }
        $this->_isShippingRateNonZeroOnly = $bool ? true : false;
        return $this;
    }

    /**
     * Set aggregated columns used in totals mode
     * @param array $columns
     * @return My_Reports_Model_Mysql4_Report_Collection
     */
    public function setAggregatedColumns($columns)
    {
        $this->_aggregatedColumns = $columns;
        return $this;
    }

    /**
     * Setter/getter for setting totals mode on collection
     * By default the collection selects columns we display in the grid,
     * by selecting this mode we will only query the aggregated columns
     * @param bool $bool by default null it returns the current state of flag
     * @return bool|My_Reports_Model_Mysql4_Report_Collection
     */
    public function isTotals($bool = null)
    {
        if (is_null($bool)) {
            return $this->_isTotals;
        }
        $this->_isTotals = $bool ? true : false;
        return $this;
    }

    // prepare select

    /**
     * Get selected columns depending on totals mode
     */
    protected function _getSelectedColumns() {
        if ($this->isTotals()) {
            $selectedColumns            = $this->_getAggregatedColumns();
        } else {
            $selectedColumns            = array(
                'created_at'            => $this->_getPeriodFormat(),
                'base_grand_total'      => 'SUM(base_grand_total)',
                'base_shipping_amount'  => 'SUM(base_shipping_amount)',
                'shipping_rate'         => 'AVG((base_shipping_amount / base_grand_total) * 100)',
                'base_currency_code'    => 'base_currency_code',
            );
        }

        return $selectedColumns;
    }

    /**
     * Return aggregated columns
     * This method uses ::_getAggregatedColumn for getting the db expression for the specified columnId
     * @return array
     */
    protected function _getAggregatedColumns()
    {
        $aggregatedColumns          = array();
        foreach ($this->_aggregatedColumns as $columnId => $total) {
            $aggregatedColumns[$columnId] = $this->_getAggregatedColumn($columnId, $total);
        }
        return $aggregatedColumns;
    }

    /**
     * Returns the db expression based on total mode and column ID
     * @param string $columnId the column's ID used in expression
     * @param string $total mode of aggregation (could be sum or avg)
     * @return string
     */
    protected function _getAggregatedColumn($columnId, $total)
    {
        switch ($columnId) {
            case 'shipping_rate' : {
                $expression         = "{$total}((base_shipping_amount / base_grand_total) * 100)";
            } break;
            default : {
                $expression         = "{$total}({$columnId})";
            } break;
        }

        return $expression;
    }

    /**
     * Get period format based on '_periodType'
     * @return string
     */
    protected function _getPeriodFormat()
    {
        $adapter = $this->getConnection();
        if ('month' == $this->_periodType) {
            $periodFormat = 'DATE_FORMAT(created_at, \'%Y-%m\')';
            // From Magento EE 1.12 you should use the adapter's appropriate method:
            // $periodFormat = $adapter->getDateFormatSql('created_at', '%Y-%m');
        } else if ('year' == $this->_periodType) {
            $periodFormat = 'EXTRACT(YEAR FROM created_at)';
            // From Magento EE 1.12 you should use the adapter's appropriate method:
            // $periodFormat = $adapter->getDateExtractSql('created_at', Varien_Db_Adapter_Interface::INTERVAL_YEAR);
        } else {
            $periodFormat = 'created_at';
            // From Magento EE 1.12 you should use the adapter's appropriate method:
            // $periodFormat = $adapter->getDateFormatSql('created_at', '%Y-%m-%d');
        }

        return $periodFormat;
    }

    /**
     * Prepare select statement depending on totals is on or off
     * @return My_Reports_Model_Mysql4_Report_Collection
     */
    protected function _initSelect()
    {
        $this->getSelect()->reset();

        // select aggregated columns only in totals; w/o grouping by period
        $this->getSelect()->from($this->getResource()->getMainTable(), $this->_getSelectedColumns());
        if (!$this->isTotals()) {
            $this->getSelect()->group($this->_getPeriodFormat());
        }

        return $this;
    }

    // render filters

    /**
     * Apply our date range filter on select
     * @return My_Reports_Model_Mysql4_Report_Collection
     */
    protected function _applyDateRangeFilter()
    {
        if (!is_null($this->_from)) {
            $this->_from = date('Y-m-d G:i:s', strtotime($this->_from));
            $this->getSelect()->where('created_at >= ?', $this->_from);
        }
        if (!is_null($this->_to)) {
            $this->_to = date('Y-m-d G:i:s', strtotime($this->_to));
            $this->getSelect()->where('created_at <= ?', $this->_to);
        }

        return $this;
    }

    /**
     * Apply shipping rate filter
     * @return My_Reports_Model_Mysql4_Report_Collection
     */
    protected function _applyShippingRateNonZeroOnlyFilter()
    {
        if ($this->_isShippingRateNonZeroOnly) {
            $this->getSelect()
                ->where('((base_shipping_amount / base_grand_total) * 100) > 0');
        }
    }

    /**
     * Inicialise select right before loading collection
     * We need to fire _initSelect here, because the isTotals mode creates different results depending
     * on it's value. The parent implementation of the collection originally fires this method in the
     * constructor.
     * @return My_Reports_Model_Mysql4_Report_Collection
     */
    protected function _beforeLoad()
    {
        $this->_initSelect();
        return parent::_beforeLoad();
    }

    /**
     * This would render all of our pre-set filters on collection.
     * Calling of this method happens in Varien_Data_Collection_Db::_renderFilters(), while
     * the _renderFilters itself is called in Varien_Data_Collection_Db::load() before calling
     * _renderOrders() and _renderLimit() .
     * @return My_Reports_Model_Mysql4_Report_Collection
     */
    protected function _renderFiltersBefore()
    {
        $this
            ->_applyDateRangeFilter()
            ->_applyShippingRateNonZeroOnlyFilter();
        return $this;
    }

}