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
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
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
     * Constructor.
     *
     * @param SettingsService $settingsService
     * @param PaymentHelper $paymentHelper
     * @param WebstoreHelper $webstoreHelper
     */
    public function __construct(SettingsService $settingsService,
                                PaymentHelper $paymentHelper,
                                WebstoreHelper $webstoreHelper
                               )
    {
        $this->settingsService = $settingsService;
        $this->paymentHelper = $paymentHelper;
        $this->webstoreHelper           = $webstoreHelper;
        
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
    
}
