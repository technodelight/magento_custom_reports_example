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