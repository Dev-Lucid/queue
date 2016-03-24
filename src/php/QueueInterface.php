<?php
namespace Lucid\Component\Queue;

interface QueueInterface
{
    public function parseCommandLineAction(array $argv);
    public function parseRequestAction();
    public function add(string $when, string $action, $parameters = []);
    public function process();
    public function processItem(string $action, $parameters=[]);
}
