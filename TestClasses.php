<?php

class CLIHelloWorld extends CLIMax_BaseCommand
{
    public function run($arguments, $environment, $commands, $nextCommand, $previousCommand)
    {
        print "Hello, world!\n";
        return 0;
    }
}
class CLIArgRepeater extends CLIMax_BaseCommand
{
    public function run($arguments, $environment, $commands, $nextCommand, $previousCommand)
    {
        $this->testArguments($arguments);
        return 0;
    }
    public function testArguments($arguments)
    {
        return count($arguments);
    }
}
