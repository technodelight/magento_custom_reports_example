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