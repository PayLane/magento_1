<?php
/**
 * Controller to handle payment flow
 *
 * @author Michał Zabielski <michal.zabielski@endora.pl> http://www.endora.pl
 */
class Endora_PayLane_PaymentController extends Mage_Core_Controller_Front_Action {
    public function redirectAction()
    {   
        $helper = Mage::helper('paylane');
        
        $lastOrderId = Mage::getSingleton('checkout/session')
                   ->getLastRealOrderId();
        $order = Mage::getModel('sales/order')
                   ->loadByIncrementId($lastOrderId);
        
        $paymentParams = $order->getPayment()->getAdditionalInformation();
        $paymentType = $paymentParams['paylane_payment_type'];
        
        $helper->log('Receive request for order #' . $lastOrderId);
        $helper->log('paymentType: ' . $paymentType);
        
        if($paymentType == Endora_PayLane_Helper_Data::GATEWAY_TYPE_SECURE_FORM) {
            $this->_redirect('paylane/payment/secureForm', array('_secure' => true));
        } else {
            unset($paymentParams['paylane_payment_type']);
            
            $helper->log('$paymentParams: ');
            $helper->log($paymentParams);
            
            $apiPayment = Mage::getModel('paylane/api_payment_' . $paymentType);
            $result = $apiPayment->handlePayment($order, $paymentParams);
            
            Mage::getSingleton('checkout/session')->unsetData('payment_params');
            
            $this->_redirect($helper->getRedirectUrl(!$result), array('_secure' => true));
        }
    }
    
    public function secureFormAction()
    {
        $lastOrderId = Mage::getSingleton('checkout/session')
                   ->getLastRealOrderId();
        $order = Mage::getModel('sales/order')
                   ->loadByIncrementId($lastOrderId);
        $this->loadLayout();
        $this->getLayout()->getBlock('paylane_redirect')->setOrder($order);
        $this->renderLayout();
    }
    
    public function secureFormResponseAction()
    {
        $helper = Mage::helper('paylane');
        $payment = Mage::getModel('paylane/payment');
        $params = $this->getRequest()->getParams();
        
        $error = false;
        $orderStatus = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $orderIncrementId = $params['description'];
        $transactionId = $payment->getTransactionId($params);
        $order = Mage::getModel('sales/order')->load($orderIncrementId, 'increment_id');
        $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $transactionId);
        
        if($payment->verifyResponseHash($params)) {
            switch($params['status']) {
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_PENDING :
                    $orderStatus = $helper->getPendingOrderStatus();
                    break;
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_PERFORMED :
                    $orderStatus = $helper->getPerformedOrderStatus();
                    break;
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_CLEARED :
                    $orderStatus = $helper->getClearedOrderStatus();
                    break;
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_ERROR :
                default:
                    $orderStatus = $helper->getErrorOrderStatus();
                    $error = true;
            }
        } else {
            $orderStatus = Mage_Sales_Model_Order::STATE_HOLDED;
            $error = true;
        }
        
        if(!empty($params['id_error']) || $error) {
            $errorCode = (!empty($params['error_code'])) ? $params['error_code'] : '';
            $errorText = (!empty($params['error_text'])) ? $params['error_text'] : '';
            $comment = $helper->__('There was an error in payment process via PayLane module (Error ID: %s , Error code: %s, Error text: %s)', $params['id_error'], $errorCode, $errorText);
        } else {
            $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $transactionId);
            $order->setPaylaneSaleId($transactionId);
        }
        
        $helper->setOrderState($order, $orderStatus, $comment);
