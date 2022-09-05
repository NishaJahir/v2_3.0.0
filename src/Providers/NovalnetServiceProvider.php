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
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Novalnet\Helper\PaymentHelper;
use Novalnet\Services\PaymentService;
use Plenty\Modules\Wizard\Contracts\WizardContainerContract;
use Novalnet\Assistants\NovalnetAssistant;
use Novalnet\Methods\NovalnetPaymentAbstract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Novalnet\Constants\NovalnetConstants;
use Plenty\Plugin\Templates\Twig;
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
     * @param BasketRepositoryContract $basketRepository
     * @param PaymentMethodContainer $payContainer
     * @param PaymentHelper $paymentHelper
     * @param PaymentService $paymentService
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param Twig $twig
     */
    public function boot(Dispatcher $eventDispatcher,
                        BasketRepositoryContract $basketRepository,
                        PaymentMethodContainer $payContainer,
                        PaymentHelper $paymentHelper, 
                        PaymentService $paymentService,
                        FrontendSessionStorageFactoryContract $sessionStorage,
			Twig $twig
                        )
    {
        $this->registerPaymentMethods($payContainer);
        
        $this->registerPaymentRendering($eventDispatcher, $basketRepository, $paymentHelper, $paymentService, $sessionStorage, $twig);

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
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param Twig $twig
     */
    protected function registerPaymentRendering(Dispatcher $eventDispatcher,
                                              BasketRepositoryContract $basketRepository,
                                              PaymentHelper $paymentHelper,
                                              PaymentService $paymentService,
                                              FrontendSessionStorageFactoryContract $sessionStorage,
					      Twig $twig
                                              )
    {
        // Listen for the event that gets the payment method content
        $eventDispatcher->listen(
            GetPaymentMethodContent::class, 
            function(GetPaymentMethodContent $event) use($basketRepository, $paymentHelper, $paymentService, $sessionStorage, $twig) {
                
            if($paymentHelper->getPaymentKeyByMop($event->getMop())) {
                $paymentKey = $paymentHelper->getPaymentKeyByMop($event->getMop());
                $paymentRequestData = $paymentService->generatePaymentParams($basketRepository->load(), $paymentKey);
                if(empty($paymentRequestData['paymentRequestData']['customer']['first_name']) && empty($paymentRequestData['paymentRequestData']['customer']['last_name'])) {
                    $content = $paymentHelper->getTranslatedText('nn_first_last_name_error');
                    $contentType = 'errorCode';   
                } else {
		    $showBirthday = (!isset($paymentRequestData['paymentRequestData']['customer']['birth_date']) ||  (time() < strtotime('+18 years', strtotime($paymentRequestData['paymentRequestData']['customer']['birth_date'])))) ? true : false;
			
                    if(in_array($paymentKey, ['NOVALNET_INVOICE', 'NOVALNET_PREPAYMENT', 'NOVALNET_CASHPAYMENT', 'NOVALNET_MULTIBANCO']) || $paymentService->isRedirectPayment($paymentKey)  || $showBirthday == false) {
                        $content = '';
                        $contentType = 'continue';
                    } elseif($paymentKey == 'NOVALNET_SEPA') {
			$content = $twig->render('Novalnet::PaymentForm.NovalnetSepa', [
								'nnPaymentProcessUrl' => $paymentService->getProcessPaymentUrl(),
								'paymentMopKey' =>  $paymentKey,
								'paymentName' => $paymentHelper->getCustomizedTranslatedText('template_' . strtolower($paymentKey)), 
								]);
			$contentType = 'htmlContent';
	            } elseif($paymentKey == 'NOVALNET_GUARANTEED_INVOICE' && $showBirthday == true) {
			$content = $twig->render('Novalnet::PaymentForm.NovalnetGuaranteedInvoice', [
								    'nnPaymentProcessUrl' => $paymentService->getProcessPaymentUrl(),
								    'paymentMopKey' =>  $paymentKey,
								    'paymentName' => $paymentHelper->getCustomizedTranslatedText('template_' . strtolower($paymentKey)),
								    ]);
                        $contentType = 'htmlContent';
		   }
                }
                $sessionStorage->getPlugin()->setValue('nnPaymentData', $paymentRequestData);
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
                    $paymentResponseData = $paymentService->performServerCall();
                    if($paymentService->isRedirectPayment($paymentKey)) {
                        if(!empty($paymentResponseData) && !empty($paymentResponseData['result']['redirect_url']) && !empty($paymentResponseData['transaction']['txn_secret'])) {
                            // Transaction secret used for the later checksum verification
                            $sessionStorage->getPlugin()->setValue('nnTxnSecret', $paymentResponseData['transaction']['txn_secret']);
                            $event->setType('redirectUrl');
                            $event->setValue($paymentResponseData['result']['redirect_url']);
                        } else {
                           // Handle an error case and set the return type and value for the event.
                              $event->setType('error');
                              $event->setValue('The payment could not be executed!');
                        }
                    }
                }
            });
    }
}
