<?php namespace LJ\Log;

use Config;
use Closure;
use Illuminate\Events\Dispatcher;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RotatingFileHandler;
use Illuminate\Support\Contracts\JsonableInterface;
use Illuminate\Support\Contracts\ArrayableInterface;
class Writer {
    /**
     * @var bool
     */
    protected $initd = false;

	/**
	 * The Monolog logger instance array.
	 *
	 * @var array
	 */
	protected $loggers = array();

	/**
	 * All of the error levels.
	 *
	 * @var array
	 */
	protected $levels = array(
		'debug',
		'info',
		'notice',
		'warning',
		'error',
		'critical',
		'alert',
		'emergency',
	);

	/**
	 * The event dispatcher instance.
	 *
	 * @var \Illuminate\Events\Dispatcher
	 */
	protected $dispatcher;

    /**
     * @param Dispatcher $dispatcher
     */
    public function __construct(Dispatcher $dispatcher = null)
	{
		if (isset($dispatcher))
		{
			$this->dispatcher = $dispatcher;
		}
	}

    /**
     * 初始化日志项
     * @return void
     */
    public function init()
    {
        if($this->initd == false)
        {
            $channels = Config::get('log::config.channels');
            foreach($channels as $channel => $streams)
            {
                $this->addChannel($channel, $streams);
            }

            $this->initd = true;
        }
    }

    /**
     * 添加一个通道
     * @param string $channel 通道名称 如：default
     * @param array  $streams 通道中定义的streams 如：info, warring, error
     * @param boolean 在此类外面追加日志通道
     * @return void
     * @throws \RuntimeException
     */
    public function addChannel($channel, $streams = array(), $append = false)
    {
        if($append) $this->init();

        $this->loggers[$channel] = new MonologLogger($channel);
        $default = Config::get('log::config.default');
        foreach ($streams as $stream => $config) {
            $config = array_merge($default, $config);
            if(!$config['enable']) continue;

            $path = $config['daily']
                ? sprintf('%s%s/', $config['path'], date('Ymd'))
                : $config['path'];

            if(!is_dir($path))
            {
                umask(0);
                @mkdir($path, $config['pathMode'], true);
            }
            elseif(!is_writable($path))
            {
                throw new \RuntimeException("Directory $path is not writable!");
            }

            $logFile =  isset($config['logFile'])
                        ? sprintf('%s%s', $path, $config['logFile'])
                        : sprintf('%s%s-%s.log', $path, $channel, $stream);

            if($config['daily'])
            {
                $this->useDailyFiles($channel, $logFile, 0, $stream, $config['bubble'], $config['fileMode']);
            }
            else
            {
                $this->useFiles($channel, $logFile, $stream, $config['bubble'], $config['fileMode']);
            }
        }
    }

	/**
	 * Call Monolog with the given method and parameters.
	 *
	 * @param  string  $method
	 * @param  mixed   $parameters
	 * @return mixed
	 */
	protected function callMonolog($method, $parameters)
	{
		if (is_array($parameters[1]))
		{
			$parameters[1] = json_encode($parameters[1]);
		}

        $logger = $this->getMonolog($parameters[0]);
        unset($parameters[0]);

		return call_user_func_array(array($logger, $method), $parameters);
	}

