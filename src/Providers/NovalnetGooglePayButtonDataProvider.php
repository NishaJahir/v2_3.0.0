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
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Novalnet\Helper\PaymentHelper;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Novalnet\Services\PaymentService;

/**
 * Class NovalnetGooglePayButtonDataProvider
 *
 * @package Novalnet\Providers
 */
class NovalnetGooglePayButtonDataProvider
{
    /**
     * Setup the Novalnet transaction comments for the requested order
     *
     * @param Twig $twig
     * @param BasketRepositoryContract $basketRepository
     * @param BasketItemRepositoryContract $basketItem
     * @param Arguments $arg
     * 
     * @return string
     */
    public function call(Twig $twig, 
                         BasketRepositoryContract $basketRepository, 
                         BasketItemRepositoryContract $basketItem,
                         $arg)
    {
        $basket = $basketRepository->load();
        $paymentHelper = pluginApp(PaymentHelper::class);
        $sessionStorage = pluginApp(FrontendSessionStorageFactoryContract::class);
        $paymentService = pluginApp(PaymentService::class);
        
        // Get the order total basket amount
        $orderAmount = $paymentHelper->ConvertAmountToSmallerUnit($basket->basketAmount);
        // Gte the order language
        $orderLang = strtoupper($sessionStorage->getLocaleSettings()->language);
        
        // Render the Google Pay button
       return $twig->render('Novalnet::NovalnetGooglePayButton', ['countryCode' => 'DE', 'orderTotalAmount' => $orderAmount, 'orderLang' => $orderLang, 'orderCurrency' => $basket->currency, 'nnPaymentProcessUrl' => $paymentService->getProcessPaymentUrl()]);
    }
}
