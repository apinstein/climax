CLImax is a php cli framework.

CLImax makes it dead-easy for you to build CLI programs in php:
- Custom apps for managing your project
- General-purpose CLI apps writen in PHP

See the "climax" executable for an example.

Philosophy and Opinions

CLImax is a framwork for progressive development of CLI apps. Although it's pretty easy to build a CLI app in PHP, it is much harder to make one scale as the number of arguments, options, and general scope of the application grows.

CLImax is trivially easy to use to get started, yet provides a set of conventions and interfaces for making your CLI app self-documenting and modular. 

Anatomy of a CLI app:

The simplest CLI app is just a program with no inputs:

$ myApp
> Hello, World!

Really you hardly need a framework for such a thing. But maybe now you want to pass in some arguments:

$ myApp USA
> Hello, USA!

Argument parsing is pretty easy, but still it's nice to not have to learn about argv/argc parsing:

fuction run($arguments, $controller) {
    print "Hello, {$arguments[0]}!";
}

myApp                               \
    # global flags                  \
    --verbose                       \
    --debug                         \
    # specify subcommand to run     \
    subcommand                      \
        --setting1 foo              \
        --setting2 bar              \
        argument1                   \
        argument2                   \


# Distinguishes between global and subcommand options
myApp --foo=bar command --foo=baz
=> gloal env foo=bar
=> command env foo=baz

# Once a "subcommand" has run there's no more 

Architecture:
Everything is implemented as a "command", even arguments. Each token that is parsed is looked up to see if it's a command, and if so, it has first right-of-refusal for eating succeeding tokens (for variable argument amounts). For convenience a few base commands have been created for making basic flags such as a single flag to set a variable (ie --debug sets env.debug=true) or flags that accepts arguments ie (--for-id 1 which would set env.for_id=1). If you have more complex argument processing needs (ie where 2+ arguments *may* be processed based on various other settings or parsing of tokens). For these advanced cases the command implementer must take care to do the right thing as additional processing of the command arguments is dependent on the proper functioning of this advanced feature.

The argument eating should look something like:
function selectArguments($remainingArguments, $globalEnv, $commandEnv)
{
    return 3; // number of arguments used
}

# +> can selectArguments even get commandEnv? Do commands need to know if they "belong" to another command?

$cli->addCommand('globalOpt', ...)
    ->addCommand('globalOpt', ...)
    ->addCommand('subCommand1', ...)
        ->addSubcommand('subCommandOpt', ...)
        ->subcommandDone()
    ->addCommand('subCommand2', ...)
        ->addSubcommand('subCommandOpt', ...)
        ->subcommandDone()
    ->run(...)
    ;


I think we also need some kind of "final command" flag for a command that would just eat the rest of the arguments and pass them along. Maybe this could just be a "subcommand" subclass that returns all remaining
