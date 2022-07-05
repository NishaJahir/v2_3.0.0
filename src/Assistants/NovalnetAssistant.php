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
 
namespace Novalnet\Assistants;

use Plenty\Modules\Wizard\Services\WizardProvider;
use Novalnet\Assistants\SettingsHandlers\NovalnetAssistantSettingsHandler;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;
use Plenty\Plugin\Application;
use Plenty\Plugin\Log\Loggable;

/**
 * Class NovalnetAssistant
 *
 * @package Novalnet\Assistants
 */
class NovalnetAssistant extends WizardProvider
{
    use Loggable;
 
    /**
     * @var WebstoreRepositoryContract
     */
    private $webstoreRepository;
 
    /**
     * @var $mainWebstore
     */
    private $mainWebstore;
    
    /**
     * @var $webstoreValues
     */
    private $webstoreValues;
    
    /**
    * Constructor.
    *
    * @param WebstoreRepositoryContract $webstoreRepository
    */
    public function __construct(WebstoreRepositoryContract $webstoreRepository) 
    {
         $this->webstoreRepository = $webstoreRepository;
     }
 
    protected function structure()
    {
        $config = [
            "title" => 'NovalnetAssistant.novalnetAssistantTitle',
            "shortDescription" => 'NovalnetAssistant.novalnetAssistantShortDescription',
            "iconPath" => $this->getIcon(),
            "settingsHandlerClass" => NovalnetAssistantSettingsHandler::class,
            //"dataSource" => NovalnetAssistantDataSource::class,
            "translationNamespace" => 'Novalnet',
            "key" => 'payment-novalnet-assistant',
            "topics" => ['payment'],
            "priority" => 990,
            "options" => [
                'clientId' => [
                    'type' => 'select',
                    'defaultValue' => $this->getMainWebstore(),
                    'options' => [
                        'name' => 'NovalnetAssistant.clientId',
                        'required' => true,
                        'listBoxValues' => $this->getWebstoreListForm(),
                    ],
                ],
            ],
            "steps" => [
            ]
        ];

        $config = $this->createGlobalConfiguration($config);

        return $config;
    }
 
   /**
     * Load the Novalnet Icon
     *
     * @return string
     */
    protected function getIcon()
    {
        $app = pluginApp(Application::class);
        $icon = $app->getUrlPath('Novalnet').'/images/novalnet_icon.png';
        return $icon;
    }
 
    private function getMainWebstore()
    {
        if($this->mainWebstore === null) {
            $this->mainWebstore = $this->webstoreRepository->findById(0)->storeIdentifier;
        }
        $this->getLogger(__METHOD__)->error('getMainWebstore', $this->mainWebstore);
        return $this->mainWebstore;
    }
 
    /**
     * @return array
     */
    private function getWebstoreListForm()
    {
        if($this->webstoreValues === null)
        {
            $webstores = $this->webstoreRepository->loadAll();
            $this->webstoreValues = [];
            /** @var Webstore $webstore */
            foreach ($webstores as $webstore) {
                $this->webstoreValues[] = [
                    "caption" => $webstore->name,
                    "value" => $webstore->storeIdentifier,
                ];
            }
        }
     $this->getLogger(__METHOD__)->error('getWebstoreListForm', $this->webstoreValues);
        return $this->webstoreValues;
    }
    
    /**
    * Create the configuration for Global Configuration
    * 
    * @param array $config
    * 
    * @return array
    */
    public function createGlobalConfiguration($config) 
    {
        $config['steps']['novalnetGlobalConf'] = [
                "title" => 'NovalnetAssistant.novalnetGlobalConf',
                "description" => 'NovalnetAssistant.novalnetGlobalConfDesc',
                "sections" => [
                    [
                        "title" => 'NovalnetAssistant.novalnetGlobalConf',
                        "description" => 'NovalnetAssistant.novalnetGlobalConfDesc',
                        "form" => [
                            'novalnetMerchantId' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetVendorIdLabel',
                                    'required' => true
                                ]
                            ],
                            'novalnetAuthCode' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetAuthCodeLabel',
                                    'required' => true
                                ]
                            ],
                            'novalnetProductId' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetProductIdLabel',
                                    'required' => true
                                ]
                            ],
                            'novalnetTariffId' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetTariffIdLabel',
                                    'required' => true
                                ]
                            ],
                            'novalnetAccessKey' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetAccessKeyLabel',
                                    'required' => true
                                ]
                            ],
                            'novalnetClientKey' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetClientKeyLabel',
                                    'required' => true
                                ]
                            ]
                        ]
                    ]
                ]
        ];
        return $config;
    }
}
