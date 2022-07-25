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
    
	public function getNnSettings($clientId = null, $pluginSetId = null)
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
		
		
		return $setting[0];
	}

	public function createOrUpdateNovalnetConfigurationSettings($data, $clientId, $pluginSetId)
	{
	    $novalnetSettings = $this->getNnSettings($clientId, $pluginSetId);
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
	
	public function getNnPaymentSettingsValue($settingsKey, $paymentKey = null, $clientId = null, $pluginSetId = null)
    	{
		
		$settings = $this->getNnSettings($clientId, $pluginSetId);
		
		$this->getLogger(__METHOD__)->error('setting123', $settings);
		
		if(!is_null($settings)) {
			if(!empty($paymentKey) && isset($settings->value[$paymentKey])) {
				return $settings->value[$paymentKey][$settingsKey];
			} else {
				return $settings->value[$settingsKey];
			}
		}
                
        	return null;
	}
	
}

