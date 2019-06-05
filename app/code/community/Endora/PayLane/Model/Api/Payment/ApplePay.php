<?php
/**
 * Payment model for ApplePay payment channel
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Api_Payment_ApplePay extends Endora_PayLane_Model_Api_Payment_Type_Abstract {
    const RETURN_URL_PATH = 'paylane/payment/externalUrlResponse';
    
    protected $_paymentTypeCode = 'applePay';
    protected $_isRecurringPayment = false;

    public function __construct() {
        parent::__construct();
        $this->_paymentImgUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) . '/frontend/base/default/paylane/images/payment_methods/applepay.png';
    }

    public function handlePayment(Mage_Sales_Model_Order $order, $params = null) {
        $data = array();
        $client = $this->getClient();
        $helper = Mage::helper('paylane');

        $data['sale'] = $this->_prepareSaleData($order);
        $data['customer'] = $this->_prepareCustomerData($order);
        $data['card'] = isset($params['token']) ? array('token' => $params['token']) : array();

        $helper->log('send data for applepay payment channel:');
        $helper->log($data);
        $result = $client->applePaySale($data);
        $helper->log('Received response from PayLane:');
        $helper->log($result);

        if($result['success']) {
            $orderStatus = $helper->getClearedOrderStatus();
            $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $result['id_sale']);
            $order->setPaylaneSaleId($result['id_sale']);
        } else {
            $orderStatus = $helper->getErrorOrderStatus();
            $errorCode = '';
            $errorText = '';
            if(!empty($result['error'])) {
                $errorCode = (!empty($result['error']['error_number'])) ? $result['error']['error_number'] : '';
                $errorText = (!empty($result['error']['error_description'])) ? $result['error']['error_description'] : '';
            }
            $comment = $helper->__('There was an error in payment process via PayLane module (Error code: %s, Error text: %s)', $errorCode, $errorText);
        }

        $helper->setOrderState($order, $orderStatus, $comment);
        $order->save();

        return $result['success'];
    }
    
    /**
     * Prepares customer data from order for API request
     * 
     * @param Mage_Sales_Model_Order $order
     * @return array Array of order data
     */
    protected function _prepareCustomerData($order)
    {
        $result = array();   
        
        $address = $order->getBillingAddress();
        $result['name'] = $order->getCustomerName();
        $result['email'] = $address->getEmail();
        $result['ip'] = $order->getRemoteIp();
        $result['country_code'] = $address->getCountry();
        
        return $result;
    }
}
