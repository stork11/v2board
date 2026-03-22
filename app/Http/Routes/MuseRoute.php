<?php


namespace App\Http\Routes;


use Illuminate\Contracts\Routing\Registrar;

class MuseRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'muse',
        ], function ($router) {
            // flow
            $router->get('/flow/fetch', 'Muse\\MuseController@flowList')->middleware('user');
            // shop
            $router->get('/shop/fetch', 'Muse\\MuseController@shopList');
            // shop
            $router->get('/getPaymentMethod', 'Muse\\MuseController@getPaymentMethod');
            // order
            $router->post('/order/pay', 'Muse\\MuseController@order');
            // coupon
            $router->post('/coupon/check', 'Muse\\MuseController@couponCheck');
        });
    }
}
