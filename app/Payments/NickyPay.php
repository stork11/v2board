<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NickyPay
{
    public function getName()
    {
        return 'Nicky · 人民币 → USDT（Token）';
    }

    // 后台配置表单
    public function form()
    {
        return [
            'api_token' => [
                'label' => 'Nicky API Token',
                'type' => 'input',
                'placeholder' => '创建 API Key 时生成的那一整条字符串',
            ],
            'usdt_rate' => [
                'label' => 'USDT 汇率（CNY）',
                'type' => 'input',
                'default' => '7.2',
                'placeholder' => '例如 7.2',
            ],
        ];
    }

    // 发起支付
    public function pay($order)
    {
        $config = $this->config;

        // 人民币 → USDT
        $usdtAmount = round($order->total_amount / $config['usdt_rate'], 2);

        $payload = [
            'order_id'     => $order->trade_no,
            'amount'       => $usdtAmount,
            'currency'     => 'USDT',
            'callback_url' => url('/payment/nicky/token/notify'),
            'return_url'   => url('/#/order/' . $order->trade_no),
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_token'],
            'Accept'        => 'application/json',
        ])->post('https://api.nicky.me/v1/payment/create', $payload);

        if (!$response->ok()) {
            Log::error('Nicky Create Error', [
                'body' => $response->body()
            ]);
            abort(500, 'Nicky 创建订单失败');
        }

        $data = $response->json();

        if (!isset($data['payment_url'])) {
            Log::error('Nicky Invalid Response', $data);
            abort(500, 'Nicky 返回数据异常');
        }

        return [
            'type' => 1, // 跳转
            'url'  => $data['payment_url'],
        ];
    }

    // Webhook 回调
    public function notify(Request $request)
    {
        $data = $request->all();

        Log::info('Nicky Token Notify', $data);

        // 可选：校验回调是否携带 Authorization
        if ($request->hasHeader('Authorization')) {
            if ($request->header('Authorization') !== 'Bearer ' . $this->config['api_token']) {
                Log::warning('Nicky Unauthorized Callback');
                return response('unauthorized', 401);
            }
        }

        if (!isset($data['order_id'], $data['status'])) {
            return response('bad request', 400);
        }

        if ($data['status'] !== 'paid') {
            return response('ignored');
        }

        $order = Order::where('trade_no', $data['order_id'])->first();

        if (!$order || $order->status === 1) {
            return response('ok');
        }

        // 标记订单已支付
        $order->status  = 1;
        $order->paid_at = time();
        $order->save();

        return response('success');
    }
}
