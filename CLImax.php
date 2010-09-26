<?php

class CLImaxController
{
    protected $commandMap = array();
    protected $defaultCommand = NULL;
    protected $environment = array();

    /**
     * @var string The character linking a command flag to its argument. Default NULL (ie whitespace).
     */
    protected $argLinker = NULL;

    public function __construct($opts = array())
    {
        $this->environment = $_ENV;
    }

    public static function create($opts = array())
    {
        return new CLImaxController($opts);
    }

    public function mergeEnvironment($env, $opts = array())
    {
        if (!is_array($env)) throw new Exception("Array required.");

        if (isset($opts['overwrite']) && $opts['overwrite'])
        {
            $this->environment = array_merge($this->environment, $env);
        }
        else
        {
            $this->environment = array_merge($env, $this->environment);
        }
        return $this;
    }

    public function setEnvironment($env)
    {
        $this->environment = $env;
        return $this;
    }

    public function getEnvironment($key = NULL)
    {
        if ($key)
        {
            return $this->environment[$key];
        }
        else
        {
            return $this->environment;
        }
    }

    public function hasEnvironment($key)
    {
        return array_key_exists($key, $this->environment);
    }

    public function addCommand($CLImaxCommand, $aliases = array())
    {
        if (!($CLImaxCommand instanceof CLImaxCommand)) throw new Exception("CLImaxCommand required.");

        foreach ($aliases as $alias) {
            if (isset($this->commandMap[$alias])) throw new Exception("Command " . get_class($this->commandMap[$alias]) . " has already been registered for alias {$alias}.");
            $this->commandMap[$alias] = $CLImaxCommand;
        }

        return $this;
    }

    public function setDefaultCommand($CLImaxCommand)
    {
        if ($this->defaultCommand) throw new Exception("A default command has already been registered.");

        $this->defaultCommand = $CLImaxCommand;

        return $this;
    }

    protected function commandForFlag($flag)
    {
        if (!isset($this->commandMap[$flag])) throw new Exception("Couldn't find command for flag: {$flag}");
        return $this->commandMap[$flag];
    }

    public function run($argv, $argc)
    {
        $commandNameRun = array_shift($argv);

        print_r($argv);

        $result = 0;
        $commands = array();
        $previousCommand = NULL;

        // convert argv stack into processable list
        while ($token = array_shift($argv)) {
            $cmd = $this->commandForToken($token);
            if ($cmd)
            {
                // parse out arguments
                switch ($cmd->getArgumentType()) {
                    case CLImaxCommand::ARG_NONE;
                        $args = array();
                        break;
                    case CLImaxCommand::ARG_OPTIONAL;
                    case CLImaxCommand::ARG_REQUIRED;
                        $args = array();
                        while (count($argv)) {
                            // is next token a command or an argument?
                            if ($this->commandForToken($argv[0])) break;

                            $args[] = array_shift($argv);
                        }
                        if (count($args) === 0 && $cmd->getArgumentType() === CLImaxCommand::ARG_REQUIRED) throw new Exception("Argument required for {$token}.");
                        break;
                }

                $commands[] = array('command' => $cmd, 'arguments' => $args);
            }
            else
            {
                // no-op; skip non-flag tokens
                print "not sure what to do with: {$token}\nMaybe Print Usage and Bail?\n";
            }
        }

        if ($this->defaultCommand)
        {
            $result = $this->defaultCommand->run(array(), $this->environment, $commands, NULL, $previousCommand);
        }
        exit($result);
    }

    // returns the CLImaxCommand or NULL if not a command switch
    protected final function commandForToken($token)
    {
        if (preg_match('/^-(\w)$/', $token, $matches) || preg_match('/^--(\w+)$/', $token, $matches))
        {
            $flag = $matches[1];
            return $this->commandForFlag($token);
        }
        return NULL;
    }
}

interface CLImaxCommand
{
    const ARG_NONE          = 'none';
    const ARG_OPTIONAL      = 'optional';
    const ARG_REQUIRED      = 'required';

    public function run($arguments, $environment, $commands, $nextCommand, $previousCommand);
    public function getUsage($aliases, $argLinker);
    public function getDescription($aliases, $argLinker);
    public function getArgumentType();
    public function getAllowsMultipleUse();
}

abstract class CLIMax_BaseCommand implements CLImaxCommand
{
    public function getAllowsMultipleUse() { return false; }
    public function getArgumentType() { return CLImaxCommand::ARG_NONE; }
    public function getUsage($aliases, $argLinker)
    {
        // calculate arg linker string
        switch ($this->getArgumentType()) {
            case CLImaxCommand::ARG_NONE:
                $argLinker = NULL;
                break;
            default:
                $argLinker = "{$argLinker}<arg>";
                break;
        }
        $cmd = NULL;
        foreach ($aliases as $alias) {
            if ($cmd)
            {
                $cmd .= " / ";
            }
            $cmd .= "{$alias}{$argLinker}";
        }

        return $cmd;
    }
    public function getDescription($aliases, $argLinker) { return NULL; }
}
