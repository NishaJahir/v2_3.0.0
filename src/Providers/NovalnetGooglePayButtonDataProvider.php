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
use Plenty\Modules\Listing\ShippingProfile\Contracts\ShippingProfileRepositoryContract;
use Plenty\Modules\Listing\ShippingProfile\Events\ShippingProfileEvent;
use Plenty\Modules\Listing\ShippingProfile\Models\ShippingProfile;

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
     * @param Arguments $arg
     * 
     * @return string
     */
    public function call(Twig $twig, 
                         BasketRepositoryContract $basketRepository, 
                         BasketItemRepositoryContract $basketItem, 
                         ShippingProfileRepositoryContract $shippingProfileRepository,
                         ShippingProfile $shippingProfile,
                         $arg)
    {
        $basket = $basketRepository->load();
        $paymentHelper = pluginApp(PaymentHelper::class);
        
        $paymentHelper->logger('bas', $basket);
        $paymentHelper->logger('basket Item123', $basketItem->all());
        
        
        
        $basketitemDetails = $basketItem->all();
        $paymentHelper->logger('shipping profile', $shippingProfileRepository->get($basketitemDetails[1]->shippingProfileId));
        $paymentHelper->logger('shipping ID', $basketitemDetails[1]->shippingProfileId);
        
        
       return $twig->render('Novalnet::NovalnetGooglePayButton', ['basketDetails' => $basket]);
    }
}
