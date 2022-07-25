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

namespace Novalnet\Services;

use Plenty\Modules\Basket\Models\Basket;
use Novalnet\Services\SettingsService;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Frontend\Services\AccountService;
use Novalnet\Helper\PaymentHelper;
use Novalnet\Constants\NovalnetConstants;
use Plenty\Modules\Helper\Services\WebstoreHelper;
use Plenty\Plugin\Log\Loggable;

/**
 * Class PaymentService
 *
 * @package Novalnet\Services
 */
class PaymentService
{
    use Loggable;
    
    /**
     * @var SettingsService
    */
    private $settingsService;
    
    /**
     * @var PaymentHelper
     */
    private $paymentHelper;
    
    /**
     * @var WebstoreHelper
     */
    private $webstoreHelper;
	
    /**
     * @var AddressRepositoryContract
     */
    private $addressRepository;
    
    /**
     * @var CountryRepositoryContract
     */
    private $countryRepository;
    
    /**
     * @var FrontendSessionStorageFactoryContract
     */
    private $sessionStorage;
    
    /**
     * Constructor.
     *
     * @param SettingsService $settingsService
     * @param PaymentHelper $paymentHelper
     * @param WebstoreHelper $webstoreHelper
     * @param AddressRepositoryContract $addressRepository
     * @param CountryRepositoryContract $countryRepository
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     */
    public function __construct(SettingsService $settingsService,
                                PaymentHelper $paymentHelper,
                                WebstoreHelper $webstoreHelper,
				AddressRepositoryContract $addressRepository,
                                CountryRepositoryContract $countryRepository,
                                FrontendSessionStorageFactoryContract $sessionStorage
                               )
    {
        $this->settingsService = $settingsService;
        $this->paymentHelper = $paymentHelper;
        $this->webstoreHelper = $webstoreHelper;
	$this->addressRepository  = $addressRepository;
        $this->countryRepository  = $countryRepository;
        $this->sessionStorage  = $sessionStorage;
        
    }
    
    /**
     * Build payment parameters to server
     *
     * @param  Basket $basket
     * @param  string $paymentKey
     * 
     * @return array
     */
    public function generatePaymentParams(Basket $basket, $paymentKey = '')
    {
        $billingAddressId = $basket->customerInvoiceAddressId;
        $shippingAddressId = $basket->customerShippingAddressId;
        $billingAddress = $this->paymentHelper->getCustomerBillingOrShippingAddress((int) $billingAddressId);
        $shippingAddress = $billingAddress;
        if(!empty($shippingAddressId)) {
            $shippingAddress = $this->paymentHelper->getCustomerBillingOrShippingAddress((int) $shippingAddressId);
        }
        
        // Get the customer name if the salutation as Person
        $customerName = $this->getCustomerName($billingAddress);
        
        // Get the customerId
        $account = pluginApp(AccountService::class);
        $customerId = $account->getAccountContactId();
        
        // Get the testMode value
        $paymentKeyLower = strtolower((string) $paymentKey);
        $testModeKey = $this->settingsService->getNnPaymentSettingsValue('test_mode', $paymentKeyLower);
        
        /** @var \Plenty\Modules\Frontend\Services\VatService $vatService */
        $vatService = pluginApp(\Plenty\Modules\Frontend\Services\VatService::class);

        //we have to manipulate the basket because its stupid and doesnt know if its netto or gross
        if(!count($vatService->getCurrentTotalVats())) {
            $basket->itemSum = $basket->itemSumNet;
            $basket->shippingAmount = $basket->shippingAmountNet;
            $basket->basketAmount = $basket->basketAmountNet;
        }
        
        // Build the Payment Request Parameters
        $paymentRequestData = [];
        
        // Building the merchant Data
        $paymentRequestData['merchant'] = [
                                            'signature'    => $this->settingsService->getNnPaymentSettingsValue('novalnet_public_key'),
                                            'tariff'       => $this->settingsService->getNnPaymentSettingsValue('novalnet_tariff_id')
                                          ];
                                          
        // Building the customer Data
        $paymentRequestData['customer'] = [
                                            'first_name'   => $billingAddress->firstName ?? $customerName['firstName'],
                                            'last_name'    => $billingAddress->lastName ?? $customerName['lastName'],
                                            'gender'       => $billingAddress->gender ?? 'u',
                                            'email'        => $billingAddress->email,
                                            'customer_no'  => $customerId ?? 'guest',
                                            'customer_ip'  => $this->paymentHelper->getRemoteAddress()
                                          ];
        
        if (!empty($billingAddress->phone)) { // Check if phone field is given
            $paymentRequestData['customer']['tel'] = $billingAddress->phone;
        }
        
        // Obtain the required billing and shipping details from the customer address object        
        $billingShippingDetails = $this->paymentHelper->getRequiredBillingShippingDetails($billingAddress, $shippingAddress);
        
        $paymentRequestData['customer'] = array_merge($paymentRequestData['customer'], $billingShippingDetails);
        
        // If the billing and shipping are equal, we notify it too 
        if ($paymentRequestData['customer']['billing'] == $paymentRequestData['customer']['shipping']) {
            $paymentRequestData['customer']['shipping']['same_as_billing'] = '1';
        }
        
        if (!empty($billingAddress->companyName)) { // Check if company field is given in the billing address
            $paymentRequestData['customer']['billing']['company'] = $billingAddress->companyName;
        }
        
        if (!empty($shippingAddress->companyName)) { // Check if company field is given in the shipping address
            $paymentRequestData['customer']['shipping']['company'] = $shippingAddress->companyName;
        }
        
        if (!empty($billingAddress->state)) { // Check if state field is given in the billing address
            $paymentRequestData['customer']['billing']['state'] = $billingAddress->state;
        }
        
        if (!empty($shippingAddress->state)) { // Check if state field is given in the shipping address
            $paymentRequestData['customer']['shipping']['state'] = $shippingAddress->state;
        }
        
        // Building the transaction Data
        $paymentRequestData['transaction'] = [
                                               'test_mode' => ($testModeKey == true) ? 1 : 0,
                                               'amount'    => $this->paymentHelper->ConvertAmountToSmallerUnit($basket->basketAmount),
                                               'currency'  => $basket->currency,
                                               'system_name'   => 'Plentymarkets',
                                               'system_version' => NovalnetConstants::PLUGIN_VERSION,
                                               'system_url' => $this->webstoreHelper->getCurrentWebstoreConfiguration()->domainSsl,
                                               'system_ip'  => $_SERVER['SERVER_ADDR']
                                             ];
        
		$this->getPaymentData($paymentRequestData, $paymentKey);
		    
        return $paymentRequestData;
    }

