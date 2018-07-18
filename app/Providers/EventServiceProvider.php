<?php

namespace App\Providers;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\BuildGoFileEvent' => [
            'App\Listeners\BuildGoFileEventListener',
        ],
        'App\Events\AddOPEvent' => [
            'App\Listeners\AddOPEventListener',
        ],
        'App\Events\SendsmscodeEvent' => [
            'App\Listeners\SendsmscodeEventListener',
        ],
        'App\Events\DeviceNoticeEvent' => [
            'App\Listeners\DeviceNoticeEventListener',
        ],
        'App\Events\PushMqttMessageEvent' => [
            'App\Listeners\PushMqttMessageEventListener',
        ],
    ];

    /**
     * Register any other events for your application.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function boot(DispatcherContract $events)
    {
        parent::boot($events);

        //
    }
}
