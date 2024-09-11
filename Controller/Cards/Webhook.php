<?php

/**
 * @category    Payments
 * @package     Orkestapay_Stores
 * @author      Federico Balderas
 * @copyright   Orkestapay (http://orkestapay.com)
 * @license     http://www.apache.org/licenses/LICENSE-2.0  Apache License Version 2.0
 */

namespace Orkestapay\Cards\Controller\Cards;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Orkestapay\Cards\Model\Payment as OrkestapayPayment;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Magento\Sales\Model\Order\Invoice;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Exception;

/**
 * Webhook class
 */
class Webhook extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $request;
    protected $payment;
    protected $logger;
    protected $invoiceService;
    protected $transactionRepository;
    protected $searchCriteriaBuilder;

    public function __construct(
        Context $context,
        \Magento\Framework\App\Request\Http $request,
        OrkestapayPayment $payment,
        \Psr\Log\LoggerInterface $logger_interface,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->payment = $payment;
        $this->logger = $logger_interface;
        $this->invoiceService = $invoiceService;
        $this->transactionRepository = $transactionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Load the page defined in view/frontend/layout/orkestapay_index_webhook.xml
     * URL /orkestapay/index/webhook
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $this->logger->debug('#webhook-cards');
        try {
            $body = file_get_contents('php://input');
            $json = json_decode($body);

            header('HTTP/1.1 200 OK');
        } catch (\Exception $e) {
            $this->logger->error('#webhook-cards-Exception', array('msg' => $e->getMessage(), 'code' => $e->getCode()));
            header("HTTP/1.0 500 Server Error");
        }
        exit;
    }

    /**
     * Create exception in case CSRF validation failed.
     * Return null if default exception will suffice.
     *
     * @param RequestInterface $request
     * @link https://magento.stackexchange.com/questions/253414/magento-2-3-upgrade-breaks-http-post-requests-to-custom-module-endpoint
     *
     * @return InvalidRequestException|null
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     * @SuppressWarnings(PMD.UnusedFormalParameter)
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
