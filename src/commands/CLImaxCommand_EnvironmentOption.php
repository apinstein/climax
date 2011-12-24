<?php
/* vim: set syntax=php expandtab tabstop=4 shiftwidth=4: */

class CLImaxCommand_EnvironmentOption extends CLImaxCommand_Base
{
    protected $environmentKey;
    protected $requiresArgument;
    protected $allowsMultipleArguments;
    protected $noArgumentValue;
    protected $allowedValues;

    public function __construct($environmentKey, $opts = array())
    {
        $this->environmentKey = $environmentKey;
        $opts = array_merge(array(
                                    'requiresArgument'          => false,
                                    'allowsMultipleArguments'   => false,
                                    'noArgumentValue'           => NULL,
                                    'allowedValues'             => NULL,
        ), $opts);
        $this->requiresArgument = $opts['requiresArgument'];
        $this->allowsMultipleArguments = $opts['allowsMultipleArguments'];
        $this->noArgumentValue = $opts['noArgumentValue'];
        $this->allowedValues = $opts['allowedValues'];
    }
    public function run($arguments, CLImaxController $cliController)
    {
        // argument checks
        if ($this->requiresArgument && count($arguments) === 0) throw new CLImaxCommand_ArugumentException("Argument required.");
        if (!$this->allowsMultipleArguments && count($arguments) > 1) throw new CLImaxCommand_ArugumentException("Only one argument accepted.");

        if (count($arguments) === 0 && $this->noArgumentValue)
        {
            $arguments = array($this->noArgumentValue);
        }

        if (!is_array($arguments)) throw new CLImax_Exception("Arguments should be an array but wasn't. Internal fail.");
        if ($this->allowedValues)
        {
            $badArgs = array_diff($arguments, $this->allowedValues);
            if (count($badArgs) > 0) throw new CLImaxCommand_ArugumentException("Invalid argument(s): " . join(', ', $badArgs));
        }

        // flatten argument to a single value as a convenience for working with the environment data later
        if (count($arguments) === 1)
        {
            $arguments = $arguments[0];
        }

        $cliController->setEnvironment($this->environmentKey, $arguments);
        return 0;
    }
}
