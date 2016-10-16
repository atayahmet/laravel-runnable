<?php

namespace Runnable\Env;

use Runnable\BaseEnvironment;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Debug\Exception\FatalErrorException;
use Illuminate\Database\QueryException;
use PDOException;

use DB;
use Artisan;
use stdClass;

class LiveEnv extends BaseEnvironment {

    protected $name = 'live';
    protected $description = 'Interactive shell for Laravel Application';
    protected $modes = [
        'trace' => false
    ];

    protected $vars = [];
    protected $lineText = 0;
    protected $time = 0;
    protected $io;

    public function __construct()
    {
        $output = new ConsoleOutput();
        $input  = new ArrayInput([]);

        parent::__construct($output, $input);

        $this->lineText = 0;
        $this->modes = collect($this->modes);
        $this->io = $this->getSymfonyStyle();

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
            ++$this->lineText;

            $result = null;

            extract($this->vars, EXTR_OVERWRITE);

            preg_match('/^(\$[a-zA-Z0-9\-\>]+)\s*?\=\s*?(.*)\;*$/', $command, $matches);

            if(sizeof($matches) > 0) {
                eval("{$matches[1]} = {$matches[2]};\$result = {$matches[1]};");
                preg_match('/\$([a-zA-Z0-9]+)/', $matches[1], $varName);
                $this->vars[$varName[1]] = current(compact($varName[1]));
            }

            elseif(preg_match('/^(\$[a-zA-Z0-9\-\>\(\)\_]+)\s*?$/', $command)) {
                eval("\$result = {$command};");
            }

            else if(preg_match('/\$([a-zA-Z0-9\-\>\(\)\_]+)\s*?/', $command)) {
                ob_start();
                eval("{$command};");
                $result = ob_get_clean();

                if(empty($result)) {
                    eval("\$result = {$command};");
                }
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

        }catch(\Exception $e) {
            $this->io->newLine(2);

            if($this->modes->get('trace')) {
                $this->io->error($e->getTraceAsString());
            }else{
                $this->io->error($e->getMessage());
            }
        }
    }

    public function tab($input)
    {
        dump($input);
    }

    protected function register()
    {
        $this->addMode('\t', function() {
            $trace = $this->modes->get('trace', false);
            $this->modes->offsetSet('trace', !$trace);
            $this->io->newLine(2);

            if($this->modes->get('trace')) {
                $this->white('> Error trace has been activated');
            }else{
                $this->white('> Error message has been activated');
            }
        });
    }

}
