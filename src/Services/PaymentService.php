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
use Novalnet\Services\TransactionService;
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
     * @var TransactionService
     */
    private $transactionService;
    
    /**
     * @var redirectPayment
     */
    private $redirectPayment = ['NOVALNET_APPLEPAY', 'NOVALNET_IDEAL', 'NOVALNET_SOFORT', 'NOVALNET_GIROPAY', 'NOVALNET_PRZELEWY24', 'NOVALNET_EPS', 'NOVALNET_PAYPAL', 'POSTFINANCE_CARD', 'POSTFINANCE_EFINANCE', 'NOVALNET_BANCONTACT', 'NOVALNET_ONLINE_BANK_TRANSFER', 'NOVALNET_ALIPAY', 'NOVALNET_WECHAT_PAY', 'NOVALNET_TRUSTLY', 'NOVALNET_GOOGLEPAY'];
    
    /**
     * Constructor.
     *
     * @param SettingsService $settingsService
     * @param PaymentHelper $paymentHelper
     * @param WebstoreHelper $webstoreHelper
     * @param AddressRepositoryContract $addressRepository
     * @param CountryRepositoryContract $countryRepository
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param TransactionService $transactionService
     */
    public function __construct(SettingsService $settingsService,
                                PaymentHelper $paymentHelper,
                                WebstoreHelper $webstoreHelper,
                                AddressRepositoryContract $addressRepository,
                                CountryRepositoryContract $countryRepository,
                                FrontendSessionStorageFactoryContract $sessionStorage,
                                TransactionService $transactionService
                               )
    {
        $this->settingsService = $settingsService;
        $this->paymentHelper = $paymentHelper;
        $this->webstoreHelper = $webstoreHelper;
        $this->addressRepository  = $addressRepository;
        $this->countryRepository  = $countryRepository;
        $this->sessionStorage  = $sessionStorage;
        $this->transactionService = $transactionService;
        
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
        $paymentUrl = $this->getPaymentData($paymentRequestData, $paymentKey);
    
    return [
        'paymentRequestData' => $paymentRequestData,
        'paymentUrl' => $paymentUrl
    ];
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
        $paymentUrl = NovalnetConstants::PAYMENT_URL;
    
        // Sent the payment authorize call to Novalnet server if the authorization is enabled
        if(in_array($paymentKey, ['NOVALNET_INVOICE', 'NOVALNET_CC']) && !empty($this->settingsService->getNnPaymentSettingsValue('payment_action', strtolower($paymentKey)))) {
            // Limit for the manual on-hold
            $authorizeAmount = $this->settingsService->getNnPaymentSettingsValue('onhold_amount', strtolower($paymentKey));

            // "Authorization" activated if the manual limit is configured and the order amount exceeds it 
            if(!empty($authorizeAmount) && is_numeric($authorizeAmount) && $paymentRequestData['transaction']['amount'] > $authorizeAmount) {
               $paymentUrl = NovalnetConstants::PAYMENT_AUTHORIZE_URL;
            }
        }
        
        $paymentRequestData['transaction']['payment_type'] = $this->getNnPaymentType($paymentKey);
        
        // Send due date to the Novalnet server if it configured
        if(in_array($paymentKey, ['NOVALNET_INVOICE', 'NOVALNET_SEPA'])) {
            $dueDate = $this->settingsService->getNnPaymentSettingsValue('due_date', strtolower($paymentKey));
            if(is_numeric($dueDate)) {
                $paymentRequestData['transaction']['due_date'] = $this->paymentHelper->dateFormatter($dueDate);
            }
        }
        
        if($this->isRedirectPayment($paymentKey)) {
            $paymentRequestData['transaction']['return_url'] = $this->getReturnPageUrl();
        }
        
        return $paymentUrl;
    }
    
    public function getNnPaymentType($paymentKey)
    {
        $paymentMethodType = [
            'NOVALNET_SEPA' => 'DIRECT_DEBIT_SEPA',
            'NOVALNET_CC' => 'CREDITCARD',
            'NOVALNET_APPLEPAY' => 'APPLEPAY',
            'NOVALNET_INVOICE' => 'INVOICE',
            'NOVALNET_PREPAYMENT' => 'PREPAYMENT',
            'NOVALNET_GUARANTEED_INVOICE' => 'GUARANTEED_INVOICE',
            'NOVALNET_GUARANTEED_SEPA' => 'GUARANTEED_DIRECT_DEBIT_SEPA',
            'NOVALNET_IDEAL' => 'IDEAL',
            'NOVALNET_SOFORT' => 'ONLINE_TRANSFER',
            'NOVALNET_GIROPAY' => 'GIROPAY',
            'NOVALNET_CASHPAYMENT' => 'CASHPAYMENT',
            'NOVALNET_PRZELEWY' => 'PRZELEWY24',
            'NOVALNET_EPS' => 'EPS',
            'NOVALNET_PAYPAL' => 'PAYPAL',
            'NOVALNET_POSTFINANCE_CARD' => 'POSTFINANCE_CARD',
            'NOVALNET_POSTFINANCE_EFINANCE' => 'POSTFINANCE',
            'NOVALNET_BANCONTACT' => 'BANCONTACT',
            'NOVALNET_MULTIBANCO' => 'MULTIBANCO',
            'NOVALNET_ONLINE_BANK_TRANSFER' => 'ONLINE_BANK_TRANSFER',
            'NOVALNET_ALIPAY' => 'ALIPAY',
            'NOVALNET_WECHAT_PAY' => 'WECHATPAY',
            'NOVALNET_TRUSTLY' => 'TRUSTLY',
            'NOVALNET_GOOGLEPAY' => 'GOOGLEPAY'
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
        $paymentRequestData['paymentRequestData']['transaction']['order_no'] = $this->sessionStorage->getPlugin()->getValue('nnOrderNo');
        $paymentKey = $this->sessionStorage->getPlugin()->getValue('paymentkey');
        $privateKey = $this->settingsService->getNnPaymentSettingsValue('novalnet_private_key');
        $this->getLogger(__METHOD__)->error('NN request', $paymentRequestData);
        $paymentResponseData = $this->paymentHelper->executeCurl($paymentRequestData['paymentRequestData'], $paymentRequestData['paymentUrl'], $privateKey);
         $this->getLogger(__METHOD__)->error('NN request', $paymentRequestData);
        $isPaymentSuccess = isset($paymentResponseData['result']['status']) && $paymentResponseData['result']['status'] == 'SUCCESS';
        
    
        
        // Do redirect if the redirect URL is present
        if($isPaymentSuccess && $this->isRedirectPayment($paymentKey)) {
            return $paymentResponseData;
        } else {
            // Push notification to customer regarding the payment response
            if($isPaymentSuccess) {
                $this->pushNotification($paymentResponseData['result']['status_text'], 'success', 100);
            } else {
                $this->pushNotification($paymentResponseData['result']['status_text'], 'error', 100);
            }
        
        // Set the payment response in the session for the further processings
            $this->sessionStorage->getPlugin()->setValue('nnPaymentData', array_merge($paymentRequestData['paymentRequestData'], $paymentResponseData));
            // Handle the further process to the order based on the payment response
            $this->HandlePaymentResponse();
        }
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
            
            $nnTxnSecret = $this->sessionStorage->getPlugin()->getValue('nnTxnSecret');
            $strRevPrivateKey = $this->paymentHelper->reverseString($this->settingsService->getNnPaymentSettingsValue('novalnet_private_key'));
           
            // Condition to check whether the payment is redirect
            if (!empty($paymentResponseData['checksum']) && !empty($paymentResponseData['tid']) && !empty($nnTxnSecret)) {                            
                $generatedChecksum = hash('sha256', $paymentResponseData['tid'] . $nnTxnSecret . $paymentResponseData['status'] . $strRevPrivateKey);
                // If the checksum isn't matching, there could be a possible manipulation in the data received 
                if ($generatedChecksum !== $paymentResponseData['checksum']) {                              
                    $paymentResponseData['nn_checksum_invalid'] = $this->paymentHelper->getTranslatedText('checksum_error');
                    return $paymentResponseData;
                }
            }
                                          
            $paymentRequestData = [];
            $paymentRequestData['transaction']['tid'] = $paymentResponseData['tid'];
            
            $privatekey = $this->settingsService->getNnPaymentSettingsValue('novalnet_private_key');
            return $this->paymentHelper->executeCurl($paymentRequestData, NovalnetConstants::TXN_RESPONSE_URL, $privatekey);
        } else {
            $paymentResponseData['nn_checksum_invalid'] = $paymentResponseData['status_text'];
            return $paymentResponseData;
        }                  
    }
    
    public function HandlePaymentResponse()
    {
        $nnPaymentData = $this->sessionStorage->getPlugin()->getValue('nnPaymentData');
        $this->sessionStorage->getPlugin()->setValue('nnPaymentData', null);
        
        $nnPaymentData['mop']            = $this->sessionStorage->getPlugin()->getValue('mop');
        $nnPaymentData['payment_method'] = strtolower($this->paymentHelper->getPaymentKeyByMop($nnPaymentData['mop']));
        
        $this->getLogger(__METHOD__)->error('final process', $nnPaymentData);
        
        // Insert payment response into Novalnet table
        $this->insertPaymentResponseIntoNnDb($nnPaymentData);
        
        // Create a plenty payment to the order
        $this->paymentHelper->createPlentyPaymentToNnOrder($nnPaymentData);
    }
    
    public function insertPaymentResponseIntoNnDb($paymentResponseData)
    {
        $additionalInfo = $this->additionalPaymentInfo($paymentResponseData);
        
         $transactionData = [
            'order_no'         => $paymentResponseData['transaction']['order_no'],
            'amount'           => $paymentResponseData['transaction']['amount'],
            'callback_amount'  => $paymentResponseData['transaction']['amount'],
            'tid'              => $paymentResponseData['transaction']['tid'] ?? 0,
            'ref_tid'          => $paymentResponseData['transaction']['tid'] ?? 0,
            'payment_name'     => $paymentResponseData['payment_method'],
            'additional_info'  => $additionalInfo ?? 0,
        ];
        
        if($transactionData['payment_name'] == 'NOVALNET_INVOICE' || $paymentResponseData['result']['status'] != 'SUCCESS') {
            $transactionData['callback_amount'] = 0;
        }

        $this->transactionService->saveTransaction($transactionData);
    }
    
    public function additionalPaymentInfo($paymentResponseData)
    {
        $lang = strtolower((string)$paymentResponseData['custom']['lang']);
        
        $additionalInfo = [
                            'currency' => $paymentResponseData['transaction']['currency'] ?? 0,
                            'test_mode' => !empty($paymentResponseData['transaction']['test_mode']) ? $this->paymentHelper->getTranslatedText('test_order',$lang) : 0,
                            'plugin_version' => $paymentResponseData['transaction']['system_version'] ?? NovalnetConstants::PLUGIN_VERSION,
                          ];
                          
        if($paymentResponseData['result']['status'] == 'SUCCESS' && $paymentResponseData['payment_method'] == 'NOVALNET_INVOICE') {
            $additionalInfo['account_holder'] = ['transaction']['bank_details']['account_holder'];
            $additionalInfo['iban'] = ['transaction']['bank_details']['iban'];
            $additionalInfo['bic'] = ['transaction']['bank_details']['bic'];
            $additionalInfo['bank_name'] = ['transaction']['bank_details']['bank_name'];
            $additionalInfo['bank_place'] = ['transaction']['bank_details']['bank_place'];
        }
        return json_encode($additionalInfo);
    }
    
   public function isGuaranteePaymentToBeDisplayed(Basket $basket, $paymentKey)
   {
        try {
            if(!is_null($basket) && $basket instanceof Basket && !empty($basket->customerInvoiceAddressId)) {
                // Check if the guaranteed payment method is enabled
                if($this->settingsService->getNnPaymentSettingsValue('payment_active', $paymentKey) == true) {

                    // Get the customer billing and shipping details
                    if(!empty($basket->customerInvoiceAddressId)) {
                       $billingAddress = $this->paymentHelper->getCustomerBillingOrShippingAddress((int) $basket->customerInvoiceAddressId);
                       $shippingAddress = $billingAddress; 
                    }
                    
                    if(!empty($basket->customerShippingAddressId)) {
                        $shippingAddress = $this->paymentHelper->getCustomerBillingOrShippingAddress((int) $basket->customerShippingAddressId);
                    }

                    // Get the billing and shipping details
                    $billingShippingDetails = $this->paymentHelper->getRequiredBillingShippingDetails($billingAddress, $shippingAddress);
                    
                    // Set the minimum guaranteed amount
                    $configuredMinimumGuaranteedAmount = $this->settingsService->getNnPaymentSettingsValue('minimum_guaranteed_amount', $paymentKey);

                    $minimumGuaranteedAmount = !empty($configuredMinimumGuaranteedAmount) ? $configuredMinimumGuaranteedAmount : 999;
                    
                    /** @var \Plenty\Modules\Frontend\Services\VatService $vatService */
                    $vatService = pluginApp(\Plenty\Modules\Frontend\Services\VatService::class);

                    //we have to manipulate the basket because its stupid and doesnt know if its netto or gross
                    if(!count($vatService->getCurrentTotalVats())) {
                        $basket->itemSum = $basket->itemSumNet;
                        $basket->shippingAmount = $basket->shippingAmountNet;
                        $basket->basketAmount = $basket->basketAmountNet;
                    }
                    
                    
                       return 'guarantee';  
                  

                    // Further we check if the normal payment method can be enabled if the condition not met 
                    if ($this->settingsService->getNnPaymentSettingsValue('force', $paymentKey) == true) {
                        return 'normal';
                    }

                    // If none matches, error message displayed 
                    return 'error'; 
                }
                // If payment guarantee is not enabled, we show default one 
                return 'normal';
            }
            // If payment guarantee is not enabled, we show default one 
            return 'normal';
        } catch(\Exception $e) {
            $this->getLogger(__METHOD__)->error('Novalnet::isGuaranteePaymentToBeDisplayedFailed', $e);
        }
    }
    
    /**
     * Returning the list of the European Union countries for checking the country code of Guaranteed customer 
     *     
     * @return array
     */
    public function getEuropeanRegionCountryCodes()
    {
        return ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'UK', 'CH'];
    }
    
    /**
    * Get the direct payment process controller URL to be handled
    *
    * @return string
    */
    public function getProcessPaymentUrl()
    {
        return $this->webstoreHelper->getCurrentWebstoreConfiguration()->domainSsl . '/' . $this->sessionStorage->getLocaleSettings()->language . '/payment/novalnet/processPayment/';
    }
}
