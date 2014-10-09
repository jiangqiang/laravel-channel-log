<?php namespace LJ\Log;

use Illuminate\Support\ServiceProvider;
use Monolog\Logger;
class LogServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('l-j/log');
		$this->app['log']->init();//init stream handler
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
        $logger = new Writer($this->app['events']);

        $this->app->instance('log', $logger);

        // If the setup Closure has been bound in the container, we will resolve it
        // and pass in the logger instance. This allows this to defer all of the
        // logger class setup until the last possible second, improving speed.
        if (isset($this->app['log.setup']))
        {
            call_user_func($this->app['log.setup'], $logger);
        }
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('log');
	}

}
