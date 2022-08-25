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
        if (isset($data['novalnet_sepa'])) {
            $this->value['novalnet_sepa'] = $data['novalnet_sepa'];
        }
        if (isset($data['novalnet_cc'])) {
            $this->value['novalnet_cc'] = $data['novalnet_cc'];
        }
        if (isset($data['novalnet_applepay'])) {
            $this->value['novalnet_applepay'] = $data['novalnet_applepay'];
        }
        if (isset($data['novalnet_invoice'])) {
            $this->value['novalnet_invoice'] = $data['novalnet_invoice'];
        }
        if (isset($data['novalnet_prepayment'])) {
            $this->value['novalnet_prepayment'] = $data['novalnet_prepayment'];
        }
        if (isset($data['novalnet_guaranteed_invoice'])) {
            $this->value['novalnet_guaranteed_invoice'] = $data['novalnet_guaranteed_invoice'];
        }
        if (isset($data['novalnet_guaranteed_sepa'])) {
            $this->value['novalnet_guaranteed_sepa'] = $data['novalnet_guaranteed_sepa'];
        }
        if (isset($data['novalnet_ideal'])) {
            $this->value['novalnet_ideal'] = $data['novalnet_ideal'];
        }
        if (isset($data['novalnet_sofort'])) {
            $this->value['novalnet_sofort'] = $data['novalnet_sofort'];
        }
        if (isset($data['novalnet_giropay'])) {
            $this->value['novalnet_giropay'] = $data['novalnet_giropay'];
        }
        if (isset($data['novalnet_cashpayment'])) {
            $this->value['novalnet_cashpayment'] = $data['novalnet_cashpayment'];
        }
        if (isset($data['novalnet_przelewy24'])) {
            $this->value['novalnet_przelewy24'] = $data['novalnet_przelewy24'];
        }
        if (isset($data['novalnet_eps'])) {
            $this->value['novalnet_eps'] = $data['novalnet_eps'];
        }
        if (isset($data['novalnet_paypal'])) {
            $this->value['novalnet_paypal'] = $data['novalnet_paypal'];
        }
        if (isset($data['novalnet_postfinance_card'])) {
            $this->value['novalnet_postfinance_card'] = $data['novalnet_postfinance_card'];
        }
        if (isset($data['novalnet_postfinance_efinance'])) {
            $this->value['novalnet_postfinance_efinance'] = $data['novalnet_postfinance_efinance'];
        }
        if (isset($data['novalnet_bancontact'])) {
            $this->value['novalnet_bancontact'] = $data['novalnet_bancontact'];
        }
        if (isset($data['novalnet_multibanco'])) {
            $this->value['novalnet_multibanco'] = $data['novalnet_multibanco'];
        }
        if (isset($data['novalnet_online_bank_transfer'])) {
            $this->value['novalnet_online_bank_transfer'] = $data['novalnet_online_bank_transfer'];
        }
        if (isset($data['novalnet_alipay'])) {
            $this->value['novalnet_alipay'] = $data['novalnet_alipay'];
        }
        if (isset($data['novalnet_wechat_pay'])) {
            $this->value['novalnet_wechat_pay'] = $data['novalnet_wechat_pay'];
        }
        if (isset($data['novalnet_trustly'])) {
            $this->value['novalnet_trustly'] = $data['novalnet_trustly'];
        }
        if (isset($data['novalnet_googlepay'])) {
            $this->value['novalnet_googlepay'] = $data['novalnet_googlepay'];
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
