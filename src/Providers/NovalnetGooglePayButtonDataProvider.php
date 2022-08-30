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
    public function call(Twig $twig, BasketRepositoryContract $basketRepository, $arg)
    {
        $basket = $basketRepository->load();
        
       return $twig->render('Novalnet::NovalnetGooglePayButton', ['basketDetails' => $basket]);
    }
}
