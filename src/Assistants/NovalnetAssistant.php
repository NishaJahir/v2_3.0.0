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
                                    //'tooltip' => 'NovalnetAssistant.novalnetVendorIdTooltip',
                                    'required' => true,
                                    'pattern'  => '^[1-9]\d*$'
                                ]
                            ],
                            'novalnetAuthCode' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetAuthCodeLabel',
                                    //'tooltip' => 'NovalnetAssistant.novalnetAuthCodeTooltip',
                                    'required' => true
                                ]
                            ],
                            'novalnetProductId' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetProductIdLabel',
                                    //'tooltip' => 'NovalnetAssistant.novalnetProductIdTooltip',
                                    'required' => true,
                                    'pattern'  => '^[1-9]\d*$'
                                ]
                            ],
                            'novalnetTariffId' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetTariffIdLabel',
                                    //'tooltip' => 'NovalnetAssistant.novalnetTariffIdTooltip',
                                    'required' => true,
                                    'pattern'  => '^[1-9]\d*$'
                                ]
                            ],
                            'novalnetAccessKey' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetAccessKeyLabel',
                                   //'tooltip' => 'NovalnetAssistant.novalnetAccessKeyTooltip',
                                    'required' => true
                                ]
                            ],
                            'novalnetClientKey' => [
                                'type' => 'text',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetClientKeyLabel',
                                    //'tooltip' => 'NovalnetAssistant.novalnetClientKeyTooltip',
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
                "title" => 'NovalnetAssistant.novalnetWebhookConf',
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
                                    'name' => 'NovalnetAssistant.novalnetWebhookEmailToLabel',
                                    //'tooltip' => 'NovalnetAssistant.novalnetWebhookEmailToTooltip'
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
          $paymentMethodKey = str_replace('_','',ucwords(strtolower($paymentMethodKey),'_'));
          $paymentMethodKey[0] = strtolower($paymentMethodKey[0]);
          
          $config['steps'][$paymentMethodKey] = [
                "title" => 'NovalnetAssistant.'. $paymentMethodKey,
                "sections" => [
                    [
                        "title" => 'Customize.' .$paymentMethodKey,
                        "description" => 'Customize.'. $paymentMethodKey .'Desc',
                        "form" => [
                            $paymentMethodKey.'PaymentActive' => [
                                'type' => 'checkbox',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetPaymentActiveLabel'
                                ]
                            ],
                            $paymentMethodKey. 'TestMode' => [
                                'type' => 'checkbox',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetTestModeLabel'
                                ]
                            ],
                           $paymentMethodKey. 'PaymentLogo' => [
                                'type' => 'file',
                                'options' => [
                                    'name' => 'NovalnetAssistant.novalnetPaymentLogoLabel',
                                    'defaultValue' => 'images/'. $paymentMethodKey .'.png',
                                ]
                            ],
                            $this->CreateOptionalPaymentDisplayConfiguration($config, $paymentMethodKey)
                        ]
                    ]
                 ]
          ];
          
        }
        
        // Load the Novalnet Credit card payment configuration
        $config = $this->createNovalnetCcPaymentConfiguration($config);
        // Load the Novalnet Invoice payment configuration
        $config = $this->createNovalnetInvoicePaymentConfiguration($config);
        
        
        return $config;
    }
    
    public function createNovalnetCcPaymentConfiguration($config)
    {
        $config['steps']['novalnetCc']['sections'][]['form'] = [
             'novalnetCcEnforce' => [
                           'type' => 'checkbox',
                           'options' => [
                               'name' => 'NovalnetAssistant.novalnetCcEnforceLabel'
                           ]
                       ],
            'novalnetCcInlineForm' => [
                           'type' => 'checkbox',
                           'options' => [
                               'name' => 'NovalnetAssistant.novalnetCcDisplayInlineFormLabel'
                           ]
                       ],
            'novalnetCcLogos' => [
                           'type' => 'checkboxGroup',
                           'defaultValue' => ['Visa', 'MasterCard', 'AmericanExpress' , 'Mastero', 'Cartasi', 'UnionPay', 'Discover', 'DinersClub', 'Jcb', 'CarteBleue'],
                            'options' => [
                                'name' => 'NovalnetAssistant.novalnetCcLogosLabel',
                                'checkboxValues' => $this->getAllowedCreditCardTypes()
                            ]
                       ],
            'novalnetCcOneClickShoppping' => [
                           'type' => 'checkbox',
                           'options' => [
                               'name' => 'NovalnetAssistant.novalnetOneClickShopppingLabel'
                           ]
                       ],
            'novalnetCcStandardStyleLabel' => [
                           'type' => 'text',
                           'options' => [
                               'name' => 'NovalnetAssistant.novalnetCcStandardStyleLabelLabel'
                           ]
                       ],
             'novalnetCcStandardStyleField' => [
                           'type' => 'text',
                           'options' => [
                               'name' => 'NovalnetAssistant.novalnetCcStandardStyleFieldLabel'
                           ]
                       ],
             'novalnetCcStandardStyleCss' => [
                           'type' => 'text',
                           'options' => [
                               'name' => 'NovalnetAssistant.novalnetCcStandardStyleCssLabel'
                           ]
                       ],
             $this->createOnHoldConfiguration($config, 'novalnetCc'),
        ];
        return $config;
    }
    
    public function createNovalnetInvoicePaymentConfiguration($config)
    {
	$config['steps']['novalnetInvoice']['sections'][]['form'] = [
		'novalnetInvoiceDuedate' => [
		   'type' => 'text',
		   'options' => [
		       'name' => 'NovalnetAssistant.novalnetInvoiceDuedateLabel',
		       //'tooltip' => 'NovalnetAssistant.novalnetInvoiceDuedateTooltip'
		   ]
	       ],
		$this->createOnHoldConfiguration($config, 'novalnetInvoice'),
	];

	return $config;
		
    }
	
    public function createOnHoldConfiguration($config, $paymentMethodKey) {
       $config['steps'][$paymentMethodKey]['sections'][]['form'] = [
           $paymentMethodKey. 'PaymentAction' => [
               'type' => 'select',
               'defaultValue' => 0,
               'options' => [
                   'name' => 'NovalnetAssistant.novalnetPaymentActionLabel',
                   'listBoxValues' => [
                       [
                          'caption' => 'NovalnetAssistant.novalnetOnHoldCaptureLabel',
                          'value' => 0
                       ],
                       [
                          'caption' => 'NovalnetAssistant.novalnetOnHoldAuthorizeLabel',
                          'value' => 1
                       ]
                    ]
               ]
            ],
           $paymentMethodKey. 'OnHold' => [
                'type' => 'text',
                'options' => [
                    'name' => 'NovalnetAssistant.novalnetOnHoldLabel',
                    //'tooltip' => 'NovalnetAssistant.novalnetOnHoldTooltip'
                ]
            ]
        ];
     
        return $config;
     
    }
    
    public function getAllowedCreditCardTypes()
    {
        $cardTypes = ['Visa', 'MasterCard', 'AmericanExpress' , 'Mastero', 'Cartesi', 'UnionPay', 'Discover', 'DinersClub', 'Jcb', 'CarteBleue'];
        foreach($cardTypes as $cardType) {
            $allowedCreditCardTypes = [
                'caption' => 'NovalnetAssistant.novalnetCc'. $cardType .'Label',
                'value' => $cardType
            ];
        }
    }
    
    public function CreateOptionalPaymentDisplayConfiguration($config, $paymentMethodKey)
    {
        $config['steps'][$paymentMethodKey]['sections'][]['form'] = [
            $paymentMethodKey. 'MinimumOrderAmount' => [
                               'type' => 'text',
                               'options' => [
                                   'name' => 'NovalnetAssistant.novalnetMinimumOrderAmountLabel',
                                   //'tooltip' => 'NovalnetAssistant.novalnetMinimumOrderAmountTooltip'
                               ]
                           ],
            $paymentMethodKey. 'MaximumOrderAmount' => [
                               'type' => 'text',
                               'options' => [
                                   'name' => 'NovalnetAssistant.novalnetMaximumOrderAmountLabel',
                                   //'tooltip' => 'NovalnetAssistant.novalnetMaximumOrderAmountTooltip',
                               ]
                           ],
            $paymentMethodKey. 'AllowedCountry' => [
                               'type' => 'text',
                               'options' => [
                                   'name' => 'NovalnetAssistant.novalnetAllowedCountryLabel'
                               ]
                           ]
        ];
        return $config;
    }
     
    
}
