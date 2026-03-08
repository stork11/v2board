<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class OrderStatistics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:statistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $period = [
        'month_price' => '月付套餐',
        'quarter_price' => '季付套餐',
        'half_year_price' => '半年套餐',
        'year_price' => '一年套餐',
        'two_year_price' => '两年套餐',
        'three_year_price' => '三年套餐',
        'onetime_price' => '一次性套餐',
        'reset_price' => '重置套餐',
    ];

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
     * @return int
     */
    public function handle()
    {
        $between = [Carbon::now()->subDay()->startOfDay()->timestamp, Carbon::now()->subDay()->endOfDay()->timestamp];
        $keys = array_keys($this->period);

        $data_1 = Order::query()
            ->selectRaw('count(id) as total_amount_count, period')
            ->whereBetween('created_at', $between)
            ->where('status', 3)
            ->groupBy('period')
            ->get()
            ->toArray();
        $text = '昨日销量统计~' . PHP_EOL . PHP_EOL;
        foreach ($keys as $key) {
            foreach ($data_1 as $item) {
                if ($item['period'] === $key) {
                    $text .= $this->period[$key] . '数: ' . $item['total_amount_count'] . '笔' . PHP_EOL;
                }
            }
        }
        $this->sendMessage($text);

        $data_2 = Order::query()
            ->selectRaw('sum(total_amount) as total_amount_sum, period')
            ->whereBetween('created_at', $between)
            ->where('status', 3)
            ->groupBy('period')
            ->get()
            ->toArray();
        $text = '昨日营业额统计~' . PHP_EOL . PHP_EOL;
        foreach ($keys as $key) {
            foreach ($data_2 as $item) {
                if ($item['period'] === $key) {
                    $text .= $this->period[$key] . '数: ' . $item['total_amount_sum']/100 . '元' . PHP_EOL;
                }
            }
        }
        $this->sendMessage($text);

        $data_3 = Order::query()
            ->selectRaw('count(id) as total_amount_count, period, type')
            ->whereBetween('created_at', $between)
            ->where('status', 3)
            ->groupBy(['period', 'type'])
            ->get()
            ->toArray();
        $text = '昨日销量新购/续费/升级统计~' . PHP_EOL . PHP_EOL;
        foreach ($keys as $key) {
            foreach ($data_3 as $item) {
                if ($item['period'] === $key) {
                    if ($item['type'] === 1){
                        $text .= $this->period[$key] . '数 - 新购: ' . $item['total_amount_count'] . '笔' . PHP_EOL;
                    } elseif ($item['type'] === 2){
                        $text .= $this->period[$key] . '数 - 续费: ' . $item['total_amount_count'] . '笔' . PHP_EOL;
                    } elseif ($item['type'] === 3){
                        $text .= $this->period[$key] . '数 - 升级: ' . $item['total_amount_count'] . '笔' . PHP_EOL;
                    }
                }
            }
        }
        $this->sendMessage($text);

        $data_1 = Order::query()
            ->selectRaw('count(id) as total_amount_count, period')
            ->whereBetween('created_at', $between)
            ->where('status', 3)
            ->whereNotNull('invite_user_id')
            ->groupBy('period')
            ->get()
            ->toArray();
        $text = '昨日AFF销量统计~' . PHP_EOL . PHP_EOL;
        foreach ($keys as $key) {
            foreach ($data_1 as $item) {
                if ($item['period'] === $key) {
                    $text .= $this->period[$key] . '数:' . $item['total_amount_count'] . '笔' . PHP_EOL;
                }
            }
        }
        $this->sendMessage($text);
    }

    public function sendMessage($text)
    {
        $telegramService = new TelegramService();
        //$telegramService->sendMessageWithAdmin($text);
        $telegramService->sendMessage('-1001819758397', $text);
    }
}
