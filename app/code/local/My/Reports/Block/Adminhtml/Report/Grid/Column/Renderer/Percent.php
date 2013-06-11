<?php

class My_Reports_Block_Adminhtml_Report_Grid_Column_Renderer_Percent
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