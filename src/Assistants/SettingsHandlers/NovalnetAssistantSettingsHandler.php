<?php


namespace Novalnet\Assistants\SettingsHandlers;

use Plenty\Modules\Plugin\PluginSet\Contracts\PluginSetRepositoryContract;
use Plenty\Modules\Wizard\Contracts\WizardSettingsHandler;

/**
 * Class NovalnetAssistantSettingsHandler
 *
 * @package Novalnet\Assistants\SettingsHandlers
 */
class NovalnetAssistantSettingsHandler implements WizardSettingsHandler
{
    
    public function handle(array $data)
    {
        $this->getLogger(__METHOD__)->error('post data', $data);
    }
}
