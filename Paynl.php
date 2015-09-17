<?php

namespace subdee\paynl;


use yii\base\Component;
use yii\helpers\ArrayHelper;

class Paynl extends Component
{
    public $token;
    public $serviceId;
    public $accountId = '';
    public $country = 'NL';
    public $finishUrl = '';

    static $url = 'https://rest-api.pay.nl/v3/';

    const SUCCESS = 100;
    const FAILURE = 90;

    public function getServices()
    {
        $url = static::$url . 'Transaction/getService';
        $result = $this->call($url);
        if (!$result) {
            throw new \Exception('There as an error getting info from paynl.');
        }
        if ($result->request->errorId != 0) {
            throw new \Exception($result->request->errorMessage);
        }

        return $result->countryOptionList->{$this->country}->paymentOptionList;
    }

    public function createPayment($amount, $description, $refID, $method, $bank = null)
    {
        $url = static::$url . 'Transaction/start';
        $params = [
            'amount' => $amount,
            'paymentOptionId' => $method,
            'ipAddress' => \Yii::$app->request->userIP,
            'finishUrl' => $this->finishUrl,
            'transaction' => ['description' => substr($description, 0, 24)],
            'statsData' => ['info' => $refID]
        ];
        if ($method == 10) {
            $params['paymentOptionSubId'] = $bank;
        }
        $result = $this->call($url, $params);
        if (!$result) {
            throw new \Exception('There as an error getting info from paynl.');
        }
        if (isset($result->status) && $result->status == 'FALSE') {
            throw new \Exception($result->error);
        }
        if ($result->request->errorId != 0) {
            throw new \Exception($result->request->errorMessage);
        }

        return $result->transaction;
    }

    public function getIdealBanks()
    {
        $url = static::$url . 'Transaction/getBanks';
        $result = $this->call($url);

        $banks = [];
        foreach ($result as $bank) {
            $banks[$bank->id] = $bank->name;
        }
        return $banks;
    }

    public function verifyPostback()
    {
        $orderId = false;
        $data = $_GET;
        if (isset($data['order_id'])) {
            $orderId = $data['order_id'];
        }
        if (isset($data['orderId'])) {
            $orderId = $data['orderId'];
        }
        if (!$orderId) {
            return false;
        }
        $url = static::$url . 'Transaction/info';
        $params = [
            'transactionId' => $orderId,
        ];

        $result = $this->call($url, $params);
        if (!$result) {
            return false;
        }

        return $result->paymentDetails->state;
    }

    protected function call($url, $extraParams = [], $format = 'json')
    {
        $params = [
            'token' => $this->token,
            'serviceId' => $this->serviceId
        ];
        $params = ArrayHelper::merge($params, $extraParams);
        $params = http_build_query($params);
        $url = "$url/$format?$params";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($data);

        return $result;
    }
}