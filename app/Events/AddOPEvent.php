<?php

namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AddOPEvent extends Event
{
    use SerializesModels;
    public $productId;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($product_id)
    {
        $this->productId = $product_id;
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
