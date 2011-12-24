<?php
/* vim: set syntax=php expandtab tabstop=4 shiftwidth=4: */

interface CLImaxCommand
{
    const ARG_NONE          = 'none';
    const ARG_OPTIONAL      = 'optional';
    const ARG_REQUIRED      = 'required';

    public function run($arguments, CLImaxController $cliController);
    public function getUsage($aliases, $argLinker);
    public function getDescription($aliases, $argLinker);
    /**
     * @return string One of the CLImaxCommand::ARG_* options.
     */
    public function getArgumentType();
    public function getAllowsMultipleUse();
}

// Everything but run()
abstract class CLImaxCommand_Base implements CLImaxCommand
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

        $description = $this->getDescription($aliases, $argLinker);
        if ($description)
        {
            $cmd .= "\n  {$description}\n";
        }

        return $cmd;
    }
    public function getDescription($aliases, $argLinker) { return NULL; }
}
