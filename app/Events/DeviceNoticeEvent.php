<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class DeviceNoticeEvent extends Event
{
    use SerializesModels;

    public $client_hash;
    public $order_id;
    public $datas;
    public $type;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($client_hash, $order_id, $datas, $type='push')
    {
        $this->client_hash = $client_hash;
        $this->order_id = $order_id;
        $this->datas = $datas;
        $this->type  = $type;
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
