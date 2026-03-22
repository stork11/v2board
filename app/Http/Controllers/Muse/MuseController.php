<?php


namespace App\Http\Controllers\Muse;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\StatUser;
use App\Models\User;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PlanService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MuseController extends Controller
{
    public function flowList(Request $request)
    {
        $date_array = array();
        $date_count = array();

        $i = 0;
        while ($i < 7) {
            array_push($date_array, Carbon::today()->subDays($i)->format('Y-m-d'));
            $i++;
        }
        $user = $request->user;
        foreach ($date_array as $key => $date) {
            $res = StatUser::selectRaw('sum(u+d) as total')
                ->where('user_id', $user['id'])
                ->where('record_at', '>=', strtotime($date))
                ->when($key > 0, function ($query) use ($date) {
                    return $query->where('record_at', '<', strtotime('+1 day', strtotime($date)));
                })
                ->first()
                ->toArray();
            $date_count[] = $res['total'] ?? 0;
        }

        return response()->json(['data' => [$date_array, $date_count]]);
    }

    public function shopList(Request $request)
    {
        $counts = PlanService::countActiveUsers();
        $plans = Plan::where('show', 1)
            ->orderBy('sort', 'ASC')
            ->get();
        foreach ($plans as $k => $v) {
            if ($plans[$k]->capacity_limit === NULL) continue;
            if (!isset($counts[$plans[$k]->id])) continue;
            $plans[$k]->capacity_limit = $plans[$k]->capacity_limit - $counts[$plans[$k]->id]->count;
        }

        return response([
            'data' => $plans
        ]);
    }

    public function getPaymentMethod()
    {
        $methods = Payment::select([
            'id',
            'name',
            'payment',
            'icon',
            'handling_fee_fixed',
            'handling_fee_percent'
        ])
            ->where('enable', 1)
            ->orderBy('sort', 'ASC')
            ->get();

        return response([
            'data' => $methods
        ]);
    }

    public function order(Request $request)
    {
        $data = $request->all();
        if ((int)config('v2board.email_gmail_limit_enable', 0)) {
            $prefix = explode('@', $data['email'])[0];
            if (strpos($prefix, '.') !== false || strpos($prefix, '+') !== false) {
                abort(500, __('Gmail alias is not supported'));
            }
        }
        if ((int)config('v2board.stop_register', 0)) {
            abort(500, __('Registration has closed'));
        }
        $exist = User::where('email', $data['email'])->first();
        if ($exist) {
            abort(500, __('Email already exists'));
        }
        DB::beginTransaction();
        $user = new User();
        $user->email = $data['email'];
        $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        // try out
        if ((int)config('v2board.try_out_plan_id', 0)) {
            $plan = Plan::find(config('v2board.try_out_plan_id'));
            if ($plan) {
                $user->transfer_enable = $plan->transfer_enable * 1073741824;
                $user->plan_id = $plan->id;
                $user->group_id = $plan->group_id;
                $user->expired_at = time() + (config('v2board.try_out_hour', 1) * 3600);
            }
        }
        if (!$user->save()) {
            DB::rollBack();
            abort(500, __('Register failed'));
        }
        $plan = Plan::find($data['plan_id']);
        if (!$plan) {
            DB::rollBack();
            abort(500, __('Subscription plan does not exist'));
        }
        if ((!$plan->show && !$plan->renew) || (!$plan->show && $user->plan_id !== $plan->id)) {
            if ($data['cycle'] !== 'reset_price') {
                DB::rollBack();
                abort(500, __('This subscription has been sold out, please choose another subscription'));
            }
        }
        if ($plan[$data['period']] === NULL) {
            DB::rollBack();
            abort(500, __('This payment period cannot be purchased, please choose another cycle'));
        }
        $order = new Order();
        $orderService = new OrderService($order);
        $order->user_id = $user->id;
        $order->plan_id = $plan->id;
        $order->period = $data['period'];
        $order->trade_no = Helper::guid();
        $order->total_amount = $plan[$data['period']];
        if ($request->input('coupon_code')) {
            $couponService = new CouponService($data['coupon_code']);
            if (!$couponService->use($order)) {
                DB::rollBack();
                abort(500, __('Coupon failed'));
            }
            $order->coupon_id = $couponService->getId();
        }
        $orderService->setVipDiscount($user);
        $orderService->setOrderType($user);
        $orderService->setInvite($user);
        if (!$order->save()) {
            DB::rollback();
            abort(500, __('Failed to create order'));
        }
        $payment = Payment::find($data['method']);
        if (!$payment || $payment->enable !== 1){
            DB::rollback();
            abort(500, __('Payment method is not available'));
        }
        $paymentService = new PaymentService($payment->payment, $payment->id);
        $result = $paymentService->pay([
            'trade_no' => $order->trade_no,
            'total_amount' => $order->total_amount,
            'user_id' => $order->user_id,
            'stripe_token' => $request->input('token')
        ]);
        $order->update(['payment_id' => $data['method']]);
        DB::commit();

        return response([
            'trade_no' => $order->trade_no,
            'type' => $result['type'],
            'data' => $result['data'],
            'token' => $user->token,
            'auth_data' => base64_encode("{$user->email}:{$user->password}")
        ]);
    }

    public function couponCheck(Request $request)
    {
        if (empty($request->input('code'))) {
            abort(500, __('Coupon cannot be empty'));
        }
        $couponService = new CouponService($request->input('code'));
        $couponService->setPlanId($request->input('plan_id'));
        $couponService->setUserId($request->user ? $request->user['id'] : 1);
        $couponService->check();
        return response([
            'data' => $couponService->getCoupon()
        ]);
    }
}
