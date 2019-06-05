<?php
/**
 * Block to handle ApplePay payment type
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Block_Payment_ApplePay extends Mage_Core_Block_Template {

    public function isActive()
    {
        return Mage::getStoreConfig('payment/paylane_applepay/active');
    }

    public function getApiKey()
    {
        return Mage::getStoreConfig('payment/paylane/api_key');
    }

    public function getCountryCode()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        return $quote->getBillingAddress()->getCountry();
    }

    public function getCurrencyCode()
    {
        return Mage::app()->getStore()->getCurrentCurrencyCode();
    }

    public function getPaymentDescription()
    {
        return $this->__('Order from shop ') . Mage::app()->getStore()->getName();
    }

    public function getAmount()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        return sprintf('%01.2f', round($quote->getGrandTotal(), 2));
    }

    public function getLoaderImg()
    {
        return $this->getSkinUrl('paylane/images/loader.gif');
    }
}
