<?php
/**
 * Created by IntelliJ IDEA.
 * User: king
 * Date: 2016/12/9
 * Time: 15:50
 */
namespace App\Fractals;

use App\Fractals\Serializer\DataArraySerializer;
use League\Fractal\Manager as FractalManager;

class Manager extends FractalManager
{
    public function getSerializer()
    {
        if (!$this->serializer) {
            $this->setSerializer(new DataArraySerializer());
        }

        return $this->serializer;
    }
}