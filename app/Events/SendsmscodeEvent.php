<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SendsmscodeEvent extends Event
{
    use SerializesModels;

    public $type;   //请求模板类型 1发送验证码 2.。。
    public $phone;  //手机号
    public $param;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($param)
    {
        $this->param = $param;
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }
}
