<?php

namespace Utopia\Servers;

use Utopia\DI\Container;
use Utopia\DI\Dependency;

abstract class Base
{
    /**
     * Mode Type
     */
    public const MODE_TYPE_DEVELOPMENT = 'development';
    public const MODE_TYPE_STAGE = 'stage';
    public const MODE_TYPE_PRODUCTION = 'production';

    /**
     * Current running mode
     *
     * @var string
     */
    protected static string $mode = '';

    /**
     * Errors
     *
     * Errors callbacks
     *
     * @var Hook[]
     */
    protected static array $errors = [];

    /**
     * Init
     *
     * A callback function that is initialized on application start
     *
     * @var Hook[]
     */
    protected static array $init = [];

    /**
     * Shutdown
     *
     * A callback function that is initialized on application end
     *
     * @var Hook[]
     */
    protected static array $shutdown = [];

    /**
     * Server start hooks
     *
     * @var Hook[]
     */
    protected static array $start = [];

    /**
     * Server end hooks
     *
     * @var Hook[]
     */
    protected static array $end = [];

    /**
     * @var Container
     */
    protected Container $container;

    /**
     * Base
     *
     * @param Adapter $server
     * @param  string  $timezone
     */
    // public function __construct(Adapter $server, Container $container, string $timezone)
    // {
    //     \date_default_timezone_set($timezone);
    //     $this->files = new Files();
    //     $this->server = $server;
    //     $this->container = $container;
    // }

    /**
     * Init
     *
     * Set a callback function that will be initialized on application start
     *
     * @return Hook
     */
    public static function init(): Hook
    {
        $hook = new Hook();
        $hook->groups(['*']);

        self::$init[] = $hook;

        return $hook;
    }

    /**
     * Shutdown
     *
     * Set a callback function that will be initialized on application end
     *
     * @return Hook
     */
    public static function shutdown(): Hook
    {
        $hook = new Hook();
        $hook->groups(['*']);

        self::$shutdown[] = $hook;

        return $hook;
    }

    /**
     * Error
     *
     * An error callback for failed or no matched requests
     *
     * @return Hook
     */
    public static function error(): Hook
    {
        $hook = new Hook();
        $hook->groups(['*']);

        self::$errors[] = $hook;

        return $hook;
    }

    /**
     * Get Mode
     *
     * Get current mode
     *
     * @return string
     */
    public static function getMode(): string
    {
        return self::$mode;
    }

    /**
     * Set Mode
     *
     * Set current mode
     *
     * @param  string  $value
     * @return void
     */
    public static function setMode(string $value): void
    {
        self::$mode = $value;
    }

    /**
     * Get Container
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Set Container
     *
     * @return $this
     */
    public function setContainer(Container $container): self
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Is http in production mode?
     *
     * @return bool
     */
    public static function isProduction(): bool
    {
        return self::MODE_TYPE_PRODUCTION === self::$mode;
    }

    /**
     * Is http in development mode?
     *
     * @return bool
     */
    public static function isDevelopment(): bool
    {
        return self::MODE_TYPE_DEVELOPMENT === self::$mode;
    }

    /**
     * Is http in stage mode?
     *
     * @return bool
     */
    public static function isStage(): bool
    {
        return self::MODE_TYPE_STAGE === self::$mode;
    }

    public static function onStart(): Hook
    {
        $hook = new Hook();
        self::$start[] = $hook;
        return $hook;
    }

    public static function onEnd(): Hook
    {
        $hook = new Hook();
        self::$end[] = $hook;
        return $hook;
    }

    abstract public function start();

    /**
     * Prepare hook for injection, add dependencies, run validation.
     *
     * @param  Hook  $hook
     * @param  array  $values
     * @param  array  $requestParams
     * @return Container
     *
     * @throws Exception
     */
    protected function prepare(Container $context, Hook $hook, array $values = [], array $requestParams = []): Container
    {
        foreach ($hook->getParams() as $key => $param) { // Get value from route or request object
            $existsInRequest = \array_key_exists($key, $requestParams);
            $existsInValues = \array_key_exists($key, $values);
            $paramExists = $existsInRequest || $existsInValues;
            $arg = $existsInRequest ? $requestParams[$key] : $param['default'];
            if (\is_callable($arg)) {
                $injections = array_map(fn($injection)=>$this->getContainer()->get($injection), $param['injections']);
                $arg = \call_user_func_array($arg, $injections);
            }
            $value = $existsInValues ? $values[$key] : $arg;

            /**
             * Validation
             */
            if (!$param['skipValidation']) {
                if (!$paramExists && !$param['optional']) {
                    throw new Exception('Param "' . $key . '" is not optional.', 400);
                }

                if ($paramExists) {
                    $this->validate($key, $param, $value, $context);
                }
            }

            $hook->setParamValue($key, $value);

            $dependencyForValue = new Dependency();
            $dependencyForValue
                ->setName($key)
                ->setCallback(fn () => $value)
            ;

            $context->set($dependencyForValue);
        }

        return $context;
    }

    /**
     * Validate Param
     *
     * Creates an validator instance and validate given value with given rules.
     *
     * @param  string  $key
     * @param  array  $param
     * @param  mixed  $value
     * @return void
     *
     * @throws Exception
     */
    protected function validate(string $key, array $param, mixed $value, Container $context): void
    {
        if ($param['optional'] && \is_null($value)) {
            return;
        }

        $validator = $param['validator']; // checking whether the class exists

        if (\is_callable($validator)) {
            $dependency = new Dependency();
            $dependency
                ->setName('_validator:'.$key)
                ->setCallback($param['validator'])
            ;

            foreach ($param['injections'] as $injection) {
                $dependency->inject($injection);
            }

            $validator = $context->inject($dependency);
        }

        if (!$validator instanceof Validator) { // is the validator object an instance of the Validator class
            throw new Exception('Validator object is not an instance of the Validator class', 500);
        }

        if (!$validator->isValid($value)) {
            throw new Exception('Invalid `' . $key . '` param: ' . $validator->getDescription(), 400);
        }
    }

    /**
     * Reset all the static variables
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$mode = '';
        self::$errors = [];
        self::$init = [];
        self::$shutdown = [];
        self::$start = [];
        self::$end = [];
    }
}
