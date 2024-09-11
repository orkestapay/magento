<?php

/**
 * @category    Payments
 * @package     Orkestapay_Cards
 * @author      Federico Balderas
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Orkestapay\Cards\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Checkout\Model\Cart;
use Orkestapay\Cards\Model\Payment as OrkestapayPayment;

class Order extends \Magento\Framework\App\Action\Action
{
    protected $payment;
    protected $logger;
    protected $cart;

    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    protected $quoteManagement;

    /**
     *
     * @param Context $context
     * @param OrkestapayPayment $payment
     * @param \Psr\Log\LoggerInterface $logger_interface
     */
    public function __construct(Context $context, OrkestapayPayment $payment, \Psr\Log\LoggerInterface $logger_interface, Cart $cart, \Magento\Quote\Model\QuoteManagement $quoteManagement)
    {
        parent::__construct($context);
        $this->payment = $payment;
        $this->logger = $logger_interface;
        $this->cart = $cart;
        $this->quoteManagement = $quoteManagement;
    }
    public function execute()
    {
        $data = null;
        $post = $this->getRequest()->getPostValue();

        try {
            $quote = $this->cart->getQuote();
            $this->logger->debug('#Order', ['device_session_id' => $post['device_session_id']]);
            $this->logger->debug('#Order', ['payment_method_id' => $post['payment_method_id']]);

            // Create Orkestapay Order
            $purchase_data = $this->getPurchaseData();
            $this->logger->debug('purchase_data ====> ' . json_encode($purchase_data));
            $orkestapay_order = $this->payment->createOrkestapayOrder($purchase_data);
            $this->logger->debug('orkestapay_order ====> ' . json_encode($orkestapay_order));


            // Create Orkestapay Payment
            $base_url = $this->payment->getBaseUrlStore();
            $payment_data = $this->getPaymentData($orkestapay_order['order_id'], $post['device_session_id'], $post['payment_method_id'], $base_url);
            $this->logger->debug('payment_data ====> ' . json_encode($payment_data));
            $orkestapay_payment = $this->payment->createOrkestapayPayment($quote->getId(), $payment_data);
            $this->logger->debug('orkestapay_payment ====> ' . json_encode($orkestapay_payment));

            // Datos que se van a devolver al front
            $data = $orkestapay_payment;
        } catch (\Exception $e) {
            $this->logger->error('#order', ['msg' => $e->getMessage()]);
            $data = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($data);
        return $resultJson;
    }

    public function getPurchaseData()
    {
        $items = [];
        $quote = $this->cart->getQuote();
        $billing_address = $quote->getBillingAddress();
        $shipping_address = $quote->getShippingAddress();

        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'product_id' => $item->getProductId(),
                'name' => $item->getName(),
                'quantity' => $item->getQty(),
                'unit_price' => round($item->getPrice(), 2),
            ];
        }

        $totalItemsAmount = $quote->getSubtotal();
        $discount = $totalItemsAmount - $quote->getSubtotalWithDiscount();

        $purchase_data = [
            'merchant_order_id' => $quote->getId() . '-' . time(),
            'currency' => $quote->getBaseCurrencyCode(),
            'country_code' => $billing_address->getCountryId(),
            'products' => $items,
            'discounts' => [
                ['amount' => abs(round($discount, 2))],
            ],
            'shipping_details' => [
                'amount' => round($quote->getShippingAddress()->getShippingAmount(), 2),
            ],
            'subtotal_amount' => round($totalItemsAmount, 2),
            'total_amount' => round($quote->getBaseGrandTotal(), 2),
            'customer' => [
                'first_name' => $billing_address->getFirstname(),
                'last_name' => $billing_address->getLastname(),
                'email' => $quote->getCustomerEmail(),
            ],
            'billing_address' => [
                'first_name' => $billing_address->getFirstname(),
                'last_name' => $billing_address->getLastname(),
                'email' => $quote->getCustomerEmail(),
                'line_1' => $billing_address->getStreetLine(1),
                'line_2' => $billing_address->getStreetLine(2),
                'city' => $billing_address->getCity(),
                'state' => $billing_address->getRegion(),
                'country' => $billing_address->getCountryId(),
                'zip_code' => $billing_address->getPostcode(),
            ],
            'shipping_address' => [
                'first_name' => $shipping_address->getFirstname(),
                'last_name' => $shipping_address->getLastname(),
                'email' => $quote->getCustomerEmail(),
                'line_1' => $shipping_address->getStreetLine(1),
                'line_2' => $shipping_address->getStreetLine(2),
                'city' => $shipping_address->getCity(),
                'state' => $shipping_address->getRegion(),
                'country' => $shipping_address->getCountryId(),
                'zip_code' => $shipping_address->getPostcode(),
            ],
        ];

        return $purchase_data;
    }

    public function getPaymentData($order_id, $device_session_id, $payment_method_id, $base_url)
    {
        $payment_request = [
            'order_id' => $order_id,
            'device_session_id' => $device_session_id,
            'payment_source' => [
                'type' => 'CARD',
                'payment_method_id' => $payment_method_id,
                'settings' => [
                    'card' => [
                        'capture' => true
                    ],
                    'redirection_url' => [
                        'completed_redirect_url' => $base_url . 'orkestapay/payment/success',
                        'canceled_redirect_url' => $base_url . 'checkout/cart',
                    ]
                ]
            ]
        ];

        return $payment_request;
    }
}
