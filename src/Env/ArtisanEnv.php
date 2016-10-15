<?php

namespace Runnable\Env;

use Runnable\BaseEnvironment;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Debug\Exception\FatalErrorException;

use DB;
use Artisan;
use stdClass;

class ArtisanEnv extends BaseEnvironment {

    public $name = 'artisan';
    public $description = 'Run raw sql';
    public $lineText = 'dxx';
    
    protected $model;
    protected $table;
    protected $listTable = true;
    protected $time = 0;
    protected $io;

    public function __construct(\App\User $model)
    {
        $output = new ConsoleOutput();
        $input = new ArrayInput([]);

        parent::__construct($output, $input);

        $this->io = $this->getSymfonyStyle();
        $this->table = $this->table();

        DB::listen(function($sql) {
            $this->time = $sql->time;
        });
    }

    /**
     * Command handle
     *
     * @param  string $command Current command
     * @return mixed
     */
    public function handle($command)
    {
        try {

            if($command == 'exit') {
                $this->io->text('<green>Good Byeee...</green>');
                $this->io->newLine(1);
                exit;
            }

            $this->io->newLine(2);

            if($command == 'tinker') {
                $this->io->warning('tinker not supported in runnnable. Please exit and run `php artisan tinker` command');
                return;
            }

            // run current command on real environment
            $rawResult = shell_exec("php artisan {$command} 2>&1 &");

            // check any error in response
            $exception = $this->hasException($rawResult);

            // if error exists stop the process
            if($exception) return;

            // if command result in contain table
            // structure render it as table
            if($this->isTable($rawResult)) {

                preg_match('/\s*(?:\+-*)+\n(?:\|?\s*[a-zA-Z]+\s*\|)+\n(?:\+-*)+/', $rawResult, $matches);

                if(sizeof($matches) > 0) {
                    $header = preg_replace('/([a-zA-Z]+)/', '<green>$0</green>', $matches[0]);
                    $rawResult = preg_replace('/\s*(?:\+-*)+\n(?:\|?\s*[a-zA-Z]+\s*\|)+\n(?:\+-*)+/', $header, $rawResult);
                }

                $this->io->text('<white>'.$this->colorIf($command, $rawResult).'</white>');
            }else{
                $this->io->text('<green>'.$this->colorIf($command, $rawResult).'</green>');
            }

        }catch(FatalErrorException $e) {
            $this->io->error($e->getMessage());
        }

    }

    protected function isTable($output)
    {
        return preg_match('/^\s*?\+(?:\-+)\+/', $output);
    }

    protected function getHeader($data)
    {
        if(is_array($data)) {
            return array_keys((array)reset($data));
        }
    }

    protected function getBody($data)
    {
        $newData = [];

        if(is_array($data) && reset($data) instanceof stdClass) {

            $lastKey = sizeof($data) - 1;

            foreach($data as $key => $item) {
                $newData[] = get_object_vars($item);

                if($key < $lastKey) {
                    $newData[] = new TableSeparator();
                }
            }
        }
        return $newData;
    }

    protected function colorIf($command, $output)
    {
        if(preg_match('/^.*?(\-h|\-\-help)$/', $command) || $command == 'list' || $command == 'help') {

            $output = preg_replace(
                ['/\-\-?[a-zA-Z|\[\=\]]+,?/'],
                ['<green>$0</green>'],
                $this->titleColor($output)
            );
        }

        return $output;
    }

    protected function titleColor($output)
    {
        $output = preg_replace(
            ['/Usage\:/','/Options\:/', '/Help\:/','/Arguments\:/','/Available\scommands\:/'],
            array_fill(0, 5, '<yellow>$0</yellow>'),
            $output
        );

        $splitted = preg_split('/\n/', $output);
        $color = false;

        foreach($splitted as &$line) {

            if(preg_match('/Available\scommands/', $line)) {
                $color = true;
            }

            if($color) {

                if(!preg_match('/^\s+[a-z\:\-]+\s+/', $line)) {
                    $line = '<yellow>'.$line.'</yellow>';
                }else{
                    $line = preg_replace('/^(\s+[a-z\:\-]+)/', '<green>$0</green>', $line);
                    $line = preg_replace('/([A-Z]+[a-z\s\-]+)/', '<white>$0</white>', $line);
                }
            }
        }
        return implode("\n", $splitted);
    }

    protected function hasException($output)
    {
        $has = preg_match('/(?:[a-zA-Z]+'.preg_quote("\\").')+[a-zA-Z]+(Exception)/', $output);

        if($has) {
            $this->io->error($output);
            return true;
        }
        return false;
    }
}
