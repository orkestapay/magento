<?php

/**
 * @category    Payments
 * @package     Orkestapay_Cards
 * @author      Federico Balderas
 * @copyright   Orkestapay (http://orkestapay.com)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Orkestapay\Cards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Orkestapay\Cards\Model\Payment as Config;
use Magento\Framework\DataObject;

class AfterPlaceOrder implements ObserverInterface
{

    protected $config;
    protected $order;
    protected $logger;
    protected $_actionFlag;
    protected $_response;
    protected $_redirect;
    protected $orkestapayCustomerFactory;

    public function __construct(
        Config $config,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\App\Response\RedirectInterface $redirect,
        \Magento\Framework\App\ActionFlag $actionFlag,
        \Psr\Log\LoggerInterface $logger_interface,
        \Magento\Framework\App\ResponseInterface $response
    ) {
        $this->config = $config;
        $this->order = $order;
        $this->logger = $logger_interface;

        $this->_redirect = $redirect;
        $this->_response = $response;

        $this->_actionFlag = $actionFlag;
    }

    public function execute(Observer $observer)
    {
        $orderId = $observer->getEvent()->getOrderIds();
        $order = $this->order->load($orderId[0]);

        $this->logger->debug('#AfterPlaceOrder orkestapay_cards');

        return $this;
    }
}
