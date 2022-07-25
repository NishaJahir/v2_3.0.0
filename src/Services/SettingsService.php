<?php

namespace Novalnet\Services;

use Carbon\Carbon;
use Novalnet\Models\Settings;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Plenty\Modules\Plugin\PluginSet\Contracts\PluginSetRepositoryContract;
use Plenty\Plugin\Application;
use Plenty\Plugin\Log\Loggable;

/**
 * Class SettingsService
 *
 * @package Novalnet\Services\SettingsService
 */
class SettingsService
{ 
	    use Loggable;

       /**
       * @var DataBase
       */
       protected $database;

       /**
       * SettingsService constructor.
       * @param DataBase $database
       * @param CachingRepository $cachingRepository
       */
	public function __construct(DataBase $database)
	{
	    $this->database = $database;
	}
    
	public function getNovalnetSettings($clientId, $pluginSetId)
	{
		if (is_null($clientId)) {
	    /** @var Application $application */
	    $application = pluginApp(Application::class);
	    $clientId = $application->getPlentyId();
	}

	if (is_null($pluginSetId)) {
	    /** @var PluginSetRepositoryContract $pluginSetRepositoryContract */
	    $pluginSetRepositoryContract = pluginApp(PluginSetRepositoryContract::class);
	    $pluginSetId = $pluginSetRepositoryContract->getCurrentPluginSetId();
	}

	/** @var Settings[] $setting */
	$setting = $this->database->query(Settings::class)->where('clientId', '=', $clientId)
							  ->where('pluginSetId', '=', $pluginSetId)
							  ->get();
		
		
	}

	public function createOrUpdateNovalnetConfigurationSettings($data, $clientId, $pluginSetId)
	{
	    $novalnetSettings = $this->getNovalnetSettings($clientId, $pluginSetId);
            if (!$novalnetSettings instanceof Settings) {
		    /** @var Settings $settings */
		    $novalnetSettings = pluginApp(Settings::class);
		    $novalnetSettings->clientId = $clientId;
		    $novalnetSettings->pluginSetId = $pluginSetId;
		    $novalnetSettings->createdAt = (string)Carbon::now();
            }
	    $novalnetSettings = $novalnetSettings->updateValues($data);
            $this->getLogger(__METHOD__)->error('db123', $novalnetSettings);
	    return $novalnetSettings;
	}
	
	public function create($data)
    {
		$thsi->getLogger(__METHOD__)->error('create', $data);
        $this->clientId = $data['clientId'];
        $this->pluginSetId = $data['pluginSetId'];
        $this->createdAt = (string)Carbon::now();

        $this->value = [
            'novalnet_merchant_id' => $data['novalnet_merchant_id'],
            'novalnet_auth_Code' => $data['novalnet_auth_Code'],
            'novalnet_product_id' => $data['novalnet_product_id'],
            'novalnet_tariff_id' => $data['novalnet_tariff_id'],
            'novalnet_access_key' => $data['novalnet_access_key'],
            'novalnet_client_key' => $data['novalnet_client_key'],
            'novalnet_webhook_testmode' => $data['novalnet_webhook_testmode'],
            'novalnet_webhook_email_to' => $data['novalnet_webhook_email_to'],
            'novalnet_cc' => $data['novalnet_cc'],
            'novalnet_invoice' => $data['novalnet_invoice'],
            'novalnet_ideal' => $data['novalnet_ideal']
        ];

        return $this->save();
    }
    
    public function save()
    {
	    $thsi->getLogger(__METHOD__)->error('save', $data);
        $this->updatedAt = (string)Carbon::now();

        return $this->database->save($this);
    }
    
    public function updateValues($data)
    {
	    $thsi->getLogger(__METHOD__)->error('update', $data);
		if (isset($data['novalnet_merchant_id'])) {
            $this->value['novalnet_merchant_id'] = $data['novalnet_merchant_id'];
        }
		if (isset($data['novalnet_auth_Code'])) {
            $this->value['novalnet_auth_Code'] = $data['novalnet_auth_Code'];
        }
		if (isset($data['novalnet_product_id'])) {
            $this->value['novalnet_product_id'] = $data['novalnet_product_id'];
        }
		if (isset($data['novalnet_tariff_id'])) {
            $this->value['novalnet_tariff_id'] = $data['novalnet_tariff_id'];
        }
		if (isset($data['novalnet_access_key'])) {
            $this->value['novalnet_access_key'] = $data['novalnet_access_key'];
        }
		if (isset($data['novalnet_client_key'])) {
            $this->value['novalnet_client_key'] = $data['novalnet_client_key'];
        }
		if (isset($data['novalnet_webhook_testmode'])) {
            $this->value['novalnet_webhook_testmode'] = $data['novalnet_webhook_testmode'];
        }
		if (isset($data['novalnet_webhook_email_to'])) {
            $this->value['novalnet_webhook_email_to'] = $data['novalnet_webhook_email_to'];
        }
		if (isset($data['novalnet_cc'])) {
            $this->value['novalnet_cc'] = $data['novalnet_cc'];
        }
		if (isset($data['novalnet_invoice'])) {
            $this->value['novalnet_invoice'] = $data['novalnet_invoice'];
        }
		if (isset($data['novalnet_ideal'])) {
            $this->value['novalnet_ideal'] = $data['novalnet_ideal'];
        }
        return $this->database->save($this);
		
	}
}

