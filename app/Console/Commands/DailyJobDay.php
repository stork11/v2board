<?php


namespace App\Console\Commands;

use App\Models\Stat;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DailyJobDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:day';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '日报';

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
        $between = [Carbon::now()->subDay()->startOfDay()->timestamp, Carbon::now()->subDay()->endOfDay()->timestamp];
        $register_count = User::query()->whereBetween('created_at', $between)->count();
        $statistics = Stat::where('record_type', 'd')
            ->limit(1)
            ->orderBy('record_at', 'DESC')
            ->first();
        $text_html = '昨日財務報表~' . PHP_EOL .
            '大家辛苦了凌晨也在努力工作哦~!' . PHP_EOL .
            '註冊人數:' . $register_count . PHP_EOL .
            '商品銷量:' . $statistics->paid_count . PHP_EOL .
            '收入總額:' . $statistics->paid_total / 100 . PHP_EOL;

        $telegramService = new TelegramService();
        $telegramService->sendMessage('-1001819758397', $text_html, 'markdown');
    }
}
