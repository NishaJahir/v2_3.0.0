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
use Novalnet\Helper\PaymentHelper;
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
     * @var PaymentHelper
     */
    private $paymentHelper;
    
    /**
    * Constructor.
    *
    * @param WebstoreRepositoryContract $webstoreRepository
    * @param PaymentHelper $paymentHelper
    */
    public function __construct(WebstoreRepositoryContract $webstoreRepository,
                                PaymentHelper $paymentHelper
                               ) 
    {
         $this->webstoreRepository = $webstoreRepository;
         $this->paymentHelper = $paymentHelper;
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
        $config = $this->createWebhookConfiguration($config);
        $config = $this->createPaymentMethodConfiguration($config);
        

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
                "sections" => [
                    [
                        "title" => 'NovalnetAssistant.novalnetGlobalConf',
                        "description" => 'NovalnetAssistant.novalnetGlobalConfDesc',
                        "form" => [
                            'novalnetMerchantId' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetVendorIdLabel',
                                    'required' => true,
                                    'pattern'  => '^[1-9]\d*$'
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
                                    'required' => true,
                                    'pattern'  => '^[1-9]\d*$'
                                ]
                            ],
                            'novalnetTariffId' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetTariffIdLabel',
                                    'required' => true,
                                    'pattern'  => '^[1-9]\d*$'
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
 
    /**
    * Create the configuration for Webhook process
    * 
    * @param array $config
    * 
    * @return array
    */
    public function createWebhookConfiguration($config) 
    {
        $config['steps']['novalnetWebhookConf'] = [
                "title" => 'NovalnetAssistant.novalnetGlobalConf',
                "sections" => [
                    [
                        "title" => 'NovalnetAssistant.novalnetWebhookConf',
                        "description" => 'NovalnetAssistant.novalnetWebhookConfDesc',
                        "form" => [
                            'novalnetWebhookTestMode' => [
                                'type' => 'checkbox',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetWebhookTestModeLabel'
                                ]
                            ],
                            'novalnetWebhookEmailTo' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetWebhookEnableEmailLabel'
                                ]
                            ]
                        ]
                    ]
                 ]
        ];
        return $config;
    }

    /**
    * Create the configuration for Payment methods
    * 
    * @param array $config
    * 
    * @return array
    */
    public function createPaymentMethodConfiguration($config)
    {
       foreach($this->paymentHelper->getPaymentMethodsKey() as $paymentMethodKey) {
          $config['steps'][$paymentMethodKey] = [
                "title" => 'NovalnetAssistant'.strtolower($paymentMethodKey),
                "sections" => [
                 ]
          ];
          
       }
       return $config;
    }
         
    
}
