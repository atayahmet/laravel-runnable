<?php

namespace Runnable;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\Table;

class BaseEnvironment extends Command {

    protected $consoleOutput;
    protected $formetter;
    protected $table;
    protected $output;
    protected $input;

    protected $fg = [
        'red',
        'green',
        'yellow',
        'blue',
        'magenta',
        'cyan',
        'white'
    ];

    public function __construct(ConsoleOutputInterface $output, InputInterface $input)
    {
        $this->output = $output;
        $this->input = $input;
        $this->symfonyStyle = new SymfonyStyle($input, $output);

        $this->generateFormat();
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

    protected function generateFormat()
    {
        foreach($this->fg as $color) {

            $style = new OutputFormatterStyle($color, 'default', array('bold'));
            $this->output->getFormatter()->setStyle($color, $style);
        }
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
