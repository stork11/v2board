<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\Protocols\General;
use App\Http\Controllers\Controller;
use App\Services\ServerService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use App\Services\UserService;

class ClientController extends Controller
{
    public function subscribe(Request $request)
    {
        $flag = $request->input('flag')
            ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $flag = strtolower($flag);
        $decodedFlag = urldecode($flag);
        $flags = [
            'clash' => ['clash', 'cfw', 'clashforwindows'],
            'meta' => ['meta', 'clashmeta', 'clash-meta', 'metacubex'],
            'quantumult%20x' => ['quantumult x', 'quantumultx', 'quantumult%20x'],
            'shadowrocket' => ['shadowrocket', 'shadowrocketios'],
            'v2rayn' => ['v2rayn', 'v2ray n'],
            'v2rayng' => ['v2rayng', 'v2ray ng'],
            'ssrplus' => ['ssrplus', 'ssr-plus'],
        ];
        $user = $request->user;
        // account not expired and is not banned.
        $userService = new UserService();
        if ($userService->isAvailable($user)) {
            $serverService = new ServerService();
            $servers = $serverService->getAvailableServers($user);
            $this->setSubscribeInfoToServers($servers, $user);
            if ($flag) {
                foreach (array_reverse(glob(app_path('Http//Controllers//Client//Protocols') . '/*.php')) as $file) {
                    $file = 'App\\Http\\Controllers\\Client\\Protocols\\' . basename($file, '.php');
                    $class = new $file($user, $servers);

                    $matchFlags = [];
                    if (isset($class->flags) && is_array($class->flags) && count($class->flags)) {
                        $matchFlags = $class->flags;
                    } elseif (isset($class->flag) && $class->flag) {
                        $matchFlags = $flags[$class->flag] ?? [$class->flag];
                    }

                    foreach ($matchFlags as $matchFlag) {
                        $matchFlag = strtolower($matchFlag);
                        if (
                            strpos($flag, $matchFlag) !== false
                            || strpos($decodedFlag, $matchFlag) !== false
                        ) {
                            die($class->handle());
                        }
                    }
                }
            }
            $class = new General($user, $servers);
            die($class->handle());
        }
    }

    private function setSubscribeInfoToServers(&$servers, $user)
    {
        if (!isset($servers[0])) return;
        if (!(int)config('v2board.show_info_to_server_enable', 0)) return;
        $useTraffic = $user['u'] + $user['d'];
        $totalTraffic = $user['transfer_enable'];
        $remainingTraffic = Helper::trafficConvert($totalTraffic - $useTraffic);
        $expiredDate = $user['expired_at'] ? date('Y-m-d', $user['expired_at']) : '长期有效';
        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        array_unshift($servers, array_merge($servers[0], [
            'name' => "套餐到期：{$expiredDate}",
        ]));
        if ($resetDay) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "距离下次重置剩余：{$resetDay} 天",
            ]));
        }
        array_unshift($servers, array_merge($servers[0], [
            'name' => "剩余流量：{$remainingTraffic}",
        ]));
    }
}
