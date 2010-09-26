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
        print "Arguments: ";
        print_r($arguments);
    }
    //public function getArgumentType() { return CLImaxCommand::ARG_OPTIONAL; }
}
