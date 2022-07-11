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

namespace Novalnet\Helper;

use Novalnet\Methods\NovalnetCcPaymentMethod;
use Novalnet\Methods\NovalnetInvoicePaymentMethod;
use Novalnet\Methods\NovalnetIdealPaymentMethod;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * Class PaymentHelper
 *
 * @package Novalnet\Helper
 */
class PaymentHelper
{
    use Loggable;

    /**
     *
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;
    
    /**
     * Constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }
    
    /**
     * Get the payment method class
     * 
     * @return array
     */
    public static function getPaymentMethods()
    {
        return [
            NovalnetCcPaymentMethod::PAYMENT_KEY => NovalnetCcPaymentMethod::class,
            NovalnetInvoicePaymentMethod::PAYMENT_KEY => NovalnetInvoicePaymentMethod::class,
            NovalnetIdealPaymentMethod::PAYMENT_KEY => NovalnetIdealPaymentMethod::class,
        ];
    }
    
    /**
     * Load the ID of the payment method
     * Return the ID for the payment method found
     * 
     * @param string $paymentKey
     * @return string|int
     */
    public function getPaymentMethodByKey($paymentKey)
    {
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('plenty_novalnet');
        
        if(!is_null($paymentMethods))
        {
            foreach($paymentMethods as $paymentMethod)
            {
                if($paymentMethod->paymentKey == $paymentKey)
                {
                    return [$paymentMethod->id, $paymentMethod->paymentKey, $paymentMethod->name];
                }
            }
        }
        return 'no_paymentmethod_found';
    }
    
     /**
     * Get the payment method class
     * 
     * @return array
     */
    public function getPaymentMethodsKey()
    {
        return [
            NovalnetCcPaymentMethod::PAYMENT_KEY,
            NovalnetInvoicePaymentMethod::PAYMENT_KEY,
            NovalnetIdealPaymentMethod::PAYMENT_KEY
        ];
    }
}
