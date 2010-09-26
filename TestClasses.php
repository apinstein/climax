<?php

class CLIHelloWorld extends CLIMax_BaseCommand
{
    public function run($arguments, $environment, $commands, $nextCommand, $previousCommand)
    {
        print "Hello, world!\n";
    }
}
class CLIArgRepeater extends CLIMax_BaseCommand
{
    public function run($arguments, $environment, $commands, $nextCommand, $previousCommand)
    {
        return count($arguments);
    }
    //public function getArgumentType() { return CLImaxCommand::ARG_OPTIONAL; }
}
