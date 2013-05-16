
 Custom Reports in Magento
 ~~~~~~~~~~~~~~~~~~~~~~~~

 Because I haven't found any detailed article about how to create a report and how it works, I decided to write
one myself, where I try to give you some details, not just a plain-code-figure-it-out-yourself stuff.
The example would be quite simple, but it just fits for an excersice: list the orders grand total and shipping
amount, and - to give this story a little twist - we would like to display how much percent was the shipping amount of the
order's total. We would like to display totals too under our grid.
 Acceptance criteria for our module:
 - Ability to filter in a given date interval
 - Ability to change date interval (days, months or years)
 - Ability to filter results with a non-zero shipping percent only
 - Ability to export to CSV and MS Excel
 In the example code I would like to use some best practices, and would like to follow the conventions mostly. I
created a public git repository, from where you could download the whole source code. If you are impatient, scroll to
the end of this article for the link.


 Shortly about Reports

 Basically it consists a grid, a collection and a form, where the form has fields with we can filter the displayed
results of the grid. The grid displays the collection's items with the applied filters. There is an enermous amount
of entry points which we could use to change datas during runtime, but we won't use many of them.


 Creating the base module

 We will create some blocks, models, helpers for our module, overwrite a controller, define a layout, then
place the whole into the admin area's menu system. We have to define models because we will use a collection,
it should have blocks to display the grid and the form, while the helper will handle the translations and it's
required because we make an admin module. We will place our files under the 'local' codepool, under the 'My' vendor
and the module's name would be 'Reports'.
 You can notice some difference while creating a module in the admin area compared to a frontend one. We will overwrite
the controller under the config's 'admin' node instead of adding a new frontname to the system, also applying the
layout updates would be in a different node named 'adminhtml'. You may wonder about why we won't place it
under the same node as the controller; this could be traced back to legacy reasons. This node is also the place
for admin menu configuration, but we separate it to a file named after this node (adminhtml.xml). This is
a feature by Magento, you could separate your module's configuration by the node names used. Usually we do
this with system.xml, adminhtml.xml and api.xml/api_v2.xml, depending on needs.


 Configuration files

 First of all, we will write our module enabler xml. Because we'll work in the 'local' codepool, we should
place all of our files under the 'app/code/local' directory.

app/etc/modules/My_Reports.xml

<?xml version="1.0"?>
<config>
    <modules>
        <My_Reports>
            <active>true</active>
            <version>0.1.0</version>
            <codePool>local</codePool>
        </My_Reports>
    </modules>
</config>

 In config.xml, we tell Magento's admin router to search the controller first in our module, before Mage_Adminhtml,
then add the layout update file for creating the report's user interface.

app/code/local/My/Reports/etc/config.xml

<?xml version="1.0"?>
<config>
    <modules>
        <My_Reports>
            <version>0.1.0</version>
            <depends>
                <Mage_Adminhtml />
                <Mage_Sales />
            </depends>
        </My_Reports>
    </modules>

    <global>
        <models>
            <my_reports>
                <class>My_Reports_Model</class>
                <resourceModel>my_reports_mysql4</resourceModel>
            </my_reports>
            <my_reports_mysql4>
                <class>My_Reports_Model_Mysql4</class>
            </my_reports_mysql4>
        </models>
        <helpers>
            <my_reports>
                <class>My_Reports_Helper</class>
            </my_reports>
        </helpers>
        <blocks>
            <my_reports>
                <class>My_Reports_Block</class>
            </my_reports>
        </blocks>
    </global>

    <admin>
        <routers>
            <adminhtml>
                <args>
                    <modules>
                        <My_Reports before="Mage_Adminhtml">My_Reports_Adminhtml</My_Reports>
                    </modules>
                </args>
            </adminhtml>
        </routers>
    </admin>

    <adminhtml>
        <layout>
            <updates>
                <my_reports>
                    <file>my_reports.xml</file>
                </my_reports>
            </updates>
        </layout>
    </adminhtml>

