<?php
use Lucid\Component\Queue\Queue;

class LoadTest extends \PHPUnit_Framework_TestCase
{
    public $queue = null;
    public function setup()
    {
        $router = new \Lucid\Component\Router\Router();

        $config = new \Lucid\Component\Container\Container();
        $config->set('root', realpath(__DIR__.'/app/'));
        $factory = new \Lucid\Component\Factory\Factory(null, $config);

        $this->queue = new Queue(null, $router, $factory);
    }

    public function testRunViewInOrder()
    {
        $this->queue->add('request', ['type'=>'view', 'class'=>'Test1', 'method'=>'echoHi'], []);
        $this->expectOutputString("Hi from Test1.view.echoHi\n");
        $this->queue->process();
        ob_get_clean();
        ob_start();

        $this->queue->add('request', ['type'=>'view', 'class'=>'Test1', 'method'=>'echoHi'], []);
        $this->queue->add('request', ['type'=>'view', 'class'=>'Test1', 'method'=>'echoHello'], []);
        $this->expectOutputString("Hi from Test1.view.echoHi\nHello from Test1.view.echoHello\n");
        $this->queue->process();

        ob_get_clean();
        ob_start();

        $this->queue->add('request', ['type'=>'view', 'class'=>'Test1', 'method'=>'echoHello'], []);
        $this->queue->add('request', ['type'=>'view', 'class'=>'Test1', 'method'=>'echoHi'], []);
        $this->expectOutputString("Hello from Test1.view.echoHello\nHi from Test1.view.echoHi\n");
        $this->queue->process();

        ob_get_clean();
        ob_start();

        $this->queue->add('pre', ['type'=>'view', 'class'=>'Test1', 'method'=>'echoHi'], []);
        $this->queue->add('request', ['type'=>'view', 'class'=>'Test1', 'method'=>'echoHello'], []);
        $this->queue->add('post', ['type'=>'view', 'class'=>'Test1', 'method'=>'echoGreetings'], []);
        $this->expectOutputString("Hi from Test1.view.echoHi\nHello from Test1.view.echoHello\nGreetings from Test1.view.echoGreetings\n");
        $this->queue->process();

        /*
        ob_get_clean();
        ob_start();
        */

    }
}