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

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Novalnet\Helper\PaymentHelper;
use Novalnet\Services\PaymentService;
use Plenty\Modules\Wizard\Contracts\WizardContainerContract;
use Novalnet\Assistants\NovalnetAssistant;
use Novalnet\Methods\NovalnetPaymentAbstract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * Class NovalnetServiceProvider
 *
 * @package Novalnet\Providers
 */
class NovalnetServiceProvider extends ServiceProvider
{
    use Loggable;

    /**
     * Register the route service provider
     */
    public function register()
    {
        $this->getApplication()->register(NovalnetRouteServiceProvider::class);
    }

    /**
     * Boot additional services for the payment method
     * 
     * @param Dispatcher $eventDispatcher
     * @param BasketRepositoryContract $basket
     * @param PaymentMethodContainer $payContainer
     */
    public function boot(Dispatcher $eventDispatcher,
                        BasketRepositoryContract $basketRepository,
                        PaymentMethodContainer $payContainer,
                        PaymentHelper $paymentHelper, 
			PaymentService $paymentService,
                        FrontendSessionStorageFactoryContract $sessionStorage
                        )
    {
        $this->registerPaymentMethods($payContainer);
        
        $this->registerPaymentRendering($eventDispatcher, $basketRepository, $paymentHelper, $paymentService);

        $this->registerPaymentExecute($eventDispatcher, $paymentHelper, $paymentService, $sessionStorage);
        
        pluginApp(WizardContainerContract::class)->register('payment-novalnet-assistant', NovalnetAssistant::class);
    }
     
    /**
     * Register the Novalnet payment methods in the payment method container
     *
     * @param PaymentMethodContainer $payContainer
     */
    protected function registerPaymentMethods(PaymentMethodContainer $payContainer)
    {
        foreach(PaymentHelper::getPaymentMethods() as $paymentMethodKey => $paymentMethodClass) {
            $payContainer->register('plenty_novalnet::' . $paymentMethodKey, $paymentMethodClass,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        }
    }
    
    
    /**
     * Rendering the Novalnet payment method content
     *
     * @param Dispatcher $eventDispatcher
     * @param BasketRepositoryContract $basketRepository
     * @param PaymentHelper $paymentHelper
     * @param PaymentService $paymentService
     */
    protected function registerPaymentRendering(Dispatcher $eventDispatcher,
                                              BasketRepositoryContract $basketRepository,
                                              PaymentHelper $paymentHelper,
                                              PaymentService $paymentService
                                              )
    {
        // Listen for the event that gets the payment method content
        $eventDispatcher->listen(
            GetPaymentMethodContent::class, 
            function(GetPaymentMethodContent $event) use($basketRepository, $paymentHelper, $sessionStorage) {
				
			if($paymentHelper->getPaymentKeyByMop($event->getMop())) {
				$paymentKey = $paymentHelper->getPaymentKeyByMop($event->getMop());
				if(in_array($paymentKey, ['NOVALNET_INVOICE'])) {
					 $content = '';
                     $contentType = 'continue';
                     $paymentRequestData = $paymentService->getRequestParameters($basketRepository->load(), $paymentKey);
					 if(empty($paymentRequestData['customer']['first_name']) && empty($paymentRequestData['customer']['last_name'])) {
							$content = $paymentHelper->getTranslatedText('nn_first_last_name_error');
							$contentType = 'errorCode';   
                     }
                     $sessionStorage->getPlugin()->setValue('nnPaymentData', $paymentRequestData);
				}
				
				$event->setValue($content);
				$event->setType($contentType);
			}
			
                        
        });
    
    }
    
     /**
     * Execute the Novalnet payment method
     *
     * @param Dispatcher $eventDispatcher
     * @param PaymentHelper $paymentHelper
     * @param PaymentService $paymentService
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     */
    protected function registerPaymentExecute(Dispatcher $eventDispatcher,
                                               PaymentHelper $paymentHelper,
                                               PaymentService $paymentService,
                                               FrontendSessionStorageFactoryContract $sessionStorage
                                              )
    {
        // Listen for the event that executes the payment
        $eventDispatcher->listen(
			ExecutePayment::class,
            function (ExecutePayment $event) use ($paymentHelper, $paymentService, $sessionStorage)
            {
                if($paymentHelper->getPaymentKeyByMop($event->getMop())) {
                    $sessionStorage->getPlugin()->setValue('nnOrderNo',$event->getOrderId());
                    $sessionStorage->getPlugin()->setValue('mop',$event->getMop());
                    $paymentKey = $paymentHelper->getPaymentKeyByMop($event->getMop());
                    $sessionStorage->getPlugin()->setValue('paymentkey', $paymentKey);
                    $paymentService->performServerCall();
                    $paymentService->validateResponse();
                }
            });
    
    }
}