</config>

 After that, add our module to the admin menusystem under Report > Sales. Also, we defined some basic ACL rule too, which
allows every user to operate with our grid.

app/code/local/My/Reports/etc/adminhtml.xml

<?xml version="1.0"?>
<config>
    <menu>
        <report>
            <children>
                <salesroot translate="title">
                    <children>
                        <my_reports translate="title">
                            <title>My Custom Reports</title>
                            <action>adminhtml/my_reports</action>
                            <sort_order>100</sort_order>
                        </my_reports>
                    </children>
                </salesroot>
            </children>
        </report>
    </menu>
    <acl>
        <resources>
            <admin>
                <children>
                    <system>
                        <children>
                            <config>
                                <children>
                                    <my_reports>
                                        <title>My Reports Section</title>
                                    </my_reports>
                                </children>
                            </config>
                        </children>
                    </system>
                    <report>
                        <children>
                            <salesroot>
                                <children>
                                    <my_reports translate="title">
                                        <title>My Custom Reports</title>
                                        <children>
                                            <view translate="title">
                                                <title>View</title>
                                            </view>
                                        </children>
                                    </my_reports>
                                </children>
                            </salesroot>
                        </children>
                    </report>
                </children>
            </admin>
        </resources>
    </acl>
</config>

 To get a working admin module, we should create a helper class. Since we haven't got any logic which we should
share between blocks, controllers or models, we just inherit everything from Mage_Core_Helper_Abstract and leave the body empty.
 There is a convention to use the helper's translate method to hook translations through it, so let's follow it on our code!

app/code/local/My/Reports/Helper/Data.php

<?php

/**
 * Default helper for our Admin module
 * 
 * Hook for translations
 */
class My_Reports_Helper_Data 
    extends Mage_Core_Helper_Abstract
{
    
}


 Controller

 The controller's '_initAction()' and '_initReportAction()' methods could be familiar from the Mage_Adminhtml_Report_SalesController.
We will use this methods to pass the filter values from request in the 'indexAction'. The methods starting with 'export' shall
export our data to the appropriate formats. Luckily we shouldn't have to code the exportation logic ourself, it's already
implemented by the Magento Team (at least one thing less to do). Because the export is a part of the grid, we have the opportunity
to export anything what the grid could display.

app/code/local/My/Reports/controllers/Adminhtml/My/ReportsController.php

<?php

