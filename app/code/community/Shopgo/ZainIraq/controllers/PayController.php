<?php
/**
 * Created by PhpStorm.
 * User: belalmazlom
 * Date: 6/8/16
 * Time: 3:20 PM
 */

require_once(Mage::getBaseDir('lib') . '/Firebase/JWT/JWT.php');
require_once(Mage::getBaseDir('lib') . '/Firebase/JWT/ExpiredException.php');

use Firebase\JWT\JWT;

class Shopgo_ZainIraq_PayController extends Mage_Core_Controller_Front_Action
{
    const LIVE_URL = 'https://api.zaincash.iq/';
    const DEMO_URL = 'https://test.zaincash.iq/';

    const CHECK_PAYMENT_STATUS_URL = 'checkPaymentStatus';
    const REDIRECT_URL = 'transaction/pay';
    const GENERATE_ID_URL = 'transaction/init';

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    private function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function indexAction()
    {
        return;
    }

    public function redirectAction()
    {
        $isDemoMode = Mage::getStoreConfig('payment/zainiraq/account_mode');

        if ($isDemoMode == 1) {
            $gatewayUrl = self::DEMO_URL . self::REDIRECT_URL;
            $generateIdURL = self::DEMO_URL . self::GENERATE_ID_URL;
        } else {
            $gatewayUrl = self::LIVE_URL . self::REDIRECT_URL;
            $generateIdURL = self::LIVE_URL . self::GENERATE_ID_URL;
        }

        $mssidn = Mage::getStoreConfig('payment/zainiraq/mssidn');
        $secretKey = Mage::getStoreConfig('payment/zainiraq/secret_key');
        $merchantId = Mage::getStoreConfig('payment/zainiraq/merchant_id');

        $session = $this->getCheckout();
        $session->setMigsQuoteId($session->getQuoteId());
        $session->setMigsRealOrderId($session->getLastRealOrderId());

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());

        $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $currentCurrencyCode = 'IQD';
        $orderAmount = $order->getBaseGrandTotal();

        $orderAmount = Mage::helper('directory')->currencyConvert($orderAmount, $baseCurrencyCode, $currentCurrencyCode);

        $orderAmount = ceil($orderAmount);

        $key = $secretKey;
        $urlObj = parse_url(Mage::getBaseUrl());

        $params = array(
            'msisdn' => intval($mssidn),
            'amount' => $orderAmount,
            'serviceType' => urlencode($urlObj['host']),
            'orderId' => $session->getLastRealOrderId(),
            'redirectUrl' => Mage::getBaseUrl() . 'zainiraq/pay/response',
            'iat' => time(),
            'exp' => time() + 3600
        );

        $token = JWT::encode($params, $key);

        $fields = array(
            'token' => urlencode($token),
            'merchantId' => urlencode($merchantId),
            'lang' => 'ar',
        );

        $fields_string = '';

        foreach ($fields as $key => $value) {
            $fields_string .= $key . '=' . $value . '&';
        }

        $fields_string = rtrim($fields_string, '&');

        //open connection
        $ch = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $generateIdURL);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //execute post
        $result = curl_exec($ch);

        if (FALSE === $result) {
            curl_close($ch);
            //redirect to failure url
            $this->_redirect('*/*/failure');
        } else {
            curl_close($ch);
            $body = json_decode($result);
            //Prepare order
            $order->addStatusToHistory($order->getStatus(), __('Customer is redirected to Zain Iraq payment.'));
            $order->save();

            $session->unsQuoteId();

            if (isset($body->id)) {
                //Show for in response
                $gatewayParams = array(
                    'id' => $body->id
                );
            } else {
                $gatewayParams = array();
            }

            $block = $this->getLayout()->createBlock(
                'Mage_Core_Block_Template', 'zainiraq_block_process', array('template' => 'shopgo/zainiraq/redirect.phtml')
            )
                ->setData('gatewayParams', $gatewayParams)
                ->setData('gatewayUrl', $gatewayUrl);

            $this->loadLayout();
            $this->getLayout()->getBlock('content')->append($block);
            $this->renderLayout();
        }
    }

    public function responseAction()
    {
        $secretKey = Mage::getStoreConfig('payment/zainiraq/secret_key');

        $token = $this->getRequest()->getParam('token');

        if (strlen($token) > 0) {
            $responseObject = JWT::decode($token, $secretKey, array('HS256'));

            try {
                if ($responseObject) {
                    $orderId = $responseObject->orderid;
                    if ($responseObject->status == "success") {
                        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
                        $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true, 'Payment Success.');
                        $order->save();
                        $_payment = $order->getPayment();
                        $_payment->setTransactionId($responseObject->id);
                        $_payment->setParentTransactionId($responseObject->id);
                        $_payment
                            ->setShouldCloseParentTransaction(Mage_Sales_Model_Order::STATE_COMPLETE)
                            ->setIsTransactionClosed(0);
                        $_payment->save();

                        Mage::getSingleton('checkout/session')->unsQuoteId();
                        Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure' => false));
                    } else {
                        //Failure
                        Mage::getSingleton('checkout/session')->setErrorMessage($responseObject->msg);
                        Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure' => false));
                    }
                }
            } catch (Exception $e) {
                Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure' => false));
            }
        }else{
            Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure' => false));
        }
    }
}
