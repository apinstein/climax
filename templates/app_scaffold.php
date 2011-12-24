#!/usr/bin/env php
<?php echo "<?php\n"; ?>
/* vim: set syntax=php expandtab tabstop=4 shiftwidth=4: */
/**
 * Created by CLImax http://github.com/apinstein/climax
 * <?php echo date('r'); ?>
 */

require_once "CLImax.php";
class CLImaxSampleCommand extends CLImaxCommand_Base
{
    public function run($arguments, CLImaxController $cliController)
    {
        // do something interesting
        print "Sample...\n";
        // or throw new Exception("error", $returnCode);
        return 0;
    }
}

// WIRE UP APPLICTION
CLImaxController::create()
                  ->addCommand(new CLImaxSampleCommand, array("sample"))
                  ->run($argv, $argc);