class My_Reports_Adminhtml_My_ReportsController
    extends Mage_Adminhtml_Controller_Action
{
    /**
     * Initialize titles and navigation breadcrumbs
     * @return My_Reports_Adminhtml_ReportsController
     */
    protected function _initAction()
    {
        $this->_title($this->__('Reports'))->_title($this->__('Sales'))->_title($this->__('My Custom Reports'));
        $this->loadLayout()
            ->_setActiveMenu('report/sales')
            ->_addBreadcrumb(Mage::helper('my_reports')->__('Reports'), Mage::helper('my_reports')->__('Reports'))
            ->_addBreadcrumb(Mage::helper('my_reports')->__('Sales'), Mage::helper('my_reports')->__('Sales'))
            ->_addBreadcrumb(Mage::helper('my_reports')->__('My Custom Reports'), Mage::helper('my_reports')->__('My Custom Reports'));
        return $this;
    }

    /**
     * Prepare blocks with request data from our filter form
     * @return My_Reports_Adminhtml_ReportsController
     */
    protected function _initReportAction($blocks)
    {
        if (!is_array($blocks)) {
            $blocks = array($blocks);
        }
 
        $requestData    = Mage::helper('adminhtml')->prepareFilterString($this->getRequest()->getParam('filter'));
        $requestData    = $this->_filterDates($requestData, array('from', 'to'));
        $params         = $this->_getDefaultFilterData();
        foreach ($requestData as $key => $value) {
            if (!empty($value)) {
                $params->setData($key, $value);
            }
        }
 
        foreach ($blocks as $block) {
            if ($block) {
                $block->setFilterData($params);
            }
        }
        return $this;
    }

    /**
     * Grid action
     */
    public function indexAction()
    {
        $this->_initAction();

        $gridBlock = $this->getLayout()->getBlock('adminhtml_report.grid');
        $filterFormBlock = $this->getLayout()->getBlock('grid.filter.form');
        $this->_initReportAction(array(
            $gridBlock,
            $filterFormBlock
        ));

        $this->renderLayout();
    }

    /**
     * Export reports to CSV file
     */
    public function exportCsvAction()
    {
        $fileName   = 'my_reports.csv';
        $grid       = $this->getLayout()->createBlock('my_reports/adminhtml_report_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse($fileName, $grid->getCsvFile());
    }

    /**
     * Export reports to Excel XML file
     */
    public function exportExcelAction()
    {
        $fileName   = 'my_reports.xml';
        $grid       = $this->getLayout()->createBlock('my_reports/adminhtml_report_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse($fileName, $grid->getExcelFile());
    }

    /**
     * Returns default filter data
     * @return Varien_Object
     */
    protected function _getDefaultFilterData()
    {
        return new Varien_Object(array(
            'from'      => date('Y-m-d G:i:s', strtotime('-1 month -1 day')),
            'to'        => date('Y-m-d G:i:s', strtotime('-1 day'))
        ));
    }
}


 Layout, Grid Container

 The 'indexAction' supplies our blocks with datas, therefore it's time to start creating them! Let's start right now with the 'layout.xml'.
As you could see, we will need a container block, which would be the place of the grid and the filter form. Probably you have already
noticed that nothing describes the grid block here, but don't worry, the container should add it later, dynamically.

app/design/adminhtml/default/default/layout/my_reports.xml

<?xml version="1.0"?>
<layout version="0.1.0">
    <adminhtml_my_reports_index>
        <reference name="content">
            <block type="my_reports/adminhtml_report" template="report/grid/container.phtml" name="my_reports_report_grid_container">
                <block type="my_reports/adminhtml_filter_form" name="grid.filter.form" />
            </block>
        </reference>
    </adminhtml_my_reports_index>
</layout>

 Let's go on with the container. This block should build the the grid block in it's parent class' '_prepareLayout()' method in
the following way: {blockGroup}/{controller}_grid. The {blockGroup} is the block alias ('my_reports'), which we already defined in our
'config.xml' under the blocks node, and the {controller} is this block's identifier ('adminhtml_report'). The grid block's name
would be 'my_reports/adminhtml_report_grid' then.

app/code/local/My/Reports/Block/Adminhtml/Report.php

<?php

class My_Reports_Block_Adminhtml_Report
    extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * This is your module alias
     */
    protected $_blockGroup      = 'my_reports';
    /**
     * This is the controller's name (this block)
     */
    protected $_controller      = 'adminhtml_report';

    /*
        Note: the grid block's name would prepare from $_blockGroup and $_controller with the suffix '_grid'.
        So the complete block would called my_reports/adminhtml_report_grid . As you already guessed it,
        this will resolve to the class My_Reports_Adminhtml_Report_Grid .
     */

    /**
     * Prepare grid container, add and remove additional buttons
     */
    public function __construct()
    {
        // The head title of the grid
        $this->_headerText = Mage::helper('my_reports')->__('My Custom Reports');
        // Set hard-coded template. As you can see, the layout.xml 
        // attribute is ineffective, but we keep up with conventions
        $this->setTemplate('report/grid/container.phtml');
        // call parent constructor and let it add the buttons
        parent::__construct();
        // we create a report, not just a standard grid, so remove add button, we don't need it this time
        $this->_removeButton('add');

        // add a button to our form to let the user kick-off our logic from the admin
        $this->addButton('filter_form_submit', array(
            'label'     => Mage::helper('my_reports')->__('Show Report'),
            'onclick'   => 'filterFormSubmit()'
        ));
    }

    /**
     * This function will prepare our filter URL
     * @return string
     */
    public function getFilterUrl()
    {
        $this->getRequest()->setParam('filter', null);
        return $this->getUrl('*/*/index', array('_current' => true));
    }
}


 Grid

 The grid connects our backend data and the logic in templates to display everything on the frontend, so it's a 
bit of both worlds.
The original sales report grid contains an abstract and a concrete class implementation, but for the purpose
of easy understanding what we do, we will place everything to only one class.
 The code which deals with displaying datas on the user interface should be prepared in the '_prepareColumns'. Using
the 'type' key you can choose one column renderer from the bundled ones (you could find the full list of the renderers
at 'Mage_Adminhtml_Block_Widget_Grid_Column::_getRendererByType()'). However, there isn't one which could handle
the percent values, therefore we should create one by ourself. The 'index' would attach the SQL result's column
to the column renderer (you should define the 'alias' here as you defined it in your query in the resource model,
for example you could see how we specified the 'shipping_rate' column).
 The method which deals with supplying datas from the backend is '_prepareCollection()'. Here we pass the values
from the filters to the collection within the '_addCustomFilter()'.

app/code/local/My/Reports/Block/Adminhtml/Report/Grid.php

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

 We don't have a renderer to display the percent values yet, that's why we would create one now. Because
every column object inherits from 'Varien_Object', you could pass any value to your column renderer in the
grid's '_prepareColumns()' method. We will create our renderer with using this capability, but because we
should have default values, we should wrap the getters within our own methods.
 If you'd like to display the value differently in an export, you have to overwrite the 'renderExport()'
method (by default it returns with the 'render()' method's result).
 Also, it's worth to mention that there are two column block types, the one which we would like to create
now, and an other one which deals with inline filtering on values, placed on the top of the grid (we turned 
it off this time, see 'setFilterVisibility' in the grid class). If you are interested, you could find everything
in 'Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Abstract'.

app/code/local/My/Reports/Block/Adminhtml/Report/Grid/Column/Renderer/Percent.php

<?php

class My_Reports_Block_Adminhtml_Report_Column_Renderer_Percent
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    // default (fallback) values, if not specified from outside

    /**
     * Default value for rounding value by
     * @var int
     */
    const DECIMALS                  = 2;

    /**
     * Percent sign, appended to the value
     */
    const PERCENT_SIGN              = '%';

    // render the field

    /**
     * Renders grid column
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $value          = $this->_getValue($row);
        $decimals       = $this->_getDecimals();
        return number_format($value, $decimals) . self::PERCENT_SIGN;
    }

    // add getter for decimals

    /*
      Note: as many objects in Magento, also the renderers inherit methods from Varien_Object
      (actually the renderer is a block, and all block inherits from Varien_Object).
      Therefore we could pass any value to this block using Varien_Object's methods.
      For example $renderer->setAnything(1) will set the 'anything''s value to 1. In our case
      we pass the decimals with a value of 2 when we add the 'shipping_rate' column to the grid
      (because the default is 2 this is not necessary, but the code is easier to understand 
      and read this way).
      See: Varien_Object (especially ::__call), My_Reports_Block_Adminhtml_Report_Grid::_prepareColumns().
     */

    /**
     * Get decimal to round value by
     * The decimals value could be changed with specifying it from outside using
     * a setter method supported by Varien_Object (ie. with setData('decimals', 2) or setDecimals(2))
     * @return int
     */
    protected function _getDecimals()
    {
        $decimals       = $this->getDecimals(); // this is a magic getter
        return !is_null($decimals) ? $decimals : self::DECIMALS;
    }

}


 Form

 We are already done with almost everything in our layout, except the filter form.
 This is a block which wraps the 'Varien_Data_form' with a template ('widget/grid.phtml'). We will
create a fieldset and place our form elements in it, and put the options for the select elements
to protected getters. We may have to modify the fields in runtime from outside the class, therefore we 
will add functionality to achieve this behaviour.

app/code/local/My/Reports/Block/Adminhtml/Filter/Form.php

<?php

class My_Reports_Block_Adminhtml_Filter_Form
    extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * This will contain our form element's visibility
     * @var array
     */
    protected $_fieldVisibility             = array();

    /**
     * Field options
     * @var array
     */
    protected $_fieldOptions                = array();

    /**
     * Sets a form element to be visible or not
     * @param string $fieldId
     * @param bool $visibility
     * @return My_Reports_Block_Adminhtml_Filter_Form
     */
    public function setFieldVisibility($fieldId, $visibility)
    {
        $this->_fieldVisibility[$fieldId] = $visibility ? true : false;
        return $this;
    }

    /**
     * Returns the field is visible or not. If we hadn't set a value
     * for the field previously, it will return the value defined in the
     * defaultVisibility parameter (it's true by default)
     * @param string $fieldId
     * @param bool $defaultVisibility
     * @return bool
     */
    public function getFieldVisibility($fieldId, $defaultVisibility = true)
    {
        if (isset($this->_fieldVisibility[$fieldId])) {
            return $this->_fieldVisibility[$fieldId];
        }
        return $defaultVisibility;
    }

    /**
     * Set field option(s)
     * @param string $fieldId
     * @param string|array $option if option is an array, loop through it's keys and values
     * @param mixed $value if option is an array this option is meaningless
     * @return My_Reports_Block_Adminhtml_Filter_Form
     */
    public function setFieldOption($fieldId, $option, $value = null)
    {
        if (is_array($option)) {
            $options    = $option;
        } else {
            $options    = array($option => $value);
        }

        if (!isset($this->_fieldOptions[$fieldId])) {
            $this->_fieldOptions[$fieldId] = array();
        }

        foreach ($options as $key => $value) {
            $this->_fieldOptions[$fieldId][$key] = $value;
        }

        return $this;
    }

    /**
     * Prepare our form elements
     * @return My_Reports_Block_Adminhtml_Filter_Form
     */
    protected function _prepareForm()
    {
        // inicialise our form
        $actionUrl      = $this->getCurrentUrl();
        $form           = new Varien_Data_Form(array(
            'id'        => 'filter_form',
            'action'    => $actionUrl, 
            'method'    => 'get'
        ));

        // set ID prefix for all elements in our form
        $htmlIdPrefix   = 'my_reports_';
        $form->setHtmlIdPrefix($htmlIdPrefix);

        // create a fieldset to add elements to
        $fieldset       = $form->addFieldset('base_fieldset', array('legend' => Mage::helper('my_reports')->__('Filter')));

        // prepare our filter fields and add each to the fieldset

        // date filter
        $dateFormatIso  = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
        $fieldset->addField('from', 'date', array(
            'name'      => 'from',
            'format'    => $dateFormatIso,
            'image'     => $this->getSkinUrl('images/grid-cal.gif'),
            'label'     => Mage::helper('my_reports')->__('From'),
            'title'     => Mage::helper('my_reports')->__('From')
        ));
        $fieldset->addField('to', 'date', array(
            'name'      => 'to',
            'format'    => $dateFormatIso,
            'image'     => $this->getSkinUrl('images/grid-cal.gif'),
            'label'     => Mage::helper('my_reports')->__('To'),
            'title'     => Mage::helper('my_reports')->__('To')
        ));
        $fieldset->addField('period_type', 'select', array(
            'name'      => 'period_type',
            'options'   => $this->_getPeriodTypeOptions(),
            'label'     => Mage::helper('my_reports')->__('Period')
        ));

        // non-zero shipping rate filter
        $fieldset->addField('shipping_rate', 'select', array(
            'name'      => 'shipping_rate',
            'options'   => $this->_getShippingRateSelectOptions(),
            'label'     => Mage::helper('my_reports')->__('Show values where shipping rate greater than 0')
        ));

        $form->setUseContainer(true);
        $this->setForm($form);

        return $this;
    }

    /**
     * Get period type options
     * @return array
     */
    protected function _getPeriodTypeOptions()
    {
        $options        = array(
            'day'       => Mage::helper('my_reports')->__('Day'),
            'month'     => Mage::helper('my_reports')->__('Month'),
            'year'      => Mage::helper('my_reports')->__('Year'),
        );

        return $options;
    }

    /**
     * Returns options for shipping rate select
     * @return array
     */
    protected function _getShippingRateSelectOptions()
    {
        $options        = array(
            '0'         => 'Any',
            '1'         => 'Specified'
        );

        return $options;
    }

    /**
     * Inicialise form values
     * Called after prepareForm, we apply the previously set values from filter on the form
     * @return My_Reports_Block_Adminhtml_Filter_Form
     */
    protected function _initFormValues()
    {
        $filterData     = $this->getFilterData();
        $this->getForm()->addValues($filterData->getData());
        return parent::_initFormValues();
    }

    /**
     * Apply field visibility and field options on our form fields before rendering
     * @return My_Reports_Block_Adminhtml_Filter_Form
     */
    protected function _beforeHtml()
    {
        $result         = parent::_beforeHtml();

        $elements       = $this->getForm()->getElements();

        // iterate on our elements and select fieldsets
        foreach ($elements as $element) {
            $this->_applyFieldVisibiltyAndOptions($element);
        }

        return $result;
    }

    /**
     * Apply field visibility and options on fieldset element
     * Recursive
     * @param Varien_Data_Form_Element_Fieldset $element
     * @return Varien_Data_Form_Element_Fieldset
     */
    protected function _applyFieldVisibiltyAndOptions($element) {
        if ($element instanceof Varien_Data_Form_Element_Fieldset) {
            foreach ($element->getElements() as $fieldElement) {
                // apply recursively
                if ($fieldElement instanceof Varien_Data_Form_Element_Fieldset) {
                    $this->_applyFieldVisibiltyAndOptions($fieldElement);
                    continue;
                }

                $fieldId = $fieldElement->getId();
                // apply field visibility
                if (!$this->getFieldVisibility($fieldId)) {
                    $element->removeField($fieldId);
                    continue;
                }

                // apply field options
                if (isset($this->_fieldOptions[$fieldId])) {
                    $fieldOptions = $this->_fieldOptions[$fieldId];
                    foreach ($fieldOptions as $k => $v) {
                        $fieldElement->setDataUsingMethod($k, $v);
                    }
                }
            }
        }

        return $element;
    }

}


 Collection

 Finally arrived to the point when we will code our last class: the collection. This will
