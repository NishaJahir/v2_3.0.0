<?php


namespace Novalnet\Assistants\SettingsHandlers;

use Plenty\Modules\Plugin\PluginSet\Contracts\PluginSetRepositoryContract;
use Plenty\Modules\Wizard\Contracts\WizardSettingsHandler;
use Plenty\Plugin\Log\Loggable;

/**
 * Class NovalnetAssistantSettingsHandler
 *
 * @package Novalnet\Assistants\SettingsHandlers
 */
class NovalnetAssistantSettingsHandler implements WizardSettingsHandler
{
    use Loggable;
    
    public function handle(array $data)
    {
        $this->getLogger(__METHOD__)->error('post data', $data);
    }
}
