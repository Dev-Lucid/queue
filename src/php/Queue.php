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

    public $forbiddenMethodPrefix = '';
    public $forbiddenViewPrefix   = '';
    public $requiredMethodPrefix  = '';
    public $requiredViewPrefix    = '';

    public function parseCommandLineAction(array $argv)
    {
        array_shift($argv);

        if (count($argv) > 0) {
            $action = array_shift($argv);
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

            $this->add('request', $action, $parameters);
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


            list($controllerName, $method) = $this->splitAction($action);
            if($controllerName == 'view') {
                if ($this->forbiddenViewPrefix != '' && strpos($method, $this->forbiddenViewPrefix) === 0) {
                    throw new \Exception('For security reasons, views loaded directly from requests must NOT start with '.$this->forbiddenViewPrefix.'.');
                }
                if ($this->requiredViewPrefix != '' && strpos($method, $this->requiredViewPrefix) !== 0) {
                    throw new \Exception('For security reasons, views loaded directly from requests MUST start with '.$this->requiredViewPrefix.'.');
                }
            } else {
                if ($this->forbiddenMethodPrefix != '' && strpos($method, $this->forbiddenMethodPrefix) === 0) {
                    throw new \Exception('For security reasons, controller methods called directly from requests must NOT start with '.$this->forbiddenMethodPrefix.'.');
                }
                if ($this->requiredMethodPrefix != '' && strpos($method, $this->requiredMethodPrefix) !== 0) {
                    throw new \Exception('For security reasons, controller methods called directly from requests MUST start with '.$this->requiredMethodPrefix.'.');
                }
            }

            lucid::request()->un_set('action');

            $this->add('request', $action, lucid::request());
        }
    }

    public function add(string $when, string $action, $parameters = [])
    {
        if (isset($this->queues[$when]) === false) {
            $this->queues[$when] = [];
        }

        $this->queues[$when][] = [$action, $parameters];
    }

    public function process()
    {
        #lucid::logger()->debug(print_r($this->queues, true));
        foreach ($this->queues as $when=>$queue) {
            foreach($queue as $item) {
                $this->processItem($item[0], $item[1]);
            }
        }
    }

    public function processItem(string $action, $parameters=[])
    {
        list($controllerName, $method) = $this->splitAction($action);

        try {
            if ($controllerName == 'view') {
                # 'view' isn't a real controller
                lucid::logger()->info($controllerName.'->'.$method.'()');
                return lucid::mvc()->view($method, $parameters);
            } else {
                $controller = lucid::mvc()->controller($controllerName);
                lucid::logger()->info($controllerName.'->'.$method.'()');
                return call_user_func_array([$controller, $method], $this->buildParameters($controller, $method, $parameters));
            }
        } catch(\Exception $e) {
            lucid::error()->handle($e);
            return;
        }
    }

    protected function splitAction(string $action): array
    {
        $splitAction = explode('.', $action);
        if (count($splitAction) != 2) {
            throw new \Exception('Incorrect format for action: '.$action.'. An action must contain two parts, separated by a period. The leftside part is either a controller name or the word \'view\', and the rightside part is either a method of the controller, or the name of the view to load.');
        }
        return $splitAction;
    }

    protected function buildParameters($object, string $method, $parameters=[])
    {
        $objectClass = get_class($object);

        # we need to use the Request object's methods for casting parameters
        if(is_array($parameters) === true) {
            $parameters = new \Lucid\Component\Store\Store($parameters);
        }

        if (method_exists($objectClass, $method) === false) {
            throw new \Exception($objectClass.' does not contain a method named '.$method.'. Valid methods are: '.implode(', ', get_class_methods($objectClass)));
        }

        $r = new \ReflectionMethod($objectClass, $method);
        $methodParameters = $r->getParameters();

        # construct an array of parameters in the right order using the passed parameters
        $boundParameters = [];
        foreach ($methodParameters as $methodParameter) {
            $type = strval($methodParameter->getType());
            if ($parameters->is_set($methodParameter->name)) {
                if (is_null($type) === true || $type == '' || method_exists($parameters, $type) === false) {
                    $boundParameters[] = $parameters->raw($methodParameter->name);
                } else {
                    $boundParameters[] = $parameters->$type($methodParameter->name);
                }
            } else {
                if ($methodParameter->isDefaultValueAvailable() === true) {
                    $boundParameters[] = $methodParameter->getDefaultValue();
                } else {
                    throw new \Exception('Could not find a value to set for parameter '.$methodParameter->name.' of function '.$thisClass.'->'.$method.', and no default value was set.');
                }
            }
        }
        return $boundParameters;
    }
}
