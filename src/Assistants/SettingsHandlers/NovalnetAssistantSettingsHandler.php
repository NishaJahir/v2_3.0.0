<?php


namespace Novalnet\Assistants\SettingsHandlers;

use Plenty\Modules\Plugin\PluginSet\Contracts\PluginSetRepositoryContract;
use Plenty\Modules\Wizard\Contracts\WizardSettingsHandler;
use Novalnet\Helper\PaymentHelper;
use Novalnet\Services\SettingsService;

/**
 * Class NovalnetAssistantSettingsHandler
 *
 * @package Novalnet\Assistants\SettingsHandlers
 */
class NovalnetAssistantSettingsHandler implements WizardSettingsHandler
{   
    public function handle(array $postData)
    {
        /** @var PluginSetRepositoryContract $pluginSetRepo */
        $pluginSetRepo = pluginApp(PluginSetRepositoryContract::class);
        
        /** @var PaymentHelper $paymentHelper */
        $paymentHelper = pluginApp(PaymentHelper::class);
        
        $clientId = $postData['data']['clientId'];
        $pluginSetId = $pluginSetRepo->getCurrentPluginSetId();
        
        $data = $postData['data'];
        
        // Novalnet Global and Webhook Configuration values
        $novalnetSettings = [
            'novalnet_merchant_id' => $data['novalnetMerchantId'] ?? '',
            'novalnet_auth_Code' => $data['novalnetAuthCode'] ?? '',
            'novalnet_product_id' => $data['novalnetProductId'] ?? '',
            'novalnet_tariff_id' => $data['novalnetTariffId'] ?? '',
            'novalnet_access_key' => $data['novalnetAccessKey'] ?? '',
            'novalnet_client_key' => $data['novalnetClientKey'] ?? '',
            'novalnet_webhook_testmode' => $data['novalnetWebhookTestMode'] ?? '',
            'novalnet_webhook_email_to' => $data['novalnetWebhookEmailTo'] ?? '',
        ];
        
        // Payment method common configuration values
		foreach($paymentHelper->getPaymentMethodsKey() as $paymentMethodKey) {
			$paymentKey = str_replace('_','',ucwords(strtolower($paymentMethodKey),'_'));
			$paymentKey[0] = strtolower($paymentKey[0]);
			$paymentMethodKey = strtolower($paymentMethodKey);
			
			$novalnetSettings[$paymentMethodKey]['payment_active'] = $data[$paymentKey. 'PaymentActive'] ?? '';
			$novalnetSettings[$paymentMethodKey]['test_mode'] = $data[$paymentKey. 'TestMode'] ?? '';
			$novalnetSettings[$paymentMethodKey]['payment_log'] = $data[$paymentKey. 'PaymentLogo'] ?? '';
			$novalnetSettings[$paymentMethodKey]['minimum_order_amount'] = $data[$paymentKey. 'MinimumOrderAmount'] ?? '';
			$novalnetSettings[$paymentMethodKey]['maximum_order_amount'] = $data[$paymentKey. 'MaximumOrderAmount'] ?? '';
			$novalnetSettings[$paymentMethodKey]['allowed_country'] = $data[$paymentKey. 'AllowedCountry'] ?? '';
			
			switch ($paymentMethodKey) {
				case 'novalnet_cc':
					$novalnetSettings[$paymentMethodKey]['enforce'] = $data[$paymentKey. 'Enforce'] ?? '';
					$novalnetSettings[$paymentMethodKey]['inline_form'] = $data[$paymentKey. 'InlineForm'] ?? '';
					$novalnetSettings[$paymentMethodKey]['logos'] = $data[$paymentKey. 'Logos'] ?? '';
					$novalnetSettings[$paymentMethodKey]['one_click_shopping'] = $data[$paymentKey. 'OneClickShoppping'] ?? '';
					$novalnetSettings[$paymentMethodKey]['standard_style_label'] = $data[$paymentKey. 'StandardStyleLabel'] ?? '';
					$novalnetSettings[$paymentMethodKey]['standard_style_field'] = $data[$paymentKey. 'StandardStyleField'] ?? '';
					$novalnetSettings[$paymentMethodKey]['standard_style_css'] = $data[$paymentKey. 'StandardStyleCss'] ?? '';
					$novalnetSettings[$paymentMethodKey]['payment_action'] = $data[$paymentKey. 'PaymentAction'] ?? '';
					$novalnetSettings[$paymentMethodKey]['onhold_amount'] = $data[$paymentKey. 'OnHold'] ?? '';
					break;
				case 'novalnet_invoice':
					$novalnetSettings[$paymentMethodKey]['due_date'] = $data[$paymentKey. 'Duedate'] ?? '';
					$novalnetSettings[$paymentMethodKey]['payment_action'] = $data[$paymentKey. 'PaymentAction'] ?? '';
					$novalnetSettings[$paymentMethodKey]['onhold_amount'] = $data[$paymentKey. 'OnHold'] ?? '';
					break;
			}
		}
        
        /** @var SettingsService $settingsService */
        $settingsService = pluginApp(SettingsService::class);
        $settingsService->createOrUpdateNovalnetConfigurationSettings($novalnetSettings, $clientId, $pluginSetId);
        
        return true;
    }
}
