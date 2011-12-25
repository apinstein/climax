<?php
/* vim: set syntax=php expandtab tabstop=4 shiftwidth=4: */

require dirname(__FILE__).'/CLImaxException.php';
require dirname(__FILE__).'/CLImaxCommand.php';
require dirname(__FILE__).'/commands/CLImaxCommand_EnvironmentOption.php';

class CLImaxController
{
    protected $commandMap               = array();
    protected $usageCommands            = array();
    protected $defaultCommand           = NULL;
    protected $defaultCommandAlwaysRuns = true;
    protected $environment              = array();

    /**
     * @var string The character linking a command flag to its argument. Default NULL (ie whitespace).
     */
    protected $argLinker = NULL;

    const OPT_RETURN_INSTEAD_OF_EXIT    = 'returnInsteadOfExit';
    const OPT_SLIENT                    = 'silent';

    const ERR_USAGE                 = -1;

    public function __construct($opts = array())
    {
        $this->environment = $_ENV;
        $this->options = array_merge(array(
                                            self::OPT_RETURN_INSTEAD_OF_EXIT            => false,
                                            self::OPT_SLIENT                            => false,
        ), $opts);
    }

    public static function create($opts = array())
    {
        return new CLImaxController($opts);
    }

    public function mergeEnvironment($env, $opts = array())
    {
        if (!is_array($env)) throw new CLImax_Exception("Array required.");

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

    public function setEnvironment($key, $value = NULL)
    {
        if (is_array($key))
        {
            if ($value !== NULL) throw new CLImax_Exception("When calling setEnvironment() with an array, only 1 parameter is accepted.");
            $this->environment = $key;
        }
        else
        {
            $this->environment[$key] = $value;
        }
        return $this;
    }

    public function getEnvironment($key = NULL)
    {
        if ($key)
        {
            if (!array_key_exists($key, $this->environment)) return NULL;

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
        if (!($CLImaxCommand instanceof CLImaxCommand)) throw new CLImax_Exception("CLImaxCommand required.");

        if (!is_array($aliases))
        {
            $aliases = array($aliases);
        }

        if (count($aliases) === 0) throw new CLImax_Exception("addCommand() requires at least one alias.");

        foreach ($aliases as $alias) {
            if (isset($this->commandMap[$alias])) throw new CLImax_Exception("Command " . get_class($this->commandMap[$alias]) . " has already been registered for alias {$alias}.");
            $this->commandMap[$alias] = $CLImaxCommand;
        }
        $this->usageCommands[] = array('aliases' => $aliases, 'command' => $CLImaxCommand);

        return $this;
    }

    public function addEnvironmentFlagWithExactlyOneArgument($key, $aliases = NULL, $opts = array())
    {
        if ($aliases === NULL)
        {
            $aliases = "--{$key}";
        }
        $opts = array_merge($opts, array('requiresArgument' => true));   // requiresArgument should always win
        $this->addCommand(new CLImaxCommand_EnvironmentOption($key, $opts), $aliases);
        return $this;
    }

    public function addEnvironmentFlagSetsValue($key, $flagSetsValue, $aliases = NULL, $opts = array())
    {
        if ($aliases === NULL)
        {
            $aliases = "--{$key}";
        }
        $opts = array_merge($opts, array('requiresArgument' => false, 'noArgumentValue' => $flagSetsValue)); // these values always win
        $this->addCommand(new CLImaxCommand_EnvironmentOption($key, $opts), $aliases);
        return $this;
    }

    public function setDefaultCommand($CLImaxCommand, $opts = array())
    {
        if ($this->defaultCommand) throw new CLImax_Exception("A default command has already been registered.");

        $this->defaultCommand = $CLImaxCommand;
        $this->defaultCommandAlwaysRuns = (isset($opts['alwaysRuns']) && $opts['alwaysRuns']);

        return $this;
    }

    private function setupCompleteCheck()
    {
        if (count($this->commandMap) === 0 && is_null($this->defaultCommand))
        {
            throw new CLImax_Exception("No commands specified!");
        }
    }

    public function run($argv, $argc, $opts = array())
    {
        $this->options = array_merge($this->options, $opts);

        $this->setupCompleteCheck();

        $commandNameRun = array_shift($argv);

        $result = 0;
        $commands = array();
        $previousCommand = NULL;

        // convert argv stack into processable list
        $cmd = NULL;
        $cmdToken = NULL;
        $args = array();
        $defaultCommandArguments = array();
        while (true) {
            $token = array_shift($argv);
            //print "processing '{$token}'\n";
            if ($token === NULL)    // reached end
            {
                if ($cmd)   // push last command
                {
                    $commands[] = array('command' => $cmd, 'arguments' => $args, 'token' => $cmdToken);
                    $cmd = NULL;
                    $args = array();
                }
                // we reached the end of argument processing; should we run the default command for any reason? (no commands or alwaysRuns)
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
                    $commands[] = array('command' => $this->defaultCommand, 'arguments' => $args, 'token' => '<default>');
                }
                break;
            }

            // @todo There is a subtle bug here; a token intended to be an argument for one command, for which an actual command is aliased to the token, the token will be interpreted as a COMMAND not a argument to the previous command...
            $nextCmd = $this->commandForToken($token);
            if ($nextCmd)
            {
                if ($cmd)
                {
                    $commands[] = array('command' => $cmd, 'arguments' => $args, 'token' => $cmdToken);
                }
                else     // stash original set of arguments away for use with defaultCommand as needed
                {
                    $defaultCommandArguments = $args;
                }
                $cmd = $nextCmd;
                $cmdToken = $token;
                $args = array();
            }
            else
            {
                $args[] = $token;
            }
        }

        if (count($commands) === 0)
        {
            return $this->usage();
        }

        // run commands
        $currentCommand = NULL;
        try {
            foreach ($commands as $key => $command) {
                $currentCommand = $command;
                //print "Calling " . get_class($command['command']) . "::run(" . join(', ', $command['arguments']) . ")";
                $cmdCallback = array($command['command'], 'run');
                if (!is_callable($cmdCallback)) throw new CLImax_Exception("Not callable: " . var_export($cmdCallback, true));
                $result = call_user_func_array($cmdCallback, array($command['arguments'], $this));
                if (is_null($result)) throw new CLImax_Exception("Command " . get_class($command['command']) . " returned NULL.");
                if ($result !== 0) break;
            }
        } catch (CLImaxCommand_ArugumentException $e) {
            $this->options[self::OPT_SLIENT] || fwrite(STDERR, "Error processing {$currentCommand['token']}: {$e->getMessage()}\n");
            $result = -2;
        } catch (CLImax_Exception $e) {
            $this->options[self::OPT_SLIENT] || fwrite(STDERR, "CLImax exception: {$e->getMessage()}\n");
            $result = -2;
        } catch (Exception $e) {
            $this->options[self::OPT_SLIENT] || fwrite(STDERR, get_class($e) . ": {$e->getMessage()}\n{$e->getTraceAsString()}\n");
            $result = -1;
        }

        if ($this->options['returnInsteadOfExit'])
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
        print "Usage:\n------\n";
        foreach ($this->usageCommands as $usageInfo) {
            print $usageInfo['command']->getUsage($usageInfo['aliases'], $this->argLinker) . "\n";
        }
        if ($this->options['returnInsteadOfExit'])
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
