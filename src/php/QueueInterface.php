<?php
namespace Lucid\Component\Queue;

interface QueueInterface
{
    public function parseCommandLineAction(array $argv);
    public function parseRequestAction($request);
    public function add(string $when, array $route, $parameters = []);
    public function process();
    public function processItem(array $route, $parameters=[]);
    public function clear();
}
