<?php

class My_Reports_Block_Adminhtml_Report_Grid
    extends Mage_Adminhtml_Block_Widget_Grid
{
    // add vars used by our methods

    /**
     * Grouped class name of used collection by this grid
     * @var string
     */
    protected $_resourceCollectionName      = 'my_reports/report_collection';

    /**
     * List of columns to aggregate by
     * @var array
     */
    protected $_aggregatedColumns;

    /**
     * Basic setup of our grid
     */
    public function __construct()
    {
        parent::__construct();

        // change behaviour of grid. This time we won't use pager and ajax functions
        $this->setPagerVisibility(false);
        $this->setUseAjax(false);
        $this->setFilterVisibility(false);

        // set message for empty result
        $this->setEmptyCellLabel(Mage::helper('my_reports')->__('No records found.'));

        // set grid ID in adminhtml
        $this->setId('mxReportsGrid');

        // set our grid to obtain totals
        $this->setCountTotals(true);
    }

    // add getters

    /**
     * Returns the resource collection name which we'll apply filters and display results
     * @return string
     */
    public function getResourceCollectionName()
    {
        return $this->_resourceCollectionName;
    }

    /**
     * Factory method for our resource collection
     * @return Mage_Core_Model_Mysql4_Collection_Abstract
     */
    public function getResourceCollection()
    {
        $resourceCollection = Mage::getResourceModel($this->getResourceCollectionName());
        return $resourceCollection;
    }

    /**
     * Gets the actual used currency code.
     * We will convert every currency value to this currency.
     * @return string
     */
    public function getCurrentCurrencyCode()
    {
        return Mage::app()->getStore()->getBaseCurrencyCode();
    }

    /**
     * Get currency rate, base to given currency
     * @param string|Mage_Directory_Model_Currency $toCurrency currency code
     * @return int
     */
    public function getRate($toCurrency)
    {
        return Mage::app()->getStore()->getBaseCurrency()->getRate($toCurrency);
    }

    /**
     * Return totals data
     * Count totals if it's not previously counted and set to retrieve
     * @return Varien_Object
     */
    public function getTotals()
    {
        $result                 = parent::getTotals();
        if (!$result && $this->getCountTotals()) {
            $filterData         = $this->getFilterData();
            $totalsCollection   = $this->getResourceCollection();
            
            // apply our custom filters on collection
            $this->_addCustomFilter(
                $totalsCollection,
                $filterData
            );

            // isTotals is a flag, we will deal with this in the resource collection
            $totalsCollection->isTotals(true);

            // set totals row even if we didn't got a result
            if ($totalsCollection->count() < 1) {
                $this->setTotals(new Varien_Object);
            } else {
                $this->setTotals($totalsCollection->getFirstItem());
            }

            $result             = parent::getTotals();
        }

        return $result;
    }

    // prepare columns and collection

    /**
     * Prepare our grid's columns to display
     * @return My_Reports_Block_Adminhtml_Grid
     */
    protected function _prepareColumns()
    {
        // get currency code and currency rate for the currency renderers.
        // our orders could be in different currencies, therefore we should convert the values to the base currency
        $currencyCode           = $this->getCurrentCurrencyCode();
        $rate                   = $this->getRate($currencyCode);

        // add our first column, period which represents a date
        $this->addColumn('period', array(
            'header'            => Mage::helper('my_reports')->__('Period'),
            'index'             => 'created_at', // 'index' attaches a column from the SQL result set to the grid
            'renderer'          => 'adminhtml/report_sales_grid_column_renderer_date',
            'width'             => 100,
            'sortable'          => false,
            'period_type'       => $this->getFilterData()->getPeriodType() // could be day, month or year
        ));

        // add base grand total w/ a currency renderer, and add totals
        $this->addColumn('base_grand_total', array(
            'header'            => Mage::helper('my_reports')->__('Grand Total'),
            'index'             => 'base_grand_total',
            // type defines a grid column renderer; you could find the complete list 
            // and the exact aliases at Mage_Adminhtml_Block_Widget_Grid_Column::_getRendererByType()
            'type'              => 'currency',
            'currency_code'     => $currencyCode, // set currency code..
            'rate'              => $rate, // and currency rate, used by the column renderer
            'total'             => 'sum'
        ));

        // add the next column shipping_amount, with an average on totals
        $this->addColumn('base_shipping_amount', array(
            'header'            => Mage::helper('my_reports')->__('Shipping Amount'),
            'index'             => 'base_shipping_amount',
            'type'              => 'currency',
            'currency_code'     => $currencyCode,
            'rate'              => $rate,
            'total'             => 'sum'
        ));

        // rate, where base_shipping_amount/base_grand_total is a percent
        $this->addColumn('shipping_rate', array(
            'header'            => Mage::helper('my_reports')->__('Shipping Rate'),
            'index'             => 'shipping_rate',
            'renderer'          => 'my_reports/adminhtml_report_grid_column_renderer_percent',
            'decimals'          => 2,
            'total'             => 'avg'
        ));

        // add export types
        $this->addExportType('*/*/exportCsv', Mage::helper('my_reports')->__('CSV'));
        $this->addExportType('*/*/exportExcel', Mage::helper('my_reports')->__('MS Excel XML'));

        return parent::_prepareColumns();
    }

    /**
     * Prepare our collection which we'll display in the grid
     * First, get the resource collection we're dealing with, with our custom filters applied.
     * In case of an export, we're done, otherwise calculate the totals
     * @return My_Reports_Block_Adminhtml_Grid
     */
    protected function _prepareCollection()
    {
        $filterData             = $this->getFilterData();
        $resourceCollection     = $this->getResourceCollection();

        // get our resource collection and apply our filters on it
        $this->_addCustomFilter(
            $resourceCollection,
            $filterData
        );

        // attach the prepared collection to our grid
        $this->setCollection($resourceCollection);

        // skip totals if we do an export (calling getTotals would be a duplicate, because
        // the export method calls it explicitly)
        if ($this->_isExport) {
            return $this;
        }

        // count totals if needed
        if ($this->getCountTotals()) {
            $this->getTotals();
        }

        return parent::_prepareCollection();
    }

    /**
     * Apply our custom filters on collection
     * @param Mage_Core_Model_Mysql4_Collection_Abstract $collection
     * @param Varien_Object $filterData
     * @return My_Reports_Block_Adminhtml_Report_Grid
     */
    protected function _addCustomFilter($collection, $filterData)
    {
        $collection
            ->setPeriodType($filterData->getPeriodType())
            ->setDateRange($filterData->getFrom(), $filterData->getTo())
            ->isShippingRateNonZeroOnly($filterData->getShippingRate() ? true : false)
            ->setAggregatedColumns($this->_getAggregatedColumns());

        return $this;
    }

    /**
     * Returns the columns we specified to summarize totals
     * 
     * Collect all columns we added totals to. 
     * The returned array would be ie. 'base_grand_total' => 'sum'
     * @return array
     */
    protected function _getAggregatedColumns()
    {
        if (!isset($this->_aggregatedColumns) && $this->getColumns()) {
            $this->_aggregatedColumns = array();
            foreach ($this->getColumns() as $column) {
                if ($column->hasTotal()) {
                    $this->_aggregatedColumns[$column->getId()] = $column->getTotal();
                }
            }
        }

        return $this->_aggregatedColumns;
    }

}