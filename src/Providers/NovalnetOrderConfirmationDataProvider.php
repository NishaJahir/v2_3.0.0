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
        $transactionComment = '';
        if(!empty($order['id'])) {
            // Loads the payments for an order
            $payments = $paymentRepositoryContract->getPaymentsByOrderId($order['id']);
            foreach($payments as $payment) {
                // Check it is Novalnet Payment method order
                if($paymentHelper->getPaymentKeyByMop($payment->mopId)) {
                    
                    // Load the order property and get the required details
                    $orderProperties = $payment->properties;
                    foreach($orderProperties as $orderProperty) {
                        if ($orderProperty->typeId == 21) { // Loads the bank details from the payment object for previous payment plugin versions
                            $invoiceDetails = $orderProperty->value;
                        }
                        if ($orderProperty->typeId == 30) { // Load the transaction status
                            $txStatus = $orderProperty->value;
                        }
                        if ($orderProperty->typeId == 22) { // Loads the cashpayment comments from the payment object for previous payment plugin versions
                            $cashpaymentComments = $orderProperty->value;
                        }
                    }
                    
                    // Get Novalnet transaction details from the Novalnet database table
                    $nnDbTxDetails = $paymentService->getDatabaseValues($order['id']);
                    $paymentHelper->logger('nnDbTxDetails', $nnDbTxDetails);
                    
                    // Get the transaction status as string for the previous payment plugin version
                    $nnDbTxDetails['tx_status'] = $paymentService->getTxStatusAsString($txStatus, $nnDbTxDetails['payment_id']);
                    
                    // Set the cashpayment comments into array
                    $nnDbTxDetails['cashpayment_comments'] = $cashpaymentComments ?? '';
                    
                    // Form the Novalnet transaction comments
                    $transactionComments = $paymentService->formTransactionComments($nnDbTxDetails);
                    
                    $transactionComment .= (string) $transactionComments;
                    $transactionComment .= PHP_EOL;
                }
            }
        }
        
        // Replace PHP_EOL as break tag for the alignment
        $transactionComment = str_replace(PHP_EOL, '<br>', $transactionComment);
        
        // Render the transaction comments
        return $twig->render('Novalnet::NovalnetOrderHistory', ['transactionComments' => html_entity_decode($transactionComment)]);
    }
}
