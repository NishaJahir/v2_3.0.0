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
use Novalnet\Services\PaymentService;
use Novalnet\Helper\PaymentHelper;
use Novalnet\Services\SettingsService;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Plugin\Application;
use Plenty\Plugin\Translation\Translator;
use Plenty\Plugin\Log\Loggable;

/**
 * Class NovalnetPaymentAbstract
 *
 * @package Novalnet\Methods
 */
abstract class NovalnetPaymentAbstract extends PaymentMethodBaseService
{
    use Loggable;
    
    const PAYMENT_KEY = 'Novalnet';
    
    /** 
     * @var BasketRepositoryContract 
     */
    private $basketRepository;

    /** @var  ConfigRepository */
    private $configRepository;
    
    /**
     * @var PaymentService
     */
    private $paymentService;
    
    /**
     * @var PaymentHelper
     */
    private $paymentHelper;
    
    /**
     * @var SettingsService
     */
    private $settingsService;
    
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
                                PaymentService $paymentService,
                                PaymentHelper $paymentHelper,
                                SettingsService $settingsService
                               )
    {
        $this->basketRepository = $basketRepository->load();
        $this->configRepository = $configRepository;
        $this->paymentService  = $paymentService;
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
        $isPaymentActive = $this->settingsService->getNnPaymentSettingsValue('payment_active', strtolower($this::PAYMENT_KEY));

        if($isPaymentActive) {
            // Check if the payment allowed for mentioned countries
            $activatePaymentAllowedCountry = true;
            if ($allowedCountry = $this->settingsService->getNnPaymentSettingsValue('allowed_country', strtolower($this::PAYMENT_KEY))) {
                $activatePaymentAllowedCountry  = $this->paymentService->allowedCountries($this->basketRepository, $allowedCountry);
            }
            
            // Check if the Minimum order amount value met to payment display condition
            $activatePaymentMinimumAmount = true;
            $minimumAmount = trim($this->settingsService->getNnPaymentSettingsValue('minimum_order_amount', strtolower($this::PAYMENT_KEY)));
            if (!empty($minimumAmount) && is_numeric($minimumAmount)) {
                $activatePaymentMinimumAmount = $this->paymentService->getMinBasketAmount($this->basketRepository, $minimumAmount);
            }
            
            // Check if the Maximum order amount value met to payment display condition
            $activatePaymentMaximumAmount = true;
            $maximumAmount = trim($this->settingsService->getNnPaymentSettingsValue('maximum_order_amount', strtolower($this::PAYMENT_KEY)));
            if (!empty($maximumAmount) && is_numeric($maximumAmount)) {
                $activatePaymentMaximumAmount = $this->paymentService->getMaxBasketAmount($this->basketRepository, $maximumAmount);
            }
            
            return (bool) ($this->paymentService->isMerchantConfigurationValid() && $activatePaymentAllowedCountry && $activatePaymentMinimumAmount && $activatePaymentMaximumAmount);
        }
            return false;
    }

    /**
     * Get the name of the payment method. The name can be entered in the multilingualism.
     *
     * @return string
     */
    public function getName(string $lang = 'de'): string
    {   
            $paymentMethodKey = str_replace('_','',ucwords(strtolower($this::PAYMENT_KEY),'_'));
            $paymentMethodKey[0] = strtolower($paymentMethodKey[0]);
          
            /** @var Translator $translator */
            $translator = pluginApp(Translator::class);
            return $translator->trans('Novalnet::Customize.'. $paymentMethodKey, [], $lang);
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
        $logoUrl = $this->settingsService->getNnPaymentSettingsValue('payment_logo', strtolower($this::PAYMENT_KEY));
        if(empty($logoUrl)){
            /** @var Application $app */
            $app = pluginApp(Application::class);
            $logoUrl = $app->getUrlPath('novalnet') .'/images/'. strtolower($this::PAYMENT_KEY) .'.png';
        } 
        return $logoUrl;
    }
    
    /**
     * Retrieves the description of the payment. The description can be entered in the configuration.
     *
     * @return string
     */
    public function getDescription(string $lang = 'de'): string
    {
            $paymentMethodKey = str_replace('_','',ucwords(strtolower($this::PAYMENT_KEY),'_'));
            $paymentMethodKey[0] = strtolower($paymentMethodKey[0]);
          
            /** @var Translator $translator */
            $translator = pluginApp(Translator::class);
            return $translator->trans('Novalnet::Customize.'. $paymentMethodKey .'Desc', [], $lang);
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
        return 'Novalnet:' . $this->getName($lang);
    }
    
    /**
     * Return the icon for the back end, shown in the payments UI.
     *
     * @return string
     */
    public function getBackendIcon(): string
    {
        $app = pluginApp(Application::class);
        $icon = $app->getUrlPath('novalnet') . '/images/logos/' . strtolower($this::PAYMENT_KEY) .'_backend_icon.svg';
        return $icon;
    }
}
