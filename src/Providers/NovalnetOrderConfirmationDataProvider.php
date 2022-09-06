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
     * @param Arguments $arg
     * 
     * @return string
     */
    public function call(Twig $twig, $arg)
    {
        $order = $arg[0];
        $paymentHelper = pluginApp(PaymentHelper::class);
        if(!empty($order['id'])) {
            // Check it is Novalnet Payment method order
            if($paymentHelper->getPaymentKeyByMop($payment->mopId)) {
                // Get Novalnet transaction details from the Novalnet database table
                $nnDbTxDetails = $paymentService->getDatabaseValues($orderId);
                $paymentHelper->logger('nnDbTxDetails', $nnDbTxDetails);
            }
        }
    }
}
