<?php
namespace App\View;

class Test1 extends \App\View
{
    public function echoHi()
    {
        echo("Hi from Test1.view.echoHi\n");
    }

    public function echoHello()
    {
        echo("Hello from Test1.view.echoHello\n");
    }

    public function echoGreetings()
    {
        echo("Greetings from Test1.view.echoGreetings\n");
    }
}