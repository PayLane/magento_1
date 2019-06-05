<?php
/**
 * Payment model for Credit card payment channel
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_Model_Api_Payment_CreditCard extends Endora_PayLane_Model_Api_Payment_Type_Abstract {
    const RETURN_URL_PATH = 'paylane/payment/externalUrlResponse';
    
    protected $_paymentTypeCode = 'creditCard';
    protected $_isRecurringPayment = true;

    public function __construct() {
        parent::__construct();
        $this->_paymentImgUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN) . '/frontend/base/default/paylane/images/payment_methods/creditcard.png';
    }

    /**
     * Method to handle payment process
     * 
     * @param Mage_Sales_Model_Order $order
     * @return boolean Success flag
     */
    public function handlePayment(Mage_Sales_Model_Order $order, $paymentParams = null) 
    {
        $helper = Mage::helper('paylane');
        $data = array();
        $client = $this->getClient();

        if(!empty($paymentParams['authorization_id']) || !empty($paymentParams['sale_id'])) { //using single-click way
            $result = $this->_handleSingleClickPayment($order, $paymentParams);
        } else {
            $data['sale'] = $this->_prepareSaleData($order);
            $data['customer'] = $this->_prepareCustomerData($order);
            $data['card'] = $paymentParams;

            $helper->log('send data for credit cart payment channel:');
            if($helper->is3dsActive()) {
                $data['back_url'] = Mage::getUrl(self::RETURN_URL_PATH, array('_secure' => true));
                $result = $client->checkCard3DSecureByToken($data);
            } else {
	                $result = $client->cardSaleByToken($data);
            }
            
            $helper->log($data);
            $helper->log('Received response from PayLane:');
            $helper->log($result);
        }
        
        if(!empty($result['success']) && $result['success']) {
            if(!empty($result['is_card_enrolled']) && $result['is_card_enrolled']) {
                header('Location: ' . $result['redirect_url']);
            } else if(!empty($result['is_card_enrolled']) && $result['is_card_enrolled'] == false) {
                $ds3Status = $client->saleBy3DSecureAuthorization(array ('id_3dsecure_auth' => $result['id_3dsecure_auth']));
                if(!empty($ds3Status['success']) && $ds3Status['success']) {
                    $orderStatus = $helper->getClearedOrderStatus();
                    $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $ds3Status['id_sale']);
                    $order->setPaylaneSaleId($ds3Status['id_sale']);
                } else {
                    $orderStatus = $helper->getErrorOrderStatus();
                    $errorCode = '';
                    $errorText = '';
                    if(!empty($ds3Status['error'])) {
                        $errorCode = (!empty($ds3Status['error']['error_number'])) ? $ds3Status['error']['error_number'] : '';
                        $errorText = (!empty($ds3Status['error']['error_description'])) ? $ds3Status['error']['error_description'] : '';
                    }
                    $comment = $helper->__('There was an error in payment process via PayLane module (Error code: %s, Error text: %s)', $errorCode, $errorText);
                }
            } else { //normal transaction, without 3DS
                $orderStatus = $helper->getClearedOrderStatus();
                $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $result['id_sale']);
                $order->setPaylaneSaleId($result['id_sale']);
            }
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
        
        $helper->log('order data changing: ');
        $helper->log('order status: ' .$orderStatus);
        $helper->log('order state: ' .$helper->getStateByStatus($orderStatus));
        $helper->log('comment: ' . $comment);
        
        $helper->setOrderState($order, $orderStatus, $comment);
