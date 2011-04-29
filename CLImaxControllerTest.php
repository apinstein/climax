<?php

require_once 'CLImax.php';
require_once 'TestClasses.php';

/**
 * Test class for CLImaxController.
 */
class CLImaxControllerTest extends PHPUnit_Framework_TestCase
{
    /**
     * Generates the argv/argc vars that php creates when using CLI.
     *
     * This is useful for converting a shell command string into argv/argc for testing so it can be passed into run().
     *
     * @param string command
     * @return array Assoc array with argv/argc.
     */
    public function generateArgvArgc($cmd)
    {
        $cmd = "php -r 'var_export(array(\"argv\" => \$argv, \"argc\" => \$argc));' {$cmd}";

        exec($cmd, $output, $retVal);
        if ($retVal != 0)
        {
            throw new Exception("Error running argv/argc generator with {$cmd}");
        }

        $argvArgc = join("\n", $output);
        $loadArgvArgcCmd = "return {$argvArgc};";
        $argvArgc = eval($loadArgvArgcCmd);
        if ($argvArgc === false)
        {
            throw new Exception("Error generating argv/argc for {$cmd}");
        }
        // fix "\\-" to -
        for ($i = 0; $i < count($argvArgc['argv']); $i++) {
            while (preg_match('/^[-]*[\\\\]+./', $argvArgc['argv'][$i])) {
                $argvArgc['argv'][$i] = preg_replace('/^(-*)\\\\-/', '$1-', $argvArgc['argv'][$i]);
            }
        }
        return $argvArgc;
    }

    /**
     * @testdox Ensure generateArgvArgc() test helper works.
     */
    public function testEnsureArgvArgcGeneratorWorks()
    {
        extract($this->generateArgvArgc("foo bar"));
        $this->assertEquals(array('-', 'foo', 'bar'), $argv);
        $this->assertEquals(3, $argc);

        extract($this->generateArgvArgc("a=b c"));
        $this->assertEquals(array('-', 'a=b', 'c'), $argv);
        $this->assertEquals(3, $argc);
    }

    /**
     * @testdox Has fluent constructor named CLImaxController::create()
     */
    public function testHasFluentStaticConstructorNamedCreate()
    {
        $o = CLImaxController::create();
        $this->assertTrue( $o instanceof CLImaxController );
    }

    /**
     * @testdox Reads default environemnt from $_ENV
     */
    public function testDefaultEnvironment()
    {
        $_ENV = array('foo' => 'bar');

        $o = CLImaxController::create();
        $this->assertTrue($o->hasEnvironment('foo'));
        $this->assertEquals('bar', $o->getEnvironment('foo'));
    }

    public function testMergeEnvironmentDoesNotOverwriteKeysByDefault()
    {
        $_ENV = array('foo' => 'bar');

        $o = CLImaxController::create();

        // test merge w/no overwrite
        $o->mergeEnvironment(array('foo' => 'baz does not overwrite bar without force'));
        $this->assertEquals('bar', $o->getEnvironment('foo'));
    }

    /**
     * @testdox Merge Environment Overwrites Keys if array('overwrite' => true) passed
     */
    public function testMergeEnvironmentOverwritesKeysIfOverwriteEnabled()
    {
        $_ENV = array('foo' => 'bar');

        $o = CLImaxController::create();

        // test merge w/overwrite
        $o->mergeEnvironment(array('foo' => 'baz overwrites bar'), array('overwrite' => true));
        $this->assertEquals('baz overwrites bar', $o->getEnvironment('foo'));
    }

    public function testSetEnvironmentReplacesEntireEnvironment()
    {
        $env = array('foo' => 'bar', 'boo' => 'baz');
        $o = CLImaxController::create();

        // test merge w/no overwrite
        $o->setEnvironment($env);
        $this->assertEquals($env, $o->getEnvironment());
    }

