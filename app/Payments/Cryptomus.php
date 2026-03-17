<?php
namespace App\Payments;

use Library\Cryptomus\Payment as CryptomusPayment;

/**
 *  Cryptomus crypto payment gateway
 */
class Cryptomus {

    /**
     * @param $config
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * @return array[]
     */
    public function form()
    {
        return [
            'cryptomus_api_key' => [
                'label' => 'Api key',
                'description' => 'You can find the API key in the settings of your personal account.',
                'type' => 'input',
            ],
            'cryptomus_uuid' => [
                'label' => 'UUID',
                'description' => 'You can find the Merchant UUID in the settings of your personal account.',
                'type' => 'input',
            ],
            'cryptomus_subtract' => [
                'label' => 'Substract',
                'description' => 'How much commission does the client pay (0-100%)',
                'type' => 'input',
            ],
            'cryptomus_lifetime' => [
                'label' => 'Lifetime',
                'description' => 'The lifespan of the issued invoice.(In seconds)',
                'type' => 'input',
            ],
            'cryptomus_currency' => [
                'label' => 'Fiat Currency',
                'description' => 'Default CNY',
                'type' => 'input'
            ]
        ];
    }

    /**
     * @param $order
     * @return array
     * @throws \Exception
     */
    public function pay($order) {
        $config = $this->config;

        $paymentData = [
            'amount' => sprintf('%.2f', $order['total_amount'] / 100),
            'currency' => $config['cryptomus_currency'] ?? 'CNY',
            'order_id' => 'v2board_' . $order['trade_no'],
            'url_return' => $order['return_url'],
            'url_callback' => $order['notify_url'],
            'lifetime' => $config['cryptomus_lifetime'] ?? '3600',
            'subtract' => $config['cryptomus_subtract'] ?? '0',
            'plugin_name' => 'v2board:1.7.4',
        ];

        $paymentInstance = $this->getPayment();

        try {
            $payment = $paymentInstance->create($paymentData);
        } catch (\Exception $exception) {
            info($exception->getMessage());
            abort(500, __($exception->getMessage()));
        }

        return [
            'type' => 1, // Redirect to url
            'data' =>  $payment['url']
        ];
    }


    /**
     * @return CryptomusPayment
     * @throws \Exception
     */
    private function getPayment()
    {
        $merchantUuid = trim($this->config['cryptomus_uuid']);
        $paymentKey = trim($this->config['cryptomus_api_key']);

        if (!$merchantUuid && !$paymentKey) {
            info(__("Please fill UUID and API key"));
            abort(500, __("Please fill UUID and API key"));
        }

        return new CryptomusPayment($paymentKey, $merchantUuid);
    }

    /**
     * @param $params
     * @return array|false
     */
    public function notify($params) {

        $payload = trim(file_get_contents('php://input'));
        $data = json_decode($payload, true);

        if (!$this->hashEqual($data)) {
            abort(400, 'Signature does not match');
        }

        $success = !empty($data['is_final']) && ($data['status'] === 'paid' || $data['status'] === 'paid_over' || $data['status'] === 'wrong_amount');
        if ($success) {
            $orderId = preg_replace('/^v2board(?:_upd)?_/', '', $data['order_id'] ?? '');
            return [
                'trade_no' => $orderId,
                'callback_no' => $data['uuid']
            ];
        }

        return false;
    }


    /**
     * @param $data
     * @return bool
     */
    private function hashEqual($data)
    {
        $paymentKey = trim($this->config['cryptomus_api_key']);

        if (!$paymentKey) {
            return false;
        }

        $signature = $data['sign'];
        if (!$signature) {
            return false;
        }

        unset($data['sign']);

        $hash = md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)) . $paymentKey);
        if (!hash_equals($hash, $signature)) {
            return false;
        }

        return true;
    }

}
