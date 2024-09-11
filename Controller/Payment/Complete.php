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
use Orkestapay\Cards\Model\Payment as OrkestapayPayment;

class Complete extends \Magento\Framework\App\Action\Action
{
    protected $payment;
    protected $logger;

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
    public function __construct(Context $context, OrkestapayPayment $payment, \Psr\Log\LoggerInterface $logger_interface, \Magento\Quote\Model\QuoteManagement $quoteManagement)
    {
        parent::__construct($context);
        $this->payment = $payment;
        $this->logger = $logger_interface;
        $this->quoteManagement = $quoteManagement;
    }
    public function execute()
    {
        $data = null;
        $post = $this->getRequest()->getPostValue();

        try {
            $this->logger->debug('#Complete', ['orkestapay_payment_id' => $post['orkestapay_payment_id']]);

            // Complete 3DS
            $complete_payment = $this->payment->complete3DS($post['orkestapay_payment_id']);
            $this->logger->debug('orkestapay_order ====> ' . json_encode($complete_payment));

            // Datos que se van a devolver al front
            $data = $complete_payment;
        } catch (\Exception $e) {
            $this->logger->error('#Complete', ['msg' => $e->getMessage()]);
            $data = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($data);
        return $resultJson;
    }
}
