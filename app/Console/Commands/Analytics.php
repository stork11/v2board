<?php

namespace App\Console\Commands;

use App\Jobs\OrderHandleJob;
use App\Services\OrderService;
use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class Analytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '订单统计';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        echo  PHP_EOL;
        $m = date('m');
        $y = date('Y');
        
        for($i = 0; $i < 12 ; $i ++){
            
            $cm = $m - $i;
            $cy = $y;
            
            if($cm == 0){
                $cm = 12;
                $cy = $cy - 1;
                $start_time = mktime(0, 0, 0, 12, 1, $cy);
                $end_time = mktime(0, 0, 0, 1, 1, $y );
            } else if ($cm < 0){
                $cy = $cy - 1;
                $cm = $cm + 12;
                $start_time = mktime(0, 0, 0, $cm, 1, $y - 1);
                $end_time = mktime(0, 0, 0, $cm + 1, 1, $y - 1);
            } else {
                $start_time = mktime(0, 0, 0, $cm, 1, $y);
                $end_time = mktime(0, 0, 0, $cm + 1, 1, $y);
            }
            
            $register_count = User::query()
                ->where('created_at', '>=', $start_time)
                ->where('created_at', '<', $end_time)
                ->count();
            $shop_count = Order::query()
                ->where('created_at', '>=', $start_time)
                ->where('created_at', '<', $end_time)
                ->whereNotIn('status', [0, 2])
                ->count();
            $income = Order::query()
                ->where('created_at', '>=', $start_time)
                ->where('created_at', '<', $end_time)
                ->whereNotIn('status', [0, 2])
                ->sum('total_amount');
            $comm_count = Order::query()
                ->where('created_at', '>=', $start_time)
                ->where('created_at', '<', $end_time)
                ->where('commission_balance', '>', 0)
                ->whereNotIn('status', [0, 2])
                ->count();    
            $comm = Order::query()
                ->where('created_at', '>=', $start_time)
                ->where('created_at', '<', $end_time)
                ->whereNotIn('status', [0, 2])
                ->sum('commission_balance');
                
            $text_html = $cy . '年' . $cm . '月：' . PHP_EOL .
                '註冊人數:' . $register_count . PHP_EOL .
                '佣金数量:' . $comm_count . PHP_EOL .
                '佣金总额:' . $comm / 100 . PHP_EOL .
                '商品銷量:' . $shop_count . PHP_EOL .
                '收入總額:' . $income / 100 . PHP_EOL . PHP_EOL;
                
            echo $text_html;
        }
        
    }
}
