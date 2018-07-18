<?php
namespace App\Console;
use App\lib\Alijssdk;
use App\lib\Gateway;
use App\lib\WeChat\Transfer\WxTransfer;
use App\Models\Merchant\channel_operating;
use App\Models\Merchant\client;
use App\Models\Merchant\client_log;
use App\Models\Merchant\coupon_use_log;
use App\Models\Merchant\customer_coupon;
use App\Models\MerchantAdmin\branch_in;
use App\Models\MerchantAdmin\channel_in;
use App\Models\Merchant\client_income;
use App\Models\MerchantAdmin\client_active;
use App\Models\MerchantAdmin\money_record;
use App\Models\Operate\operates;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Ixudra\Curl\Facades\Curl;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use App\Models\Merchant\dev_channel;
use App\Models\MerchantApi\order_info;
use App\Models\Merchant\container_goods_stock;

@ini_set('memory_limit', '-1');

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
//        'App\Console\Commands\Inspire',
        'App\Console\Commands\SendEmails',
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
//        $schedule->call(function () {
//        })->dailyAt('08:20');
    }
}