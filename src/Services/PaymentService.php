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
     * @var redirectPayment
     */
    private $redirectPayment = ['NOVALNET_IDEAL'];
    
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
        // Get the customer billing and shipping details
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
		
		// Build the custom parameters
		$paymentRequestData['custom'] = [
										   'lang' => strtoupper($this->sessionStorage->getLocaleSettings()->language)
										];
        
        // Build additional specific payment method request parameters
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
        $paymentRequestData['transaction']['payment_type'] = $this->getNnPaymentType($paymentKey);
        if($paymentKey == 'NOVALNET_INVOICE') {
            $invoiceDueDate = $this->settingsService->getNnPaymentSettingsValue('due_date', strtolower($paymentKey));
            if(is_numeric($invoiceDueDate)) {
                $paymentRequestData['transaction']['due_date'] = $this->paymentHelper->dateFormatter($invoiceDueDate);
            }
        }
        
        if($paymentKey == 'NOVALNET_IDEAL') {
            $paymentRequestData['transaction']['return_url'] = $this->getReturnPageUrl();
        }
    }
    
    public function getNnPaymentType($paymentKey)
    {
        $paymentMethodType = [
            'NOVALNET_INVOICE' => 'INVOICE',
            'NOVALNET_IDEAL' => 'IDEAL'
        ];
        
        return $paymentMethodType[$paymentKey];
    }
    
    /**
     * Check if the payment is redirection or not
     *
     * @param string $paymentKey
     * @param bool $doRedirect
     *
     */
    public function isRedirectPayment($paymentKey) {
        return (bool) (in_array($paymentKey, $this->redirectPayment));
    }
    
    /**
     * Get the payment response controller URL to be handled
     *
     * @return string
     */
    private function getReturnPageUrl()
    {   
        return $this->webstoreHelper->getCurrentWebstoreConfiguration()->domainSsl . '/' . $this->sessionStorage->getLocaleSettings()->language . '/payment/novalnet/paymentResponse/';
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
        $paymentKey = $this->sessionStorage->getPlugin()->getValue('paymentkey');
        $this->getLogger(__METHOD__)->error('request', $paymentRequestData);
        $payment_access_key = $this->settingsService->getNnPaymentSettingsValue('novalnet_private_key');
        $paymentResponseData = $this->paymentHelper->executeCurl($paymentRequestData, NovalnetConstants::PAYMENT_URL, $payment_access_key);
        $isPaymentSuccess = isset($paymentResponseData['result']['status']) && $paymentResponseData['result']['status'] == 'SUCCESS';
        $this->getLogger(__METHOD__)->error('response', $paymentResponseData);
        
        // if the payment method is redirect
        if($this->isRedirectPayment($paymentKey)) {
            // Do redirect if the redirect URL is present
            if (!empty($paymentResponseData['result']['redirect_url']) && !empty($paymentResponseData['transaction']['txn_secret'])) {
                // Transaction secret used for the later checksum verification
                $this->sessionStorage->getPlugin()->setValue('response', $paymentResponseData);
                header('Location: ' . $paymentResponseData['result']['redirect_url']);
		exit;
            } else {
                $this->pushNotification($paymentResponseData['result']['status_text'], 'error', 100);
            }
        }
        
        // Push notification to customer regarding the payment response
        if($isPaymentSuccess) {
            $this->pushNotification($paymentResponseData['result']['status_text'], 'success', 100);
        } else {
            $this->pushNotification($paymentResponseData['result']['status_text'], 'error', 100);
        }
        
        // Set the payment response in the session for the further processings
        $this->sessionStorage->getPlugin()->setValue('nnPaymentData', array_merge($paymentRequestData, $paymentResponseData));
    }
    
    /**
     * Push notification
     *
     */
    public function pushNotification($message, $type, $code = 0) {
        
    $notifications = json_decode($this->sessionStorage->getPlugin()->getValue('notifications'), true);  
        
    $notification = [
            'message'       => $message,
            'code'          => $code,
            'stackTrace'    => []
           ];
        
    $lastNotification = $notifications[$type];

    if(!is_null($lastNotification)) {
            $notification['stackTrace'] = $lastNotification['stackTrace'];
            $lastNotification['stackTrace'] = [];
            array_push( $notification['stackTrace'], $lastNotification );
        }
        $notifications[$type] = $notification;
        $this->sessionStorage->getPlugin()->setValue('notifications', json_encode($notifications));
    }
	
    /**
     * Validate the checksum generated for redirection payments
     *
     * @param  array  $paymentResponseData
     * 
     * @return array
     */
    public function validateChecksumAndGetTxnStatus($paymentResponseData)
    {
        if ($paymentResponseData['status'] && $paymentResponseData['status'] == 'SUCCESS') {
            
            $txnSecret = $this->sessionStorage->getPlugin()->getValue('nnTxnSecret');
	     $res = $this->sessionStorage->getPlugin()->getValue('response');
        $this->getLogger(__METHOD__)->error('check res', $res);
	    
            $strRevPrivateKey = $this->paymentHelper->reverseString($this->settingsService->getNnPaymentSettingsValue('novalnet_private_key'));
           
            // Condition to check whether the payment is redirect
            if (!empty($paymentResponseData['checksum']) && !empty($paymentResponseData['tid']) && !empty($txnSecret)) {                            
                $generatedChecksum = hash('sha256', $paymentResponseData['tid'] . $txnSecret . $paymentResponseData['status'] . $strRevPrivateKey);
                $this->getLogger(__METHOD__)->error('generated checksum', $generatedChecksum);
                // If the checksum isn't matching, there could be a possible manipulation in the data received 
                if ($generatedChecksum !== $paymentResponseData['checksum']) {
                    $checksumInvalidMsg = $this->paymentHelper->getTranslatedText('checksum_error');                                  
                    $this->pushNotification($checksumInvalidMsg, 'error', 100);
		    exit;
                }
            }
                                          
            $paymentRequestData = [];
            $paymentRequestData['transaction']['tid'] = $paymentResponseData['tid'];
            
            $privatekey = $this->settingsService->getNnPaymentSettingsValue('novalnet_private_key');
                
            return $this->paymentHelper->executeCurl($paymentRequestData, NovalnetConstants::TXN_RESPONSE_URL, $privatekey);
            
        } else {
            $this->pushNotification($paymentResponseData['status_text'], 'error', 100);
	    exit;
        }                  
    }
    
}
