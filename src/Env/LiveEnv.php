<?php

namespace Runnable\Env;

use Runnable\BaseEnvironment;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Debug\Exception\FatalErrorException;

use DB;
use Artisan;
use stdClass;

class LiveEnv extends BaseEnvironment {

    protected $name = 'live';
    protected $description = 'Run raw sql';
    protected $lineText = 'dxx';
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

            eval("\$result = {$command};");
            $this->io->newLine(2);
            $this->io->text('<yellow>></yellow> <green>' . (is_bool($result) ? ($result ? 'true' : 'false') : $result).'</green>');
            return;

        }catch(\ErrorException $e) {
            //dump($command);
            $this->io->error($e->getMessage());
        }

    }

    public function __get($e)
    {
        dd($e);
    }
}
