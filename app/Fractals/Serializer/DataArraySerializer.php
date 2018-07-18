<?php
/**
 * Created by IntelliJ IDEA.
 * User: king
 * Date: 2016/12/9
 * Time: 16:10
 */

namespace App\Fractals\Serializer;

use League\Fractal\Pagination\PaginatorInterface;
use League\Fractal\Serializer\ArraySerializer;

class DataArraySerializer extends ArraySerializer
{
    /**
     * Serialize a collection.
     *
     * @param string $resourceKey
     * @param array $data
     *
     * @return array
     */
    public function collection($resourceKey, array $data)
    {
        return $resourceKey ? array($resourceKey => $data) : $data;
    }

    /**
     * Serialize an item.
     *
     * @param string $resourceKey
     * @param array $data
     *
     * @return array
     */
    public function item($resourceKey, array $data)
    {
        return $resourceKey ? array($resourceKey => $data) : $data;
    }

    /**
     * @param PaginatorInterface $paginator
     * @return array
     */
    public function paginator(PaginatorInterface $paginator)
    {
        $pagination = array(
            'total' => (int)$paginator->getTotal(),
            'page_size' => (int)$paginator->getPerPage(),
            'current_page' => (int)$paginator->getCurrentPage(),
        );
        return array('pagination' => $pagination);
    }

    public function meta(array $meta)
    {
        return $meta;
    }
}