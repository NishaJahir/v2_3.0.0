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
    
	public function getSettings($clientId, $pluginSetId)
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
														  ->limit(1)
														  ->get();
	}
	
	public function createOrUpdateNovalnetConfigurationSettings($clientId, $pluginSetId)
	{
		
		
	}
}

