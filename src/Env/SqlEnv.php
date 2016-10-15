<?php

namespace Runnable\Env;

use Runnable\BaseEnvironment;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Illuminate\Database\QueryException;
use PDOException;
use DB;
use stdClass;

class SqlEnv extends BaseEnvironment {

    protected $name = 'sql';
    protected $description = 'Run raw sql';
    protected $lineText = '';
    protected $connection = null;
    protected $model;
    protected $table;
    protected $listTable = true;
    protected $time = 0;

    protected $casts = [
        'x' => 'boolean',
        'c' => 'string'
    ];

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

            if($this->connection) {
                $result = DB::connection($this->connection)->select(DB::raw("{$command}"));
            }else{
                $result = DB::select(DB::raw("{$command}"));
            }

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
        }catch(PDOException $e) {
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

    protected function mode($command)
    {
        preg_match_all('/\s+(?:([a-z]+))/', $command, $params);

        $params = array_map('trim', $params[0]);

        preg_match('/^\\\\([a-zA-Z]+)/', $command, $command);

        if(count($command) > 1) $command = $command[1];

        $store = collect([
            'x' => function() {
                $this->listTable = !$this->listTable;
                $this->io->newLine(1);

                if($this->listTable) {
                    $this->green('> Table view mode was activated');
                }else{
                    $this->green('> Object view mode was activated');
                }
            },
            'c' => function() {
                $args = func_get_args();

                $connection = count($args) < 1 ? null : current(func_get_args());

                if(in_array($connection, DB::availableDrivers())) {

                    $this->connection = $connection;
                    $this->lineText = $connection;

                    $this->io->newLine(1);
                    $this->io->text('<white>> Database connection driver has been changed to</white> <green>'.$connection.'</green>');
                }else{
                    if(empty($connection)) {
                        $this->connection = null;
                        $this->lineText = '';
                    }else{
                        $this->io->newLine(1);
                        $this->io->error('Connection not found');
                    }
                }
            }
        ]);

        if(! $store->has($command)) {
            $this->io->error('Command not found');
            return;
        }

        call_user_func_array($store->get($command), $params);
    }

    protected function inputOption()
    {
        return [
            'set' => [

            ]
        ];
    }

}
