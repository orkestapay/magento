<?php

/**
 * Orkestapay_Cards payment method model
 *
 * @category    Orkestapay
 * @package     Orkestapay_Cards
 * @author      Federico Balderas
 * @copyright   Orkestapay (http://orkestapay.com)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Orkestapay\Cards\Model;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;
use Orkestapay\Cards\Model\Utils\OrkestapayRequest;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;

class Payment extends \Magento\Payment\Model\Method\Cc
{
    const CODE = 'orkestapay_cards';

    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canOrder = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canAuthorize = true;
    protected $_canVoid = true;
    protected $is_sandbox;
    protected $is_active;
    protected $merchant_id = null;
    protected $client_id = null;
    protected $client_secret = null;
    protected $public_key = null;
    protected $whsec = null;
    protected $scopeConfig;
    protected $logger_interface;
    protected $_storeManager;
    protected $orkestapayRequest;

    /**
     * @var Customer
     */
    protected $customerModel;
    /**
     * @var CustomerSession
     */
    protected $customerSession;

    protected $orkestapayCustomerFactory;

    /**
     *  @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    protected $configWriter;

    /**
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param array $data
     * @param \Magento\Store\Model\StoreManagerInterface $data
     * @param WriterInterface $configWriter
     * @param OrkestapayRequest $orkestapayRequest
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        Logger $logger,
        ModuleListInterface $moduleList,
        TimezoneInterface $localeDate,
        \Psr\Log\LoggerInterface $logger_interface,
        Customer $customerModel,
        CustomerSession $customerSession,
        OrkestapayRequest $orkestapayRequest,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $moduleList, $localeDate, null, null, $data);

        $this->customerModel = $customerModel;
        $this->customerSession = $customerSession;


        $this->_storeManager = $storeManager;
        $this->logger_interface = $logger_interface;

        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;

        $this->title = $this->getConfigData('title');
        $this->is_active = $this->getConfigData('active');
        $this->is_sandbox = $this->getConfigData('is_sandbox');
        $this->country = $this->getConfigData('country');
        $this->merchant_classification = $this->getConfigData('merchant_classification');

        $this->_canRefund = true;
        $this->_canRefundInvoicePartial = true;

        $this->merchant_id = $this->getConfigData('merchant_id');
        $this->client_id = $this->getConfigData('client_id');
        $this->client_secret = $this->getConfigData('client_secret');
        $this->public_key = $this->getConfigData('public_key');
        $this->whsec = $this->getConfigData('whsec');

        $this->orkestapayRequest = $orkestapayRequest;
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate()
    {
        $info = $this->getInfoInstance();
        $device_session_id = $info->getAdditionalInformation('device_session_id');
        $errorMsg = false;
        $this->logger_interface->debug('#validate', array('$device_session_id' => $device_session_id));

        /** CC_number validation is not done because it should not get into the server * */
        if ($info->getCcType() != null && !in_array($info->getCcType(), $this->getAvailableCardTypes())) {
            $errorMsg = 'Credit card type is not allowed for this payment method.';
            $this->logger_interface->debug('validate', ['#ERROR validate() => ' => $errorMsg]);
            throw new \Magento\Framework\Exception\LocalizedException(__($errorMsg));
        }

        return $this;
    }

    /**
     * Assign corresponding data
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        $infoInstance = $this->getInfoInstance();
        $additionalData = $data->getData('additional_data') != null ? $data->getData('additional_data') : $data->getData();

        $infoInstance->setAdditionalInformation('device_session_id', isset($additionalData['device_session_id']) ? $additionalData['device_session_id'] : null);
        $infoInstance->setAdditionalInformation('orkestapay_token', isset($additionalData['orkestapay_token']) ? $additionalData['orkestapay_token'] : null);
        $infoInstance->setAdditionalInformation('payment_id', isset($additionalData['payment_id']) ? $additionalData['payment_id'] : null);

        return $this;
    }

    /**
     * Refund capture
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $trx_id = $payment->getTransactionId();
        $orkestapay_payment_id = str_replace('-refund', '', $trx_id);

        $this->logger_interface->debug('#refund', ['$orkestapay_payment_id' => $orkestapay_payment_id, '$order_id' => $order->getIncrementId(), '$status' => $order->getStatus(), '$amount' => $amount]);

        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for refund.'));
        }

        try {
            $refundData = [
                'description' => 'Refund requested from Magento',
                'amount' => $amount,
            ];

            $this->createOrkestapayRefund($orkestapay_payment_id, $refundData);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }

        return $this;
    }

    /**
     * Send authorize request to gateway
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface $payment
     * @param  float $amount
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $this->logger_interface->debug('#authorize', ['$order_id' => $order->getIncrementId(), '$status' => $order->getStatus(), '$amount' => $amount]);
        $payment->setAdditionalInformation('payment_type', $this->getConfigData('payment_action'));
        $payment->setIsTransactionClosed(false);
        $payment->setSkipOrderProcessing(true);
        $this->processCapture($payment, $amount);
        return $this;
    }

    /**
     * Send capture request to gateway
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $this->logger_interface->debug('#capture', ['$order_id' => $order->getIncrementId(), '$trx_id' => $payment->getLastTransId(), '$status' => $order->getStatus(), '$amount' => $amount]);

        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for capture.'));
        }

        $payment->setAmount($amount);
        if (!$payment->getLastTransId()) {
            $this->processCapture($payment, $amount);
        }

        return $this;
    }

    /**
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function processCapture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $this->logger_interface->debug('#processCapture', ['$order_id' => $order->getIncrementId(), '$status' => $order->getStatus(), '$amount' => $amount]);

        $payment_method_id = $this->getInfoInstance()->getAdditionalInformation('orkestapay_token');
        $device_session_id = $this->getInfoInstance()->getAdditionalInformation('device_session_id');
        $payment_id = $this->getInfoInstance()->getAdditionalInformation('payment_id');

        if (!$payment_method_id) {
            $msg = 'ERROR 100 Please specify card info';
            throw new \Magento\Framework\Validator\Exception(__($msg));
        }

        $this->logger_interface->debug('#processCapture', ['$token' => $payment_method_id, '$device_session_id' => $device_session_id, '$payment_id' => $payment_id]);

        try {
            // Obtiene el detalle del pago de Orkestapay
            $orkestaPayment = $this->getOrkestapayPayment($payment_id);
            $this->logger_interface->debug('orkestaPayment ====> ', $orkestaPayment);

            $payment->setTransactionId($payment_id);

            if ($orkestaPayment['status'] === 'COMPLETED') {
                $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
                $order->setState($state)->setStatus($state);
                $order->setTotalPaid($amount);

                $payment->setAmountPaid($amount);
                $payment->setIsTransactionClosed(true);
                $payment->setIsTransactionPending(false);
            } else {
                $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
                $order->setState($state)->setStatus($state);

                $payment->setIsTransactionClosed(false);
                $payment->setIsTransactionPending(true);
            }

            $order->save();

            $this->logger_interface->debug('#saveOrder');
        } catch (\Exception $e) {
            $this->logger_interface->error('ERROR', ['message' => $e->getMessage(), 'code' => $e->getCode()]);
            if ($e->getMessage()) {
                throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
            } else {
                throw new \Magento\Framework\Validator\Exception(__('An internal error occurred in Magento. Please try again later.'));
            }
        }

        return $this;
    }

    public function getCCBrandCode($brand)
    {
        $code = null;
        switch ($brand) {
            case 'mastercard':
                $code = 'MC';
                break;

            case 'visa':
                $code = 'VI';
                break;

            case 'american_express':
                $code = 'AE';
                break;
            case 'carnet':
                $code = 'CN';
                break;
            case 'diners':
                $code = 'DN';
                break;
        }
        return $code;
    }

    public function getAvailableCardTypes()
    {
        return array("AE", "VI", "MC", "CN", "DI", "DN", "JCB");
    }

    public function createOrkestapayOrder($order_request)
    {
        $credentials = ['client_id' => $this->client_id, 'client_secret' => $this->client_secret, 'grant_type' => 'client_credentials'];
        $orkestaOrder = $this->orkestapayRequest->make('/v1/orders', $this->is_sandbox, $credentials, "POST", $order_request);
        return $orkestaOrder;
    }

    public function createOrkestapayPayment($order_id, $payment_request)
    {
        $credentials = ['client_id' => $this->client_id, 'client_secret' => $this->client_secret, 'grant_type' => 'client_credentials'];
        $idempotency_key = $order_id . '-' . time();
        $orkesta_payment = $this->orkestapayRequest->make('/v1/payments', $this->is_sandbox, $credentials, "POST", $payment_request, $idempotency_key);
        return $orkesta_payment;
    }

    public function getOrkestapayPayment($payment_id)
    {
        $credentials = ['client_id' => $this->client_id, 'client_secret' => $this->client_secret, 'grant_type' => 'client_credentials'];
        $orkesta_payment = $this->orkestapayRequest->make("/v1/payments/" . $payment_id, $this->is_sandbox, $credentials);
        return $orkesta_payment;
    }

    public function getOrkestapayPaymentMethod($payment_method_id)
    {
        $credentials = ['client_id' => $this->client_id, 'client_secret' => $this->client_secret, 'grant_type' => 'client_credentials'];
        $orkestaPaymentMethod = $this->orkestapayRequest->make("/v1/payment-methods/" . $payment_method_id, $this->is_sandbox, $credentials);
        return $orkestaPaymentMethod;
    }

    public function complete3DS($orkestapay_payment_id)
    {
        $credentials = ['client_id' => $this->client_id, 'client_secret' => $this->client_secret, 'grant_type' => 'client_credentials'];
        $idempotency_key = $orkestapay_payment_id;
        $complete_payment = $this->orkestapayRequest->make('/v1/payments/' . $orkestapay_payment_id . '/complete', $this->is_sandbox, $credentials, "POST", [], $idempotency_key);
        return $complete_payment;
    }

    public function createOrkestapayRefund($orkestapay_payment_id, $data)
    {
        $credentials = ['client_id' => $this->client_id, 'client_secret' => $this->client_secret, 'grant_type' => 'client_credentials'];
        $idempotency_key = $orkestapay_payment_id . '-' . time();
        $complete_payment = $this->orkestapayRequest->make('/v1/payments/' . $orkestapay_payment_id . '/refund', $this->is_sandbox, $credentials, "POST", $data, $idempotency_key);
        return $complete_payment;
    }

    public function getBaseUrlStore()
    {
        $base_url = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
        return $base_url;
    }

    public function isLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote);
    }

    /**
     * @return string
     */
    public function getMerchantId()
    {
        return $this->merchant_id;
    }

    /**
     * @return string
     */
    public function getPublicKey()
    {
        return $this->public_key;
    }

    /**
     * @return boolean
     */
    public function isSandbox()
    {
        return $this->is_sandbox;
    }

    public function getCountry()
    {
        return $this->country;
    }

    public function validateSettings()
    {
        return true;
    }

    public function getCode()
    {
        return $this->_code;
    }
}