//        $order->setState($helper->getStateByStatus($orderStatus), $orderStatus, $comment, false);
        $order->save();
        
        $this->_redirect($helper->getRedirectUrl($error), array('_secure' => true));
    }
    
    public function fetchPaymentTemplateAction()
    {
        $paymentType = $this->getRequest()->getParam('paymentType');
        $templatePath = strtolower($paymentType);
        
        return $this->getResponse()->setBody($this->getLayout()->createBlock(
            'paylane/payment_'.$paymentType)->setTemplate('paylane/payment/'.$templatePath.'.phtml')->toHtml()
        );
    }
    
    public function externalUrlResponseAction()
    {
        $lastOrderId = Mage::getSingleton('checkout/session')
                   ->getLastRealOrderId();
        $order = Mage::getModel('sales/order')
               ->loadByIncrementId($lastOrderId);
        $helper = Mage::helper('paylane');
        $payment = Mage::getModel('paylane/payment');
        $result = $this->getRequest()->getParams();
        $success = false;
        $paymentType = $order->getPayment()->getAdditionalInformation('paylane_payment_type');
        
        if($payment->verifyResponseHash($result, $paymentType)) {
            if(($result['status'] != Endora_PayLane_Model_Payment::PAYMENT_STATUS_ERROR) && isset($result['id_3dsecure_auth']) && !empty($result['id_3dsecure_auth'])) { //only for 3DS
                $cardModel = Mage::getModel('paylane/api_payment_creditCard');
                $ds3Status = $cardModel->getClient()->saleBy3DSecureAuthorization(array ('id_3dsecure_auth' => $result['id_3dsecure_auth']));
                if(!empty($ds3Status['success']) && $ds3Status['success']) {
                    $result['status'] = Endora_PayLane_Model_Payment::PAYMENT_STATUS_CLEARED;
                    $result['id_sale'] = $ds3Status['id_sale'];
                } else {
                    Endora_PayLane_Model_Payment::PAYMENT_STATUS_ERROR;
                }
            }
            
            switch($result['status']) {
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_CLEARED :
                        $orderStatus = $helper->getClearedOrderStatus();
                        $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $result['id_sale']);
                        $order->setPaylaneSaleId($result['id_sale']);
                        $success = true;
                    break;
                
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_PERFORMED :
                        $orderStatus = $helper->getPerformedOrderStatus();
                        $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $result['id_sale']);
                        $order->setPaylaneSaleId($result['id_sale']);
                        $success = true;
                    break;
                
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_ERROR :
                        $orderStatus = $helper->getErrorOrderStatus();
                        $errorCode = '';
                        $errorText = '';
                        if(!empty($result['error'])) {
                            $errorCode = (!empty($result['error']['error_number'])) ? $result['error']['error_number'] : '';
                            $errorText = (!empty($result['error']['error_description'])) ? $result['error']['error_description'] : '';
                        }
                        $comment = $helper->__('There was an error in payment process via PayLane module (Error code: %s, Error text: %s)', $errorCode, $errorText);
                    break;
                
                case Endora_PayLane_Model_Payment::PAYMENT_STATUS_PENDING :
                default :
                        $orderStatus = $helper->getPendingOrderStatus();
                        $comment = $helper->__('Payment handled via PayLane module | Transaction ID: %s', $result['id_sale']);
                        $order->setPaylaneSaleId($result['id_sale']);
                        $success = true;
                    break;
            }
        } else {
            $orderStatus = $helper->getErrorOrderStatus();
            $comment = $helper->__('There was an error in payment process via PayLane module (hash verification failed)');
        }
        
        $helper->setOrderState($order, $orderStatus, $comment);
//        $order->setState($helper->getStateByStatus($orderStatus), $orderStatus, $comment, false);
        $order->save();
        
        $this->_redirect($helper->getRedirectUrl(!$success), array('_secure' => true));
    }
    
    public function recurringProfileResponseAction()
    {
        $lastOrderId = Mage::getSingleton('checkout/session')
                   ->getLastRealOrderId();
        $order = Mage::getModel('sales/order')
               ->loadByIncrementId($lastOrderId);
        $helper = Mage::helper('paylane');
        $payment = Mage::getModel('paylane/payment');
        $result = $this->getRequest()->getParams();
        $success = false;
    }

    public function applePayAction()
    {
        $helper = Mage::helper('paylane');
        $lastOrderId = Mage::getSingleton('checkout/session')
                   ->getLastRealOrderId();

        $helper->log('Receive request for order #' . $lastOrderId);
        $helper->log('paymentType: applePay');

        $order = Mage::getModel('sales/order')
                   ->loadByIncrementId($lastOrderId);
        $paymentParams = json_decode(file_get_contents('php://input'), true);
        $apiPayment = Mage::getModel('paylane/api_payment_applePay');
        $result = $apiPayment->handlePayment($order, $paymentParams);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
}