<?php
return array(
    'default' => array(
        'path'     => storage_path() .'/logs/',
        'enable'   => true,
        'daily'    => true,
        'bubble'   => false,
        'pathMode' => 0777,
        'fileMode' => 0777,
    ),

    'channels' => array(
        'default' => array(
            //streams, 各等级日志放置在不同文件, 与上面的default合并即当前配置
            'debug'     => array(),
            'info'      => array(),
            'notice'    => array(),
            'warning'   => array(),
            'error'     => array(),
            'critical'  => array(),
            'alert'     => array(),
            'emergency' => array(),
        ),

        // other channel
    ),
);