    /**
     * Get customer name if the salutation as Person
     *
     * @param object $billingAddress
     *
     * @return array
     */
    public function getCustomerName($billingAddress) 
    {
        foreach ($billingAddress->options as $option) {
            if ($option->typeId == 12) {
                    $customerName = $option->value;
            }
        }
        $customerName = explode(' ', $customerName);
        $firstName = $customerName[0];
            if( count( $customerName ) > 1 ) {
                unset($customerName[0]);
                $lastName = implode(' ', $customerName);
            } else {
                $lastName = $firstName;
            }
        $firstName = empty ($firstName) ? $lastName : $firstName;
        $lastName = empty ($lastName) ? $firstName : $lastName;
        return ['firstName' => $firstName, 'lastName' => $lastName];
    }
    
    public function getPaymentData(&$paymentRequestData, $paymentKey)
    {
		if($paymentKey == 'NOVALNET_INVOICE') {
			$paymentRequestData['transaction']['payment_type'] = 'INVOICE';
			$invoiceDueDate = $this->settingsService->getNnPaymentSettingsValue('due_date', strtolower($paymentKey));
			if(is_numeric($invoiceDueDate)) {
				$paymentRequestData['transaction']['due_date'] = $this->paymentHelper->dateFormatter($invoiceDueDate);
			}
		}
	}
    
    /**
     * Check if the merchant details configured
     *
     * @return bool
     */
    public function isMerchantConfigurationValid()
    {
		return (bool) ($this->settingsService->getNnPaymentSettingsValue('novalnet_public_key') != '' && $this->settingsService->getNnPaymentSettingsValue('novalnet_private_key') != '' && $this->settingsService->getNnPaymentSettingsValue('novalnet_tariff_id') != '');
	}
    
    /**
     * Show payment for allowed countries
     *
     * @param string $allowed_country
     *
     * @return bool
     */
    public function allowedCountries(Basket $basket, $allowed_country) 
    {
        $allowed_country = str_replace(' ', '', strtoupper($allowed_country));
        $allowed_country_array = explode(',', $allowed_country);    
        try {
            if (! is_null($basket) && $basket instanceof Basket && !empty($basket->customerInvoiceAddressId)) {         
                $billingAddressId = $basket->customerInvoiceAddressId;              
                $billingAddress = $this->paymentHelper->getCustomerBillingOrShippingAddress((int) $billingAddressId);
                $country = $this->countryRepository->findIsoCode($billingAddress->countryId, 'iso_code_2');
                if(!empty($billingAddress) && !empty($country) && in_array($country,$allowed_country_array)) {                             
                        return true;
                }
            }
        } catch(\Exception $e) {
            return false;
        }
        return false;
    }
    
    /**
     * Show payment for Minimum Order Amount
     *
     * @param object $basket
     * @param int $minimum_amount
     *
     * @return bool
     */
    public function getMinBasketAmount(Basket $basket, $minimum_amount) 
    {   
        if (!is_null($basket) && $basket instanceof Basket) {
            $amount = $this->paymentHelper->ConvertAmountToSmallerUnit($basket->basketAmount);
            if (!empty($minimum_amount) && $minimum_amount<=$amount)    {
                return true;
            }
        } 
        return false;
    }
    
    /**
     * Show payment for Maximum Order Amount
     *
     * @param object $basket
     * @param int $maximum_amount
     *
     * @return bool
     */
    public function getMaxBasketAmount(Basket $basket, $maximum_amount) 
    {   
        if (!is_null($basket) && $basket instanceof Basket) {
            $amount = $this->paymentHelper->ConvertAmountToSmallerUnit($basket->basketAmount);
            if (!empty($maximum_amount) && $maximum_amount>=$amount)    {
            
                return true;
            }
        } 
        return false;
    }
	
    public function performServerCall()
    {
	 $paymentRequestData = $this->sessionStorage->getPlugin()->getValue('nnPaymentData');
        $paymentRequestData['transaction']['order_no'] = $this->sessionStorage->getPlugin()->getValue('nnOrderNo');
        $payment_access_key = $this->settingsService->getNnPaymentSettingsValue('novalnet_private_key');
        $response = $this->paymentHelper->executeCurl($paymentRequestData, NovalnetConstants::PAYMENT_URL, $payment_access_key);
    }
}
