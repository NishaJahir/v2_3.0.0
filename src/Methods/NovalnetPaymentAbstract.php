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

use Plenty\Modules\Payment\Method\Services\PaymentMethodBaseService;
use Plenty\Plugin\ConfigRepository;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
//use Novalnet\Services\PaymentService;
use Novalnet\Helper\PaymentHelper;
use Novalnet\Services\SettingsService;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Plugin\Log\Loggable;

/**
 * Class NovalnetPaymentAbstract
 *
 * @package Novalnet\Methods
 */
abstract class NovalnetPaymentAbstract extends PaymentMethodBaseService
{
    use Loggable;
    
	const PAYMENT_CODE = 'plenty_novalnet';
	
    /** 
     * @var BasketRepositoryContract 
     */
    private $basketRepository;

    /** @var  ConfigRepository */
    private $configRepository;
	
    /**
     * @var PaymentHelper
     */
    private $paymentHelper;
    
    /**
     * @var SettingsService
     */
    protected $settingsService;
    
    /**
     * NovalnetPaymentAbstract constructor.
     *
     * @param BasketRepositoryContract $configRepository
     * @param ConfigRepository $config
     * @param PaymentService $paymentService
     * @param SettingsService $settingsService
     */
    public function __construct(BasketRepositoryContract $basketRepository,
                                ConfigRepository $configRepository,
                                //PaymentService $paymentService,
				PaymentHelper $paymentHelper,
                                SettingsService $settingsService
                               )
    {
        $this->basketRepository = $basketRepository->load();
        $this->configRepository = $configRepository;
        //$this->paymentService  = $paymentService;
	$this->paymentHelper = $paymentHelper;
        $this->settingsService  = $settingsService;
    }
    
    /**
     * Check the configuration if the payment method is active
     * Return true only if the payment method is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
       foreach($this->paymentHelper->getPaymentMethodsKey() as $paymentMethodKey) {
	    $is_payment_active = $this->settingsService->getNnPaymentSettingsValue('payment_active', strtolower($paymentMethodKey));
	       $this->getLogger(__METHOD__)->error('find'.$paymentMethodKey, $is_payment_active);
	    if($is_payment_active) {
		return true;    
	    }
	    return false;
       }
        
	    
	    return true;
    
    }

    /**
     * Get the name of the payment method. The name can be entered in the multilingualism.
     *
     * @return string
     */
    public function getName(string $lang = 'de'): string
    {   
       return 'test';
    }
	
	/**
     * Return an additional payment fee for the payment method.
     *
     * @return float
     */
    public function getFee(): float
    {
        return 0.00;
    }
    
    /**
     * Retrieves the icon of the payment. The URL can be entered in the configuration.
     *
     * @return string
     */
    public function getIcon(string $lang = 'de'): string
    {
        return '';
    }
    
    /**
     * Retrieves the description of the payment. The description can be entered in the configuration.
     *
     * @return string
     */
    public function getDescription(string $lang = 'de'): string
    {
       return '';
    }

    /**
     * Check if it is allowed to switch to this payment method
     *
     * @return bool
     */
    public function isSwitchableTo($orderId = null): bool
    {
        return false;
    }

    /**
     * Check if it is allowed to switch from this payment method
     *
     * @param int $orderId
     * @return bool
     */
    public function isSwitchableFrom($orderId = null): bool
    {
		return false;
    }
     
     /**
     * Check if this payment method should be active in the back end.
     *
     * @return bool
     */
    public function isBackendActive(): bool
    {
        return $this->isActive();
    }
    
    /**
     * Get the name for the back end.
     *
     * @param string $lang
     * @return string
     */
    public function getBackendName(string $lang = 'de'): string
    {
        return $this->getName($lang);
    }
    
    /**
     * Return the icon for the back end, shown in the payments UI.
     *
     * @return string
     */
    public function getBackendIcon(): string
    {
        return '';
    }
}
