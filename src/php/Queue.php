<?php
namespace Lucid\Component\Queue;

class Queue implements QueueInterface
{
    protected $queues = [
        'pre'=>[],
        'request'=>[],
        'post'=>[],
    ];

    public function parseCommandLineAction()
    {
        array_shift($argv);
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
    }

    public function parseRequestAction()
    {
        # setup the action request
        #if (lucid::$use_rewrite === true) # figure out how to do this using php development server
        if (\Lucid\lucid('request')->string('action', false) !== false) {
            $action = lucid::$request->string('action');
            \Lucid\lucid('request')->un_set('action');
            $this->add('request', $action, lucid::$request);
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
        foreach ($this->queues as $when=>$queue) {
            foreach($queue as $item) {
                return $this->processItem($item[0], $item[1]);
            }
        }
    }

    public function processItem(string $action, $parameters=[])
    {
        \Lucid\lucid('mvc')->callAction($action, $parameters);
    }
}
