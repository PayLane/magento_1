<?php
/**
 * Payment form markup block.
 * 
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Block_Form extends Mage_Core_Block_Template {
    protected $helper;
    
    protected function _construct()  
    {  
        parent::_construct();  
        $this->setTemplate('paylane/form.phtml');
        $this->helper = Mage::helper('paylane');
    }  
    
    /**
     * Returns payment types with labels and payment type image
     * 
     * @return array(
     *  paymentTypeCode => array(
     *     label => "Label that will be shown on checkout page. Can be translated.",
     *     img => Url to image file, that will be displayed instead of label
     *  )
     * )
     */
    public function getPaymentMethods()
    {   
        $classNames = $this->helper->getPaymentTypeClasses();
        
        foreach ($classNames as $class) {
            $paymentClass = Mage::getModel('paylane/api_payment_'.$class);
            $code = $paymentClass->getCode();
            
            if($this->_isPaymentMethodActive($code)) {
                $isRecurring = $this->_isRecurringItemInCart();
                
                if( ($isRecurring && $paymentClass->isRecurringPayment() === true) || !$isRecurring) {
                    $result[$code] = array(
                        'label' => $paymentClass->getLabel(),
                        'img' => $this->_isImgEnabled($code) ? $paymentClass->getImageUrl() : null
                    );
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Check whether payment method is active
     * 
     * @param string $code 
     * @return boolean
     */
    protected function _isPaymentMethodActive($code)
    {
        return Mage::getStoreConfig($this->helper->getPaymentMethodStoreConfigStringPrefix($code) . '/active');
    }

    protected function _isImgEnabled($code)
    {
        return Mage::getStoreConfig($this->helper->getPaymentMethodStoreConfigStringPrefix($code) . '/show_img');
    }
    
    /**
     * Check whether recurring item is in a shopping cart
     * 
     * @return boolean
     */
    protected function _isRecurringItemInCart()
    {
        $result = false;
        $cartItems = Mage::getSingleton('checkout/cart')->getItems();
        
        foreach ($cartItems as $item) { 
            if($item->getProduct()->getIsRecurring()){
                $result = true;
                break;
            }
        }
        
        return $result;
    }
}