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
     * The decimals and the percent sign could be changed
     * with specifying it from outside
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $value          = $this->_getValue($row);
        $decimals       = $this->_getDecimals();
        $percentSign    = $this->_getPercentSign();
        return number_format($value, $decimals) . $percentSign;
    }

    // add getters for decimals and percent sign

    /**
     * Get decimal value to round by
     * @return int
     */
    protected function _getDecimals()
    {
        $decimals       = $this->getDecimals();
        return !is_null($decimals) ? $decimals : self::DECIMALS;
    }

    /**
     * Gets the percent sign appended to the value
     * @return string
     */
    protected function _getPercentSign()
    {
        $percentSign    = $this->getPercentSign();
        return !is_null($percentSign) ? $percentSign : self::PERCENT_SIGN;
    }
}