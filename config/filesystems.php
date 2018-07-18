<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. A "local" driver, as well as a variety of cloud
    | based drivers are available for your choosing. Just store away!
    |
    | Supported: "local", "ftp", "s3", "rackspace"
    |
    */

    'default' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => 's3',

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],
        'wachatfile' => [
            'driver' => 'local',
            'root' => public_path('smallwechat'),
        ],
        'agentsim' => [
            'driver' => 'local',
            'root' => storage_path('app/agentsim'),
        ],
        'logs' => [
            'driver' => 'local',
            'root' => storage_path('app/logs'),
        ],
        'test' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
        ],
        'ming' => [
            'driver' => 'local',
            'root' => storage_path('app/ming'),
        ],
        'paylog' => [
            'driver' => 'local',
            'root' => storage_path('app/paylog/'),
        ],
        'nodrivce' => [
            'driver' => 'local',
            'root' => storage_path('app/nodrivce'),
        ],
        'messsedfail' => [
            'driver' => 'local',
            'root' => storage_path('app/messsedfail'),
        ],
        'qiniu' => [
            'driver' => 'qiniu',
            'domain' => 'http://ocs2ic4ow.bkt.clouddn.com', //你的七牛域名
            'access_key' => '', //AccessKey
            'secret_key' => '', //SecretKey
            'bucket' => 'billimg', //Bucket名字
        ],
        'GoService' => [
            'driver' => 'local',
            'root' => storage_path('app/go'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => 'your-key',
            'secret' => 'your-secret',
            'region' => 'your-region',
            'bucket' => 'your-bucket',
        ],

    ],

];
