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
	    $novalnetSettings = $novalnetSettings->update($data);
            $this->getLogger(__METHOD__)->error('db123', $novalnetSettings);
	    return $novalnetSettings;
	}
	
	public function getNnPaymentSettingsValue($settingsKey, $paymentKey, $clientId = null, $pluginSetId = null)
    	{
		$settings = $this->getNnSettings($clientId, $pluginSetId);
		$settingsKey = $paymentKey ? $paymentKey . '_' . $settingsKey : $settingsKey;
		
		if(!is_null($settings)) {
			if(isset($settings->value[$settingsKey])) {
				return $settings->value[$settingsKey];
			}
		}
                
        	return null;
	}
	
}

