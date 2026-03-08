<?php

namespace App\Console\Commands;

use App\Jobs\OrderHandleJob;
use App\Services\OrderService;
use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class AnalyticsDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:statsday';

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
	$day = date("Y-m-d",time());
        
        for($i = 0; $i < 30 ; $i ++){
            

	        $start_time = strtotime("-" . ($i + 1) . " day");
	        $end_time = strtotime("-" . $i . " day");
	
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
                
            $text_html = date("Y-m-d",strtotime("-" . $i . " day")) . PHP_EOL .
                '註冊人數:' . $register_count . PHP_EOL .
                '佣金数量:' . $comm_count . PHP_EOL .
                '佣金总额:' . $comm / 100 . PHP_EOL .
                '商品銷量:' . $shop_count . PHP_EOL .
                '收入總額:' . $income / 100 . PHP_EOL . PHP_EOL;
                
            echo $text_html;
        }
        
    }
}
