<?php
/**
 * Payment model for SecureForm payment channel
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Api_Payment_SecureForm extends Endora_PayLane_Model_Api_Payment_Type_Abstract {
    const RETURN_URL_PATH = 'paylane/payment/externalUrlResponse';
    
    protected $_paymentTypeCode = 'secureForm';

    public function __construct() {
        parent::__construct();
        $this->_paymentImgUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) . '/frontend/base/default/paylane/images/payment_methods/secureform.png';
    }

    public function handlePayment(Mage_Sales_Model_Order $order, $params = null) {
        $data = array();
        $client = $this->getClient();
        $helper = Mage::helper('paylane');
        
        $data['sale'] = $this->_prepareSaleData($order);
        $data['customer'] = $this->_prepareCustomerData($order);
        $data['back_url'] = Mage::getUrl(self::RETURN_URL_PATH, array('_secure' => true));
        
        $helper->log('send data for secure form payment channel:');
        $helper->log($data);
        $result = $client->sofortSale($data);
        $helper->log('Received response from PayLane:');
        $helper->log($result);
        
        //probably should be in externalUrlResponseAction
        if($result['success']) {
            header('Location: ' . $result['redirect_url']);
        } else {
            $orderStatus = $helper->getErrorOrderStatus();
            $errorCode = '';
            $errorText = '';
            if(!empty($result['error'])) {
                $errorCode = (!empty($result['error']['error_number'])) ? $result['error']['error_number'] : '';
                $errorText = (!empty($result['error']['error_description'])) ? $result['error']['error_description'] : '';
            }
            $comment = $helper->__('There was an error in payment process via PayLane module (Error code: %s, Error text: %s)', $errorCode, $errorText);
            $helper->setOrderState($order, $orderStatus, $comment);
//            $order->setState($helper->getStateByStatus($orderStatus), $orderStatus, $comment, false);
            $order->save();
        }
        
        return $result['success'];
    }
}
