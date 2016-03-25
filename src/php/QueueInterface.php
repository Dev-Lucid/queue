<?php
namespace Lucid\Component\Queue;

interface QueueInterface
{
    public function parseCommandLineAction(array $argv);
    public function parseRequestAction();
    public function add(string $when, array $route, $parameters = []);
    public function process();
    public function processItem(array $route, $parameters=[]);
}
