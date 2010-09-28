<?php

class CLImaxController
{
    protected $commandMap               = array();
    protected $usageCommands            = array();
    protected $defaultCommand           = NULL;
    protected $defaultCommandAlwaysRuns = false;
    protected $environment              = array();

    /**
     * @var string The character linking a command flag to its argument. Default NULL (ie whitespace).
     */
    protected $argLinker = NULL;

    const OPT_RETURN_INSTEAD_OF_EXIT    = 'returnInsteadOfExit';

    const ERR_USAGE                 = -1;

    public function __construct($opts = array())
    {
        $this->environment = $_ENV;
        $this->options = array_merge(array(
                                            self::OPT_RETURN_INSTEAD_OF_EXIT            => false,
        ), $opts);
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

        if (!is_array($aliases) && is_string($aliases))
        {
            $aliases = array($aliases);
        }

        foreach ($aliases as $alias) {
            if (isset($this->commandMap[$alias])) throw new Exception("Command " . get_class($this->commandMap[$alias]) . " has already been registered for alias {$alias}.");
            $this->commandMap[$alias] = $CLImaxCommand;
        }
        $this->usageCommands[] = array('aliases' => $aliases, 'command' => $CLImaxCommand);

        return $this;
    }

    public function setDefaultCommand($CLImaxCommand, $opts = array())
    {
        if ($this->defaultCommand) throw new Exception("A default command has already been registered.");

        $this->defaultCommand = $CLImaxCommand;
        $this->defaultCommandAlwaysRuns = (isset($opts['alwaysRuns']) && $opts['alwaysRuns']);

        return $this;
    }

    public function run($argv, $argc, $opts = array())
    {
        $commandNameRun = array_shift($argv);

        $result = 0;
        $commands = array();
        $previousCommand = NULL;

        // convert argv stack into processable list
        $cmd = NULL;
        $args = array();
        $defaultCommandArguments = array();
        while (true) {
            $token = array_shift($argv);
            //print "processing '{$token}'\n";
            if (!$token)    // reached end
            {
                if ($cmd)   // push last command
                {
                    $commands[] = array('command' => $cmd, 'arguments' => $args);
                    $cmd = NULL;
                    $args = array();
                }
                if (
                        $this->defaultCommand
                        and (count($commands) === 0 or $this->defaultCommandAlwaysRuns)
                   )
                {
                    //print "adding default command\n";
                    if (count($commands) >= 1)
                    {
                        $args = $defaultCommandArguments;
                    }
                    $commands[] = array('command' => $this->defaultCommand, 'arguments' => $args);
                }
                break;
            }

            $nextCmd = $this->commandForToken($token);
            if ($nextCmd)
            {
                if ($cmd)
                {
                    $commands[] = array('command' => $cmd, 'arguments' => $args);
                }
                else     // stash original set of arguments away for use with defaultCommand as needed
                {
                    $defaultCommandArguments = $args;
                }
                $cmd = $nextCmd;
                $args = array();
            }
            else
            {
                $args[] = $token;
            }
        }

        // @todo ENFORCE COMMAND ARGUMENT RULES (OPT/REQ/COUNT ETC)

        if (count($commands) === 0)
        {
            return $this->usage();
        }

        // run commands
        try {
            foreach ($commands as $key => $command) {
                //print "Calling " . get_class($command['command']) . "::run(" . join(', ', $command['arguments']) . ")";
                $cmdCallback = array($command['command'], 'run');
                if (!is_callable($cmdCallback)) throw new Exception("Not callable: " . var_export($cmdCallback, true));
                $result = call_user_func_array($cmdCallback, array($command['arguments'], $this));
                if (is_null($result)) throw new Exception("Command " . get_class($command['command']) . " returned NULL.");
                if ($result !== 0) break;
            }
        } catch (Exception $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(-1);
        }

        if (isset($this->options['returnInsteadOfExit']))
        {
            return $result;
        }
        else
        {
            exit($result);
        }
    }

    public function usage()
    {
        print "Usage:\n";
        foreach ($this->usageCommands as $usageInfo) {
            print $usageInfo['command']->getUsage($usageInfo['aliases'], $this->argLinker) . "\n";
        }
        if (isset($this->options['returnInsteadOfExit']))
        {
            return self::ERR_USAGE;
        }
        else
        {
            exit(self::ERR_USAGE);
        }
    }

    // returns the CLImaxCommand or NULL if not a command switch
    protected final function commandForToken($token)
    {
        if (isset($this->commandMap[$token])) return $this->commandMap[$token];
        return NULL;
    }
}

interface CLImaxCommand
{
    const ARG_NONE          = 'none';
    const ARG_OPTIONAL      = 'optional';
    const ARG_REQUIRED      = 'required';

    public function run($arguments, CLImaxController $cliController);
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
