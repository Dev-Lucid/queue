<?php
namespace Lucid\Component\Queue;
use Lucid\Lucid;

class Queue implements QueueInterface
{
    protected $queues = [
        'pre'=>[],
        'request'=>[],
        'post'=>[],
    ];
    protected $logger  = null;
    protected $router  = null;
    protected $factory = null;

    public $forbiddenMethodPrefix = '';
    public $forbiddenViewPrefix   = '';
    public $requiredMethodPrefix  = '';
    public $requiredViewPrefix    = '';

    public function __construct($logger = null, $router = null, $factory = null)
    {
        if (is_null($logger)) {
            $this->logger = new \Lucid\Component\BasicLogger\BasicLogger();
        } else {
            if (is_object($logger) === false || in_array('Psr\\Log\\LoggerInterface', class_implements($logger)) === false) {
                throw new \Exception('Queue contructor parameter $logger must either be null, or implement Psr\\Log\\LoggerInterface. If null is passed, then an instance of Lucid\\Component\\BasicLogger\\BasicLogger will be instantiated instead, and all messages will be passed along to error_log();');
            }
            $this->logger = $logger;
        }
        if (is_null($router)) {
            $this->router = new \Lucid\Component\Router\Router($this->logger);
        } else {
            if (is_object($router) === false || in_array('Lucid\\Component\\Router\\RouterInterface', class_implements($router)) === false) {
                throw new \Exception('Queue contructor parameter $router must either be null, or implement Lucid\\Component\\Router\\RouterInterface. If null is passed, then an instance of Lucid\\Component\\Router\\Router will be instantiated instead.');
            }
            $this->router = $router;
        }
        if (is_null($factory)) {
            $this->factory = new \Lucid\Component\Factory\Factory($this->logger);
        } else {
            if (is_object($factory) === false || in_array('Lucid\\Component\\Factory\\FactoryMinimalInterface', class_implements($factory)) === false) {
                throw new \Exception('Queue contructor parameter $factory must either be null, or implement Lucid\\Component\\Factory\\FactoryMinimalInterface. If null is passed, then an instance of Lucid\\Component\\Factory\\Factory will be instantiated instead.');
            }
            $this->factory = $factory;
        }
    }

    public function parseCommandLineAction(array $argv)
    {
        array_shift($argv);

        if (count($argv) > 0) {
            $action = array_shift($argv);
            $route = $this->router->determineRoute($action);
            $parameters = [];
            while (count($argv) > 0) {
                $parts = explode('=', array_shift($argv));
                $key = array_shift($parts);
                if (count($parts) > 0) {
                    $value = implode('=', $parts);
                } else {
                    $value = true;
                }
                $parameters[$key] = $value;
            }

            $this->add('request', $route, $parameters);
        } else {
            trigger_error('Queue->parseCommandLineAction was called, but no action was found.', E_USER_NOTICE);
        }
    }

    public function parseRequestAction()
    {
        # setup the action request
        #if (lucid::$use_rewrite === true) # figure out how to do this using php development server
        if (lucid::request()->string('action', false) !== false) {

            # check for forbidden/required prefixes
            $action = lucid::request()->string('action');
            $route = $this->router->determineRoute($action);

            if($route['type'] == 'view') {
                if ($this->forbiddenViewPrefix != '' && strpos($route['class'], $this->forbiddenViewPrefix) === 0) {
                    throw new \Exception('For security reasons, views loaded directly from requests must NOT start with '.$this->forbiddenViewPrefix.'.');
                }
                if ($this->requiredViewPrefix != '' && strpos($route['class'], $this->requiredViewPrefix) !== 0) {
                    throw new \Exception('For security reasons, views loaded directly from requests MUST start with '.$this->requiredViewPrefix.'.');
                }
            } else {
                if ($this->forbiddenMethodPrefix != '' && strpos($route['method'], $this->forbiddenMethodPrefix) === 0) {
                    throw new \Exception('For security reasons, controller methods called directly from requests must NOT start with '.$this->forbiddenMethodPrefix.'.');
                }
                if ($this->requiredMethodPrefix != '' && strpos($route['method'], $this->requiredMethodPrefix) !== 0) {
                    throw new \Exception('For security reasons, controller methods called directly from requests MUST start with '.$this->requiredMethodPrefix.'.');
                }
            }

            lucid::request()->un_set('action');

            $this->add('request', $route, lucid::request());
        }
    }

    public function add(string $when, array $route, $parameters = [])
    {
        if (isset($this->queues[$when]) === false) {
            $this->queues[$when] = [];
        }
        $this->queues[$when][] = [$route, $parameters];
    }

    public function process()
    {
        foreach ($this->queues as $when=>$queue) {
            foreach($queue as $item) {
                $this->processItem($item[0], $item[1]);
            }
        }
    }

    public function processItem(array $route, $parameters=[])
    {
        $type   = $route['type'];
        $class  = $route['class'];
        $method = $route['method'];
        $this->logger->info($class.'->'.$method.'()');

        $object = $this->factory->$type($route['class']);
        $parameters = $this->factory->buildParameters($object, $method, $parameters);
        return $object->$method(...$parameters);
    }
}
