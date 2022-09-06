<?php
/**
 * This module is used for real time processing of
 * Novalnet payment module of customers.
 * This free contribution made by request.
 * 
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet
 * All rights reserved. https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */

namespace Novalnet\Providers;

use Plenty\Plugin\Templates\Twig;
use Novalnet\Helper\PaymentHelper;
use Novalnet\Services\PaymentService;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;

/**
 * Class NovalnetOrderConfirmationDataProvider
 *
 * @package Novalnet\Providers
 */
class NovalnetOrderConfirmationDataProvider
{
    /**
     * Setup the Novalnet transaction comments for the requested order
     *
     * @param Twig $twig
     * @param PaymentRepositoryContract $paymentRepositoryContract
     * @param Arguments $arg
     * 
     * @return string
     */
    public function call(Twig $twig, PaymentRepositoryContract $paymentRepositoryContract, $arg)
    {
        $order = $arg[0];
        $paymentHelper = pluginApp(PaymentHelper::class);
        $paymentService = pluginApp(PaymentService::class);
        if(!empty($order['id'])) {
            // Loads the payments for an order
            $payments = $paymentRepositoryContract->getPaymentsByOrderId($order['id']);
            foreach($payments as $payment) {
                // Check it is Novalnet Payment method order
                if($paymentHelper->getPaymentKeyByMop($payment->mopId)) {
                    // Get Novalnet transaction details from the Novalnet database table
                    $nnDbTxDetails = $paymentService->getDatabaseValues($orderId);
                    $paymentHelper->logger('nnDbTxDetails', $nnDbTxDetails);
                }
            }
        }
    }
}