collect our datas which we would like to display in the grid rows. We should have to write some getters,
those ones which we already referenced to in the '_addCustomFilter()'. The SQL query building starts
in the '_initSelect()'. This is originally called from the parent class' constructor, but this
isn't fits for us this case; because the 'isTotals' flag is given after the object has been
instantiated, we will move the select initialisation to '_beforeLoad()'.
 We shoould define the displayed columns in '_getSelectedColumns()', based on the 'isTotals' flag's
value. The '_getAggregatedColumns()' builds the SQL query's columns part in totals mode. In the
original Sales Report the aggregated columns are prepared in the grid in this format: 
'columnId' => '{$total}({$columnId})', but I think building queries are the resource model's
responsibility; therefore I choosed a different realisation (take a look at '_getAggregatedColumn()').
 If you'd like to debug and see the actual queries, overwrite the 'load()' method. The method's two
parameters explains the functionality behind them. For a little hint you could take a look
at 'Varien_Data_Collection_Db::printLogQuery()'.

app/code/local/My/Reports/Model/Mysql4/Report/Collection.php

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


 Final words

 As you could see, it's not rocket science to create a report. However it could be scary at first, but
I hope I could give you a better overview of the process. Send me a beer if I was able to help you :)
Comments and opinions are more than welcome.
 The module is available on GitHub: https://github.com/technodelight/magento_custom_reports_example
