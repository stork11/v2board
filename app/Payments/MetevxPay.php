<?php

namespace App\Payments;

use Illuminate\Support\Facades\Log;

class MetevxPay {

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'mete_url' => [
                'label' => '业务域名',
                'description' => 'MetePay 业务域名',
                'type' => 'input',
            ],
            'appId' => [
                'label' => '应用Id',
                'description' => 'MetePay 应用Id',
                'type' => 'input',
            ],
            'secret' => [
                'label' => '应用密钥',
                'description' => 'MetePay 应用密钥',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order) {
        $params = array(
          'appId' => $this->config['appId'],
          'method' => 'WXPAY',
          'outTradeNo' => $order['trade_no'] . '_' . rand(1000, 9999),
          'totalAmount' => number_format($order['total_amount'] / 100, 2, '.', '')
        );

        ksort($params);
        reset($params);
        $signStr = '';
        foreach ($params as $key => $value) {
            $signStr .= $key . '=' . urlencode($value) . '&';
        }
        $signStr = rtrim($signStr, '&');
        $md5Str = md5($signStr);
        $finalMd5Str = md5($md5Str . $this->config['secret']);
        $params['sign'] = $finalMd5Str;

	    $url = $this->config['mete_url'] . '/api/v1/order/create';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $result = json_decode($response);
        
        if (!$result->success) {
            abort(500, $result->msg);
        }
        
        curl_close($ch);
        
        if ($result->success) {
            if ($result->data->type === 'qrcode') {
                return [
                    'type' => 0,
                    'data' => $result->data->url
                ];
            } else if ($result->data->type === 'redirect') {
                return [
                    'type' => 1,
                    'data' => $result->data->url
                ];             
            }
            
            //  return [
            //         'type' => 1,
            //         'data' => $result->data->url
            //     ];  
        } else {
            abort(500, '接口请求失败');
        }
    }

    public function notify($params)
    {
        // Log::debug($params);
        $sign = $params['sign'];
        unset($params['sign']);
        ksort($params);
        reset($params);

        $signStr = '';
        foreach ($params as $key => $value) {
            $signStr .= $key . '=' . urlencode($value) . '&';
        }
        $signStr = rtrim($signStr, '&');
        $md5Str = md5($signStr);
        $finalMd5Str = md5($md5Str . $this->config['secret']);

        if ($sign !== $finalMd5Str) {
            return false;
        }

        $tradeStatus = $params['tradeStatus'];
        $outTradeNo = explode('_', $params['outTradeNo'])[0];
        $tradeNo = $params['tradeNo'];

        if ($tradeStatus === 'TRADE_SUCCESS') {
            return [
                'trade_no' => $outTradeNo,
                'callback_no' => $tradeNo
            ];
            http_response_code(200);
            die('success');
        } else {
            return false;
        }
    }
}