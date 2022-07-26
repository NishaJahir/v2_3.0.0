<?php

namespace Novalnet\Models;


use Carbon\Carbon;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Plenty\Modules\Plugin\DataBase\Contracts\Model;
use Plenty\Plugin\Log\Loggable;

/**
 * Class Settings
 *
 * @property int $id
 * @property int $clientId
 * @property int $pluginSetId
 * @property array $value
 * @property string $createdAt
 * @property string $updatedAt
 */
class Settings extends Model
{
    use Loggable;
	
    public $id;
    public $clientId;
    public $pluginSetId;
    public $value = [];
    public $createdAt = '';
    public $updatedAt = '';

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return 'Novalnet::settings';
    }
    
    public function create($data)
    {
		$this->getLogger(__METHOD__)->error('create', $data);
        $this->clientId = $data['clientId'];
        $this->pluginSetId = $data['pluginSetId'];
        $this->createdAt = (string)Carbon::now();

        $this->value = [
            'novalnet_public_key' => $data['novalnet_public_key'],
	    'novalnet_private_key' => $data['novalnet_access_key'],
            'novalnet_tariff_id' => $data['novalnet_tariff_id'],
            'novalnet_client_key' => $data['novalnet_client_key'],
            'novalnet_webhook_testmode' => $data['novalnet_webhook_testmode'],
            'novalnet_webhook_email_to' => $data['novalnet_webhook_email_to'],
            'novalnet_cc' => $data['novalnet_cc'],
            'novalnet_invoice' => $data['novalnet_invoice'],
            'novalnet_ideal' => $data['novalnet_ideal']
        ];

        return $this->save();
    }
    
    public function update($data)
    {
	    $this->getLogger(__METHOD__)->error('update', $data);
		if (isset($data['novalnet_public_key'])) {
            $this->value['novalnet_public_key'] = $data['novalnet_public_key'];
        }
	       if (isset($data['novalnet_private_key'])) {
            $this->value['novalnet_private_key'] = $data['novalnet_private_key'];
        }
		if (isset($data['novalnet_tariff_id'])) {
            $this->value['novalnet_tariff_id'] = $data['novalnet_tariff_id'];
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
        return $this->save();
		
	}
    
    public function save()
    {
	    $this->getLogger(__METHOD__)->error('save', $data);
        $database = pluginApp(DataBase::class);
        $this->updatedAt = (string)Carbon::now();

        return $database->save($this);
    }
}
