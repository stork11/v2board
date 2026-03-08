<?php


namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class DailyJobMonth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:month';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '月报';

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
      $start_time = mktime(0, 0, 0, date('m') - 1, 1, date('Y'));
        $end_time = mktime(0, 0, 0, date('m'), 1, date('Y'));
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
        $text_html = '上月財務報表~' . PHP_EOL .
            '大家辛苦了凌晨也在努力工作哦~!' . PHP_EOL .
            '註冊人數:' . $register_count . PHP_EOL .
            '商品銷量:' . $shop_count . PHP_EOL .
            '收入總額:' . $income / 100 . PHP_EOL;

        $telegramService = new TelegramService();
        $telegramService->sendMessage('-1001819758397', $text_html, 'markdown');
    }
}
