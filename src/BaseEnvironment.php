<?php

namespace Runnable;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\Table;
use Closure;

class BaseEnvironment extends Command {

    protected $consoleOutput;
    protected $formetter;
    protected $table;
    protected $output;
    protected $input;

    private $modes;

    protected $fg = [
        'red',
        'green',
        'yellow',
        'blue',
        'magenta',
        'cyan',
        'white'
    ];

    public function __construct()
    {
        $this->output = new ConsoleOutput();
        $this->input = new ArrayInput([]);

        $this->symfonyStyle = new SymfonyStyle($this->input, $this->output);
        $this->modes = collect([]);

        $this->initConfig();
    }

    public function consoleOutput()
    {
        return $this->consoleOutput;
    }

    public function getFormatter()
    {
        return $this->formatter;
    }

    public function getSymfonyStyle(ConsoleOutputInterface $output = null, InputInterface $input = null)
    {
        if($output && $input) return new SymfonyStyle($input, $output);

        return $this->symfonyStyle;
    }

    public function tableSeparator()
    {
        return new TableSeparator;
    }

    public function table(ConsoleOutputInterface $output = null)
    {
        if($output == null) $output = $this->output;

        return new Table($output);
    }

    public function initConfig()
    {
        $this->generateFormat();
        $this->register();
    }

    protected function generateFormat()
    {
        foreach($this->fg as $color) {

            $style = new OutputFormatterStyle($color, 'default', array('bold'));
            $this->output->getFormatter()->setStyle($color, $style);
        }
    }

    protected function addMode($name = null, Closure $action)
    {
        $this->modes->put($name, $action);
    }

    protected function getMode($mode)
    {
        return $this->modes->get($mode, null);
    }

    public function runMode($command)
    {
        preg_match_all('/\s+(?:([a-z0-9]+))/', $command, $params);
        $params = array_map('trim', $params[0]);

        preg_match('/^(\\\\[a-zA-Z]+)/', $command, $command);
        $command = count($command) > 1 ? $command[1] : '';

        if(! $this->modes->has($command)) {
            return false;
        }

        call_user_func_array($this->getMode($command), $params);
        return true;
    }

    public function __call($method, $args)
    {
        if(in_array($method, $this->fg)){
            $this->output->writeln('<'.$method.'>'.current($args).'</'.$method.'>');
        }
    }

    public function __get($name)
    {
        if(in_array($name, ['name', 'description', 'lineText'])) {
            return $this->{$name};
        }
    }
}
