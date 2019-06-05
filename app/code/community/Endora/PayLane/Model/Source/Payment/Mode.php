<?php
/**
 * Source model for allowed payment modes
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Source_Payment_Mode {
    
    public function toOptionArray()
    {
        $helper = Mage::helper('paylane');
        return array(
            array('value' => Endora_PayLane_Helper_Data::GATEWAY_TYPE_API, 'label' => $helper->__('API')),
            array('value' => Endora_PayLane_Helper_Data::GATEWAY_TYPE_SECURE_FORM, 'label' => $helper->__('Secure Form')),
        );
    }
    
}
