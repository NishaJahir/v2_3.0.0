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

namespace Novalnet\Methods;


/**
 * Class NovalnetGuaranteedInvoicePaymentMethod
 *
 * @package Novalnet\Methods
 */
class NovalnetGuaranteedInvoicePaymentMethod extends NovalnetPaymentAbstract
{
    const PAYMENT_KEY = 'NOVALNET_GUARANTEED_INVOICE';
    
    public function __construct(BasketRepositoryContract $basketRepository,
                                PaymentService $paymentService
                                
                               )
    {
        $this->basketRepository = $basketRepository->load();
        
        $this->paymentService  = $paymentService;
        
    }
    
    public function isActive(): bool
    {
        $s =  $this->paymentService->isGuaranteePaymentToBeDisplayed($this->basketRepository, 'novalnet_guaranteed_invoice');
        $this->getLogger(__METHOD__)->error('active789', $s);
    }
}
