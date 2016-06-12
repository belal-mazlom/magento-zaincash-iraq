<?php

/**
 * Created by PhpStorm.
 * User: belalmazlom
 * Date: 6/10/16
 * Time: 4:08 PM
 */
class Shopgo_ZainIraq_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'zainiraq';
    protected $_isInitializeNeeded = true;
    protected $_canUseInternal = false;
    protected $_canUseForMultishipping = false;

    public function validate()
    {
        /**
         * to validate payment method is allowed for billing country or not
         */
        $paymentInfo = $this->getInfoInstance();
        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
            $billingCountry = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
        } else {
            $billingCountry = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
        }
        if (!$this->canUseForCountry($billingCountry)) {
            Mage::throwException(Mage::helper('payment')->__('Selected payment type is not allowed for billing country.'));
        }
        return $this;
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('zainiraq/pay/redirect', array('_secure' => false));
    }
}