    /**
     * @testdox "cliapp cmd" runs "cmd" with no arguments
     */
    public function testCommandRunsIfPresentInArgs()
    {
        extract($this->generateArgvArgc("hw"));

        $mock = $this->getMock('CLIHelloWorld', array('run'));
        $mock->expects($this->once())
                        ->method('run')
                        ->will($this->returnValue(0));

        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                               ->addCommand($mock, array('hw'))
                               ->run($argv, $argc);
    }

    /**
     * @testdox "cliapp cmd 1 2 3 4 5" runs "cmd" with arguments array(1,2,3,4,5)
     */
    public function testCommandGetsExpectedArguments()
    {
        extract($this->generateArgvArgc("repeat 1 2 3 4 5"));

        $ar = new CLIArgRepeater;
        // ensure proper arguments to run()
        $ar = $this->getMock('CLIArgRepeater', array('testArguments'));
        $ar->expects($this->once())
                        ->method('testArguments')
                        ->with($this->equalTo(array(1,2,3,4,5)));
        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                               ->addCommand($ar, array('repeat'))
                               ->run($argv, $argc);
        $this->assertEquals(0, $o);
    }

    /**
     * @testdox "cliapp" will print usage if no default command is specified
     */
    public function testUsagePrintedIfNoCommandSpecifiedAndNoDefaultCommandSpecified()
    {
        extract($this->generateArgvArgc(""));

        // ensure proper arguments to run()
        $cli = $this->getMock('CLImaxController', array('usage'));
        $cli->expects($this->once())
                        ->method('usage');
        $result = $cli->run($argv, $argc, array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true));
    }

    /**
     * @testdox "cliapp" runs default command with no arguments if a command is specified with setDefaultCommand()
     */
    public function testDefaultCommandRunsWithNoArgumentsIfNoCommandsOrArgumentsSpecified()
    {
        extract($this->generateArgvArgc(""));

        $def = $this->getMock('CLIArgRepeater', array('testArguments'));
        $def->expects($this->once())
                        ->method('testArguments')
                        ->with($this->equalTo(array()));
        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                               ->setDefaultCommand($def)
                               ->run($argv, $argc);
        $this->assertEquals(0, $o);
    }

    /**
     * @testdox "cliapp 1 2 3 4 5" runs default command with array(1,2,3,4,5)
     */
    public function testDefaultCommandGetsAllArgumentsIfNoOtherCommandSpecified()
    {
        extract($this->generateArgvArgc("1 2 3 4 5"));

        // ensure proper arguments to run()
        $def = $this->getMock('CLIArgRepeater', array('testArguments'));
        $def->expects($this->once())
                        ->method('testArguments')
                        ->with($this->equalTo(array(1,2,3,4,5)));
        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                               ->setDefaultCommand($def)
                               ->run($argv, $argc);
        $this->assertEquals(0, $o);
    }

    /**
     * @testdox "cliapp cmd 1 2 3 4 5" does not run default command by default
     */
    public function testDefaultCommandDoesNotRunIfAlwaysRunOptionDisabled()
    {
        extract($this->generateArgvArgc("ar 1 2 3 4 5"));

        $ar = new CLIArgRepeater;
        $def = $this->getMock('CLIArgRepeater', array('run'));
        $def->expects($this->never())
                        ->method('run');
        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                               ->setDefaultCommand($def)
                               ->addCommand($ar, 'ar')
                               ->run($argv, $argc);
        $this->assertEquals(0, $o);
    }

    /**
     * @testdox "cliapp cmd 1 2 3 4 5" will run default command with no arguments if setDefaultCommand() is called with 'alwaysRuns' => true
     */
    public function testDefaultCommandRunsIfAlwaysRunsOptionEnabled()
    {
        extract($this->generateArgvArgc("ar 1 2 3 4 5"));

        $ar = new CLIArgRepeater;

        // ensure run() is called
        $def = $this->getMock('CLIArgRepeater', array('run'));
        $def->expects($this->once())
                        ->method('run')
                        ->will($this->returnValue(0));
        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                               ->setDefaultCommand($def, array('alwaysRuns' => true))
                               ->addCommand($ar, 'ar')
                               ->run($argv, $argc);
        $this->assertEquals(0, $o);

        // ensure proper arguments to run()
        $def = $this->getMock('CLIArgRepeater', array('testArguments'));
        $def->expects($this->once())
                        ->method('testArguments')
                        ->with($this->equalTo(array()));
        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                               ->setDefaultCommand($def, array('alwaysRuns' => true))
                               ->addCommand($ar, 'ar')
                               ->run($argv, $argc);
        $this->assertEquals(0, $o);
    }

    /**
     * @testdox "cliapp 1 2 cmd 1 2 3 4 5" will run default command with arguments array(1,2) if setDefaultCommand() is called with 'alwaysRuns' => true
     */
    public function testDefaultCommandGetsFirstSetOfArgumentsIfAlwaysRunOptionEnabledAndArgumentsSpecifiedBeforeDefaultCommand()
    {
        extract($this->generateArgvArgc("1 2 ar 1 2 3 4 5"));

        $ar = new CLIArgRepeater;

        // ensure proper arguments to run()
        $def = $this->getMock('CLIArgRepeater', array('testArguments'));
        $def->expects($this->once())
                        ->method('testArguments')
                        ->with($this->equalTo(array(1,2)));
        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                               ->setDefaultCommand($def, array('alwaysRuns' => true))
                               ->addCommand($ar, 'ar')
                               ->run($argv, $argc);
        $this->assertEquals(0, $o);
    }

    public function testReturnsUsageErrorIfUsageDisplayedDueToLackOfCommands()
    {
        extract($this->generateArgvArgc(""));

        // ensure result code
        ob_start();
        $result = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                                    ->run($argv, $argc);
        $this->assertEquals(CLImaxController::ERR_USAGE, $result);
        ob_end_clean();
    }

    public function testAddEnvironmentFlagWithExactlyOneArgument()
    {
        extract($this->generateArgvArgc("\\\\-\\\\-flag 1"));

        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                               ->addEnvironmentFlagWithExactlyOneArgument('foo', '--flag');
        $res = $o->run($argv, $argc);
        $this->assertEquals(0, $res);
        $this->assertEquals(1, $o->getEnvironment('foo'));
    }

    public function testAddEnvironmentFlagSetsValue()
    {
        extract($this->generateArgvArgc("\\\\-\\\\-flag"));

        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                               ->addEnvironmentFlagSetsValue('foo', 1, '--flag');
        $res = $o->run($argv, $argc);
        $this->assertEquals(0, $res);
        $this->assertEquals(1, $o->getEnvironment('foo'));
    }

    public function testAddEnvironmentFlagAcceptsValidValue()
    {
        extract($this->generateArgvArgc("flag validVal"));

        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true))
                               ->addEnvironmentFlagSetsValue('foo', 1, 'flag', array('allowedValues' => array('validVal')));
        $res = $o->run($argv, $argc);
        $this->assertEquals(0, $res);
        $this->assertEquals('validVal', $o->getEnvironment('foo'));
    }

    public function testAddEnvironmentFlagRestrictInvalidValues()
    {
        extract($this->generateArgvArgc("flag illegalVal"));

        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true, CLImaxController::OPT_SLIENT => true))
                               ->addEnvironmentFlagSetsValue('foo', 1, 'flag', array('allowedValues' => array('validVal')));
        $res = $o->run($argv, $argc);
        $this->assertEquals(-2, $res);
    }

    public function testTokenWithValueOf0DoesNotStopProcessing()
    {
        extract($this->generateArgvArgc("0 flag 25"));

        $o = CLImaxController::create(array(CLImaxController::OPT_RETURN_INSTEAD_OF_EXIT => true, CLImaxController::OPT_SLIENT => true))
                               ->addEnvironmentFlagWithExactlyOneArgument('foo', 'flag')
                               ;
        $res = $o->run($argv, $argc);
        $this->assertEquals(0, $res);
        $this->assertEquals(25, $o->getEnvironment('foo'));
    }
}
