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

namespace Novalnet\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Novalnet\Services\PaymentService;
use Novalnet\Services\SettingsService;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Plugin\Log\Loggable;

/**
 * Class PaymentController
 *
 * @package Novalnet\Controllers
 */
class PaymentController extends Controller
{
    use Loggable;
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;
    
    /**
     * @var PaymentService
     */
    private $paymentService;
    
    /**
     * @var SettingsService
    */
    private $settingsService;
    
    /**
     * @var FrontendSessionStorageFactoryContract
     */
    private $sessionStorage;

    /**
     * PaymentController constructor.
     *
     * @param Request $request
     * @param Response $response
     * @param PaymentService $paymentService
     * @param SettingsService $settingsService
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     */
    public function __construct(  Request $request,
                                  Response $response,
                                  PaymentService $paymentService,
                                  SettingsService $settingsService,
                                  FrontendSessionStorageFactoryContract $sessionStorage
                                )
    {

        $this->request         = $request;
        $this->response        = $response;
        $this->paymentService  = $paymentService;
        $this->settingsService = $settingsService;
        $this->sessionStorage  = $sessionStorage;
    }

    /**
     * Novalnet redirects to this page if the payment was executed successfully
     *
     */
    public function paymentResponse() {
        
        // Get the initial payment call response
        $paymentResponseData = $this->request->all();
        $this->getLogger(__METHOD__)->error('initial response', $paymentResponseData);
        
        // Checksum validation for redirects
        if(!empty($paymentResponseData['tid'])) {
            
            // Checksum validation and transaction status call to retrieve the full response
            $paymentResponseData = $this->paymentService->validateChecksumAndGetTxnStatus($paymentResponseData);
            
            $this->getLogger(__METHOD__)->error('redirect response', $paymentResponseData);
            
            $isPaymentSuccess = isset($paymentResponseData['result']['status']) && $paymentResponseData['result']['status'] == 'SUCCESS';
            
            if($isPaymentSuccess) {
                $this->paymentService->pushNotification($paymentResponseData['result']['status_text'], 'success', 100);
            } else {
                $this->paymentService->pushNotification($paymentResponseData['result']['status_text'], 'error', 100);    
            }
            
            // Set the payment response in the session for the further processings
            $this->sessionStorage->getPlugin()->setValue('nnPaymentData', $paymentResponseData);
            
        } else {
            $this->paymentService->pushNotification($paymentResponseData['status_text'], 'error', 100);  
            return $this->response->redirectTo($this->sessionStorage->getLocaleSettings()->language . '/confirmation');
        }
       
        return $this->response->redirectTo($this->sessionStorage->getLocaleSettings()->language . '/confirmation');
    }
    
    
}
