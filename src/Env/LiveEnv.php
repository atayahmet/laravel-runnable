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
    protected $lineText = 0;
    protected $model;
    protected $table;
    protected $listTable = true;
    protected $time = 0;
    protected $vars = [];
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

        $this->lineText = 0;
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
            ++$this->lineText;

            extract($this->vars, EXTR_OVERWRITE);

            preg_match('/^(\$[a-zA-Z0-9\-\>]+)\s*?\=\s*?(.*)\;*$/', $command, $matches);

            if(sizeof($matches) > 0) {
                eval("{$matches[1]} = {$matches[2]};\$result = {$matches[1]};");
                preg_match('/\$([a-zA-Z0-9]+)/', $matches[1], $varName);
                $this->vars[$varName[1]] = current(compact($varName[1]));
            }

            elseif(preg_match('/^(\$[a-zA-Z0-9\-\>]+)\s*?$/', $command)) {
                eval("\$result = {$command};");
            }

            else if(preg_match('/\$([a-zA-Z0-9]+)\s*?/', $command)) {

                ob_start();
                eval($command);
                $result = ob_get_clean();
            }else{
                eval("\$result = {$command};");
            }

            $this->io->newLine(2);

            if(isset($result)) {

                if(is_bool($result)) {
                    $this->io->text('<yellow>></yellow> <green>' . ($result ? 'true' : 'false') . '</green>');
                }
                else if(is_object($result) || is_array($result)) {
                    dump($result);
                }else{
                    $this->io->text('<yellow>></yellow> <green>' . $result . '</green>');
                }
            }
            return;

        }catch(\ErrorException $e) {
            $this->io->newLine(2);
            $this->io->error($e->getMessage());
        }
    }

    public function tab($input)
    {
        dump($input);
    }
}
