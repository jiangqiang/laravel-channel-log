laravel-channel-log
===================

Make it possiable to log in different channels and streams.

## Installation

Require this package in your composer.json and run composer update (or `run composer require l-j/log:dev-master` directly):

    l-j/log:dev-master

## Config

After updating composer, add the ServiceProvider to the providers array in app/config/app.php and remove the default log provider

    `
    //'Illuminate\Log\LogServiceProvider',
    'LJ\Log\LogServiceProvider', 
    `

If you want to use the facade to log messages, add this to your facades and remove the default log facade in app.php:

    `
    //'Log' => 'Illuminate\Support\Facades\Log',
    'Log'   => 'LJ\Log\Facades\Log',
    `

Remove the `Log::useFiles(storage_path().'/logs/laravel.log')` in app/start/global/php;

If you want to overwrite the config by command:

`$ php artisan config:publish barryvdh/laravel-debugbar`

then in the app/packages, you can customize the config yourself. Note: the default config and the default channel is required.

## Useage

`Log::info($channel, $msg [, array $context]);`

There is just a default named default channel in the config, that means we can use like

`Log::info('default', $msg [, $context]);`. 

If you are annoy to do this you can just ignire the `$channel` parameter, like

 `Log::info($msg [, array $context])`
 
, the vender will automatic filling with `default` channel. 

Of course, you can add your own channel by config publish and overwrite it:

    `  
    // other channel
    'api' => array(
            //streams
            'info'      => array(),
            'warning'   => array(),
            'error'     => array(),
        ),
    `

the streams config options is same as the top default config like path, enable, daily, bubble, pathMode, fileMode. The code will merge them. Then the useage code will like this: 

`Log::info('api', $msg [, array $context])`.











