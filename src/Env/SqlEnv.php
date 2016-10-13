<?php

namespace Runnable\Env;

use Runnable\BaseEnvironment;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Illuminate\Database\QueryException;
use DB;
use stdClass;

class SqlEnv extends BaseEnvironment {

    protected $name = 'sql';

    protected $description = 'Run raw sql';

    protected $lineText = '';

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

    public function handle($command)
    {
        try {

            if(preg_match('/^\\\\[a-z]+/', $command)) {
                $this->mode($command);
                return;
            }

            $result = DB::select(DB::raw("{$command}"));

            $this->io->newLine(2);

            if($this->listTable) {
                $rows = $this->getBody($result);
                $total = count($rows);

                $this->table
                    ->setHeaders($this->getHeader($result))
                    ->setRows($rows);

                $this->table->render();

                $this->io->newLine(1);
                $this->io->text('Total rows: '.$total . ' (time: '.$this->time.' ms)');
            }else{
                dump($result);
            }

        }catch(QueryException $e) {
            $this->io->error($e->getMessage());
        }

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
                    $newData[] = $this->tableSeparator();
                }
            }
        }
        return $newData;
    }

    protected function mode()
    {
        $this->listTable = !$this->listTable;

        $result = [
            "x" => [
                false => '> Object view mode was activated',
                true  => '> Table view mode was activated'
            ]
        ];

        $this->io->newLine(1);
        $this->green($result['x'][$this->listTable]);
    }

    protected function inputOption()
    {
        return [
            'set' => [

            ]
        ];
    }

}
