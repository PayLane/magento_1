<?php
/**
 * Block to handle DirectDebit payment type
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Block_Payment_DirectDebit extends Mage_Core_Block_Template {
    
    const COUNTRY_OPTIONS_ID = 'payment_params:account_country';
    const COUNTRY_OPTIONS_NAME = 'payment[additional_information][account_country]';
    const COUNTRY_OPTIONS_TITLE = 'Bank account country';
    
    public function getCountryOptionsHtml($defValue=null)
    {
        $collection = $this->getData('country_collection');
        if (is_null($collection)) {
            $collection = Mage::getModel('directory/country')->getResourceCollection()
                ->loadByStore();
            $this->setData('country_collection', $collection);
        }
        
        if (is_null($defValue)) {
            $defValue = $this->getData('country_id');
            if (is_null($defValue)) {
                $defValue = Mage::helper('core')->getDefaultCountry();
            }
        }
        
        $cacheKey = 'DIRECTORY_COUNTRY_SELECT_STORE_'.Mage::app()->getStore()->getCode();
        if (Mage::app()->useCache('config') && $cache = Mage::app()->loadCache($cacheKey)) {
            $options = unserialize($cache);
        } else {
            $options = $collection->toOptionArray();
            if (Mage::app()->useCache('config')) {
                Mage::app()->saveCache(serialize($options), $cacheKey, array('config'));
            }
        }
        $html = $this->getLayout()->createBlock('core/html_select')
            ->setName(self::COUNTRY_OPTIONS_NAME)
            ->setId(self::COUNTRY_OPTIONS_ID)
            ->setTitle(Mage::helper('directory')->__(self::COUNTRY_OPTIONS_TITLE))
            ->setClass('validate-select required-entry')
            ->setValue($defValue)
            ->setOptions($options)
            ->getHtml();
        
        return $html;
    }
    
}