//        $order->setState($helper->getStateByStatus($orderStatus), $orderStatus, $comment, false);
        $order->save();
        
        return $result['success'];
    }
    
    /**
     * Method to handle recurring payment process
     * 
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param array $params
     * @return boolean Success flag
     */
    public function handleRecurringPayment(Mage_Payment_Model_Recurring_Profile $profile, $params = null)
    {
        $data = array();
        $client = $this->getClient();
        
        $data['sale'] = $this->_prepareRecurringSaleData($profile);
        $data['customer'] = $this->_prepareRecurringCustomerData($profile);
        $data['card'] = $params;

        $result = $client->cardSale($data);
        
        return $result;
    }
    
    public function handleCardAuthorization($customerId, $params)
    {
        $helper = Mage::helper('paylane');
        $data = array();
        $client = $this->getClient();
        $customer = Mage::getModel('customer/customer')->load($customerId);
        
        $data['sale'] = array(
            'amount' => sprintf('%01.2f', $helper->getCreditCardAuthorizationAmount()),
            'currency' => Mage::app()->getStore()->getCurrentCurrencyCode(),
            'description' => 'Credit card authorization for customer with ID ' . $customerId
        );
        $data['customer'] = $this->_prepareCustomerDataForAuthorization($customer);
        $data['card'] = $params;
        
        $result = $client->cardAuthorization($data);
        
        if($result['success']) {
            $customer->setCardAuthorizationId($result['id_authorization']);
            $customer->save();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Prepares order data for API request
     * 
     * @param Mage_Sales_Model_Order $order
     * @return array Array of order data
     */
    protected function _prepareSaleData($order)
    {
        $helper = Mage::helper('paylane');
        $result = array();   
        $result['amount'] = sprintf('%01.2f', $order->getGrandTotal());
        $result['currency'] = $order->getOrderCurrencyCode();
        $result['description'] = $order->getIncrementId();
        if($helper->canOverwriteFraudCheck()) {
            $result['fraud_check_on'] = $helper->isFraudCheck();
        }
        if($helper->canOverwriteAvsCheckLevel()) {
            $result['avs_check_level'] = $helper->getAvsCheckLevel();
        }
        
        return $result;
    }
    
    /**
     * Prepares sale data for recurring payment
     * 
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return type
     */
    protected function _prepareRecurringSaleData(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $helper = Mage::helper('paylane');
        $result = array();   
        $result['amount'] = sprintf('%01.2f', $profile->getBillingAmount());
        $result['currency'] = $profile->getCurrencyCode();
        $result['description'] = $profile->getScheduleDescription();
        if($helper->canOverwriteFraudCheck()) {
            $result['fraud_check_on'] = $helper->isFraudCheck();
        }
        if($helper->canOverwriteAvsCheckLevel()) {
            $result['avs_check_level'] = $helper->getAvsCheckLevel();
        }
        
        return $result;
    }
    
    /**
     * Prepares customer data from $customer for API request
     * to authorize card
     * 
     * @param Mage_Customer_Model_Customer $customer
     * @return array Array of customer data
     */
    protected function _prepareCustomerDataForAuthorization($customer)
    {
        $result = array();   
        $address = $customer->getDefaultBilling();
        if ($address){
            $address = Mage::getModel('customer/address')->load($address);
        }
        
        $result['name'] = $customer->getName();
        $result['email'] = $customer->getEmail();
        $result['ip'] = Mage::helper('core/http')->getRemoteAddr();
        $result['address'] = array(
            'city' => $address->getCity(),
            'state' => $address->getRegion(),
            'country_code' => $address->getCountry(),
            'zip' => $address->getPostcode(),
            'street_house' => $address->getStreet(true)
        );
        
        return $result;
    }    
    
    /**
     * Handle payment in single-click type
     * 
     * @link http://devzone.paylane.pl/api/karty/platnosci-single-click/ Single-click payment explanation
     * 
     * @param type $order
     * @param type $paymentParams
     * @return type
     */
    protected function _handleSingleClickPayment($order, $paymentParams = null)
    {
        $data = array();
        $client = $this->getClient();
        
        if(!empty($paymentParams['sale_id'])) {
            $data['id_sale'] = $paymentParams['sale_id'];
        }
        if(!empty($paymentParams['authorization_id'])) {
            $data['id_authorization'] = $paymentParams['authorization_id'];
        }
        
        $data['amount'] = sprintf('%01.2f', $order->getGrandTotal());
        $data['currency'] = $order->getOrderCurrencyCode();
        $data['description'] = $order->getIncrementId();
        
        if(!empty($paymentParams['sale_id'])) {
            $result = $client->resaleBySale($data);
        }
        if(!empty($paymentParams['authorization_id'])) {
            $result = $client->resaleByAuthorization($data);
        }
        
        return $result;
    }
}
