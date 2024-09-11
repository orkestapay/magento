<?php

/**
 * Copyright © 2015 Pay.nl All rights reserved.
 */

namespace Orkestapay\Cards\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Orkestapay\Cards\Model\Payment as OrkestapayPayment;
use Magento\Checkout\Model\Cart;

class OrkestapayConfigProvider implements ConfigProviderInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var string[]
     */
    protected $methodCodes = [
        'orkestapay_cards',
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var \Orkestapay\Cards\Model\Payment
     */
    protected $payment;

    protected $cart;


    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param PaymentHelper $paymentHelper
     * @param OrkestapayPayment $payment
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        PaymentHelper $paymentHelper,
        OrkestapayPayment $payment,
        Cart $cart
    ) {
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
        $this->logger = $logger;
        $this->cart = $cart;
        $this->payment = $payment;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $protocol = $this->hostSecure() === true ? 'https://' : 'http://';

                $config['payment']['orkestapay_credentials'] = array("merchant_id" => $this->payment->getMerchantId(), "public_key" => $this->payment->getPublicKey(), "is_sandbox"  => $this->payment->isSandbox());
                $config['payment']['total'] = $this->cart->getQuote()->getGrandTotal();
                $config['payment']['is_logged_in'] = $this->payment->isLoggedIn();
                $config['payment']['url_store'] = $this->payment->getBaseUrlStore();
                $config['payment']['create_order_url'] = $this->payment->getBaseUrlStore() . '/orkestapay/payment/order';
                $config['payment']['complete_3ds_url'] = $this->payment->getBaseUrlStore() . '/orkestapay/payment/complete';

                $config['payment']['ccform']["availableTypes"][$code] = array("AE" => "American Express", "VI" => "Visa", "MC" => "MasterCard", "CN" => "Carnet");
                $config['payment']['ccform']["hasVerification"][$code] = true;
                $config['payment']['ccform']["hasSsCardType"][$code] = false;
                $config['payment']['ccform']["months"][$code] = $this->getMonths();
                $config['payment']['ccform']["years"][$code] = $this->getYears();
                $config['payment']['ccform']["cvvImageUrl"][$code] = $protocol . $_SERVER['SERVER_NAME'] . "/pub/static/frontend/Magento/luma/es_MX/Magento_Checkout/cvv.png";
                $config['payment']['ccform']["ssStartYears"][$code] = $this->getStartYears();
            }
        }

        return $config;
    }

    public function getMonths()
    {
        return array(
            "1" => __('01 - January'),
            "2" => __('02 - February'),
            "3" => __('03 - March'),
            "4" => __('04 - April'),
            "5" => __('05 - May'),
            "6" => __('06 - June'),
            "7" => __('07 - July'),
            "8" => __('08 - August'),
            "9" => __('09 - September'),
            "10" => __('10 - October'),
            "11" => __('11 - November'),
            "12" => __('12 - December')
        );
    }

    public function getYears()
    {
        $years = array();
        for ($i = 0; $i <= 10; $i++) {
            $year = (string)($i + date('Y'));
            $years[$year] = $year;
        }
        return $years;
    }

    public function getStartYears()
    {
        $years = array();
        for ($i = 5; $i >= 0; $i--) {
            $year = (string)(date('Y') - $i);
            $years[$year] = $year;
        }
        return $years;
    }

    public function hostSecure()
    {
        $is_secure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $is_secure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $is_secure = true;
        }

        return $is_secure;
    }
}