    /**
     * Register a file log handler.
     *
     * @param  string  $channel
     * @param  string  $path
     * @param  string  $level
     * @param  boolean  $bubble
     * @param  int  $mode
     * @return void
     */
    public function useFiles($channel, $path, $level = 'debug', $bubble = true, $mode = 644)
    {
        $level = $this->parseLevel($level);

        $this->loggers[$channel]->pushHandler($handler = new StreamHandler($path, $level, $bubble, $mode));

        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * Register a daily file log handler.
     *
     * @param  string  $channel
     * @param  string  $path
     * @param  int     $days
     * @param  string  $level
     * @param  boolean  $bubble
     * @param  int  $mode
     * @return void
     */
    public function useDailyFiles($channel, $path, $days = 0, $level = 'debug', $bubble = true, $mode = 644)
    {
        $level = $this->parseLevel($level);

        $this->loggers[$channel]->pushHandler($handler = new RotatingFileHandler($path, $days, $level, $bubble, $mode));

        $handler->setFilenameFormat('{filename}', 'Y-m-d');
        $handler->setFormatter($this->getDefaultFormatter());
    }

    /**
     * Register an error_log handler.
     *
     * @param  string  $channel
     * @param  string  $level
     * @param  integer $messageType
     * @param  boolean $bubble
     * @param  int $mode
     * @return void
     */
    public function useErrorLog($channel, $level = 'debug', $messageType = ErrorLogHandler::OPERATING_SYSTEM, $bubble = true, $mode = 644)
    {
        $level = $this->parseLevel($level);

        $this->loggers[$channel]->pushHandler($handler = new ErrorLogHandler($messageType, $level, $bubble, $mode));

        $handler->setFormatter($this->getDefaultFormatter());
    }

	/**
	 * Get a defaut Monolog formatter instance.
	 *
	 * @return \Monolog\Formatter\LineFormatter
	 */
	protected function getDefaultFormatter()
	{
		return new LineFormatter(null, null, true);
	}

	/**
	 * Parse the string level into a Monolog constant.
	 *
	 * @param  string  $level
	 * @return int
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function parseLevel($level)
	{
		switch ($level)
		{
			case 'debug':
				return MonologLogger::DEBUG;

			case 'info':
				return MonologLogger::INFO;

			case 'notice':
				return MonologLogger::NOTICE;

			case 'warning':
				return MonologLogger::WARNING;

			case 'error':
				return MonologLogger::ERROR;

			case 'critical':
				return MonologLogger::CRITICAL;

			case 'alert':
				return MonologLogger::ALERT;

			case 'emergency':
				return MonologLogger::EMERGENCY;

			default:
				throw new \InvalidArgumentException("Invalid log level.");
		}
	}

	/**
	 * Register a new callback handler for when
	 * a log event is triggered.
	 *
	 * @param  \Closure  $callback
	 * @return void
	 *
	 * @throws \RuntimeException
	 */
	public function listen(Closure $callback)
	{
		if ( ! isset($this->dispatcher))
		{
			throw new \RuntimeException("Events dispatcher has not been set.");
		}

		$this->dispatcher->listen('illuminate.log', $callback);
	}

	/**
	 * Get the underlying Monolog instance.
	 *
     * @param $channel
	 * @return \Monolog\Logger
	 */
	public function getMonolog($channel)
	{
        if(isset($this->loggers[$channel]) && !empty($this->loggers[$channel]))
        {
            return $this->loggers[$channel];
        }

        return false;
	}

	/**
	 * Get the event dispatcher instance.
	 *
	 * @return \Illuminate\Events\Dispatcher
	 */
	public function getEventDispatcher()
	{
		return $this->dispatcher;
	}

	/**
	 * Set the event dispatcher instance.
	 *
	 * @param  \Illuminate\Events\Dispatcher
	 * @return void
	 */
	public function setEventDispatcher(Dispatcher $dispatcher)
	{
		$this->dispatcher = $dispatcher;
	}

	/**
	 * Fires a log event.
	 *
	 * @param  string  $level
	 * @param  string  $channel
	 * @param  string  $message
	 * @param  array   $context
	 * @return void
	 */
	protected function fireLogEvent($level, $channel, $message, array $context = array())
	{
		// If the event dispatcher is set, we will pass along the parameters to the
		// log listeners. These are useful for building profilers or other tools
		// that aggregate all of the log messages for a given "request" cycle.
		if (isset($this->dispatcher))
		{
			$this->dispatcher->fire('illuminate.log', compact('level', 'channel', 'message', 'context'));
		}
	}

	/**
	 * Dynamically pass log calls into the writer.
	 *
	 * @param  mixed (level, param, param)
	 * @return mixed
	 */
	public function write()
	{
		$level = head(func_get_args());

		return call_user_func_array(array($this, $level), array_slice(func_get_args(), 1));
	}

	/**
	 * Dynamically handle error additions.
	 *
	 * @param  string  $method
	 * @param  mixed   $parameters
	 * @return mixed
	 *
	 * @throws \BadMethodCallException
	 */
	public function __call($method, $parameters)
	{
        //$parameters, 0:channel 1:message 2:context
		if (in_array($method, $this->levels))
		{
			$this->formatParameters($parameters);

			call_user_func_array(array($this, 'fireLogEvent'), array_merge(array($method), $parameters));

			$method = 'add'.ucfirst($method);

			return $this->callMonolog($method, $parameters);
		}

		throw new \BadMethodCallException("Method [$method] does not exist.");
	}

    /**
     * Format the parameters for the logger.
     *
     * @param $parameters
     * @throws \InvalidArgumentException
     */
    protected function formatParameters(&$parameters)
	{
        if(isset($parameters[0]) && !in_array($parameters[0], array_keys($this->loggers)))
        {
            if(isset($parameters[1]) && !is_array($parameters[1]))
            {
                throw new \InvalidArgumentException("Expect input parameter 2 to be context array as the parameter 1 is not a log channel.");
            }

            array_unshift($parameters, 'default');
        }

		if (isset($parameters[1]))
		{
			if (is_array($parameters[1]))
			{
				$parameters[1] = var_export($parameters[1], true);
			}
			elseif ($parameters[1] instanceof JsonableInterface)
			{
				$parameters[1] = $parameters[1]->toJson();
			}
			elseif ($parameters[1] instanceof ArrayableInterface)
			{
				$parameters[1] = var_export($parameters[1]->toArray(), true);
			}
		}
	}

}
