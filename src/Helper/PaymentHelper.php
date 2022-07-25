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
use \Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Plugin\Translation\Translator;
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
     * @var AddressRepositoryContract
     */
    private $addressRepository;
    
    /**
     * @var CountryRepositoryContract
     */
    private $countryRepository;
    
    /**
     * Constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param AddressRepositoryContract $addressRepository
     * @param CountryRepositoryContract $countryRepository
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository,
                                AddressRepositoryContract $addressRepository,
                                CountryRepositoryContract $countryRepository
                               )
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->addressRepository       = $addressRepository;
        $this->countryRepository       = $countryRepository;
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
     *
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
    
    /**
     * Get billing/shipping address by its id
     *
     * @param int $addressId
     *
     * @return object
     */
    public function getCustomerBillingOrShippingAddress(int $addressId)
    {
        try {
            /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
            $authHelper = pluginApp(AuthHelper::class);
            $addressDetails = $authHelper->processUnguarded(function () use ($addressId) {
                //unguarded
               return $this->addressRepository->findAddressById($addressId);
            });
            return $addressDetails;
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error('Novalnet::getCustomerBillingOrShippingAddress', $e);
        }
    }
    
    /**
     * Get the required billing and shipping details
     *
     * @param object $billingAddress
     * @param object $shippingAddress
     * @return array
     */
    public function getRequiredBillingShippingDetails($billingAddress, $shippingAddress)
    {
        $billingShippingDetails['billing'] = [
                                               'street'       => $billingAddress->street,
                                               'house_no'     => $billingAddress->houseNumber,
                                               'city'         => $billingAddress->town,
                                               'zip'          => $billingAddress->postalCode,
                                               'country_code' => $this->countryRepository->findIsoCode($billingAddress->countryId, 'iso_code_2'),
                                             ];
        
        $billingShippingDetails['shipping'] = [
                                                'street'       => $shippingAddress->street,
                                                'house_no'     => $shippingAddress->houseNumber,
                                                'city'         => $shippingAddress->town,
                                                'zip'          => $shippingAddress->postalCode,
                                                'country_code' => $this->countryRepository->findIsoCode($shippingAddress->countryId, 'iso_code_2'),
                                              ];
        
        return $billingShippingDetails;
    }
    
    /**
     * Retrieves the original end-customer address with and without proxy
     *
     * @return string
     */
    public function getRemoteAddress()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key)
        {
            if (array_key_exists($key, $_SERVER) === true)
            {
                foreach (explode(',', $_SERVER[$key]) as $ip)
                {
                    return $ip;
                }
            }
        }
    }
    
    public function ConvertAmountToSmallerUnit($amount) {
        return sprintf('%0.2f', $amount) * 100;
    }
    
     /**
    * Get the customized translated text for the Novalnet key
    *
    * @param string $key
    * @param string $lang
    *
    * @return string
    */
    public function getCustomizedTranslatedText($key, $lang = null)
    {
        $translator = pluginApp(Translator::class);

        return $lang == null ? $translator->trans("Novalnet::Customize.$key") : $translator->trans("Novalnet::Customize.$key", [], $lang);
    }
}
