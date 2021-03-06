<?php

namespace Runnable\Env;

use Symfony\Component\Debug\Exception\FatalErrorException;
use Illuminate\Database\QueryException;
use Runnable\BaseEnvironment;
use DB;
use Artisan;
use stdClass;
use PDOException;

class LiveEnv extends BaseEnvironment {

    /**
     * Environment name
     * @var string
     */
    protected $name = 'live';

    /**
     * Show text on the command line
     * @var mixed
     */
    protected $lineText = 0;

    /**
     * Environment description
     * @var string
     */
    protected $description = 'Interactive shell for Laravel Application';

    /**
     * Internal modes of live environment
     * @var array
     */
    protected $envModes = [
        'trace' => false
    ];

    /**
     * Variables stores set by terminal
     * @var array
     */
    protected $vars = [];

    protected $patterns = [
        'new_var'       => '^(\$[a-zA-Z0-9\-\>]+)\s*?\=\s*?(.*)\;*$',
        'condition'     => '^(\$[a-zA-Z0-9\-\>]+)\s*?\={2,}\s*?(.*)\;*$',
        'print_value'   => '^(\$[a-zA-Z0-9\-\>\(\)\_]+)\s*?$'
    ];

    /**
     * Error storage
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Query execution time
     * @var integer
     */
    protected $time = 0;

    /**
     * SymfonyStyle command line
     * @var object
     */
    protected $io;

    public function __construct()
    {
        parent::__construct();

        // count run every command
        $this->lineText = 0;

        // modes variable pass to collection for simple automation
        $this->envModes = collect($this->envModes);

        $this->errors = collect($this->errors);

        // symfony style command line class instance
        $this->io = $this->getSymfonyStyle();

        register_shutdown_function(array($this, 'shutdown'));
    }

    /**
     * Restart console live session
     *
     * @return void
     */
    public function shutdown() {
        if (!is_null($e = error_get_last())) {
            app(\Runnable\Shell::class)->run();
        }
    }
    /**
     * Key enter command handle
     *
     * @param  string $command Current command
     * @return mixed
     */
    public function enter($command)
    {
        try {
            // increase the command counter
            ++$this->lineText;

            // set default value of print variable
            $result = null;

            // extract the all variable sets from command line
            // do it every command
            extract($this->vars, EXTR_OVERWRITE);

            // set new variable detector pattern
            preg_match('/'.$this->patterns['new_var'].'/', $command, $matches);

            // condition detector pattern
            if(preg_match('/'.$this->patterns['condition'].'/', $command)) {
                @eval("\$result = {$command};");
            }
            // set new variable
            elseif(sizeof($matches) > 0) {
                @eval("{$matches[1]} = {$matches[2]};\$result = {$matches[1]};");
                preg_match('/\$([a-zA-Z0-9]+)/', $matches[1], $varName);
                $this->vars[$varName[1]] = current(compact($varName[1]));
            }
            // print variable (like: $var, $this->var)
            elseif(preg_match('/'.$this->patterns['print_value'].'/', $command)) {
                @eval("\$result = {$command};");
            }
            // print variable
            else if(preg_match('/\$([a-zA-Z0-9\-\>\(\)\_]+)\s*?/', $command)) {
                ob_start();
                @eval("{$command};");
                $result = ob_get_clean();

                if(empty($result)) {
                    @eval("\$result = {$command};");
                }
            }else{
                // run the as constant
                if(
                    !is_numeric($command) &&
                    ! preg_match("/^\'.*?\'|\".*?\"$/", $command) &&
                    preg_match('/^[a-zA-Z0-9_]+$/', $command)
                ) {
                    $result = constant($command);
                }else{
                    // generic run php code
                    // $result = $command
                    @eval("\$result = {$command};");
                }
            }

            if($this->checkParseError($result)) {
                return;
            }

            $this->io->newLine(2);

            // print type
            if(isset($result)) {
                // Boolean type
                if(is_bool($result)) {
                    $this->io->text('<yellow>></yellow> <green>' . ($result ? 'true' : 'false') . '</green>');
                }
                // object and array type
                else if(is_object($result) || is_array($result)) {
                    dump($result);
                }else{
                    // string or numeric
                    $this->io->text('<yellow>></yellow> <green>' . (!is_int($result) && !is_float($result) ? '"'.$result.'"' : $result) . '</green>');
                }
            }

            $result = null;

        }catch(\Exception $e) {
            $this->io->warning($e->getMessage());
        }
        catch(\ErrorException $e) {
            $this->io->newLine();
            $this->io->warning($e->getMessage());
        }
    }

    /**
     * Key tab command handle
     *
     * @param  string $comma Current input
     * @return mixed
     */
    public function tab($input)
    {
        dump($input);
    }

    protected function register()
    {
        /**
         * Error trace mode. On/Off
         * Usage: \t
         *
         * @var void
         */
        $this->addMode('\trace', function()
        {
            $trace = $this->envModes->get('trace', false);
            $this->envModes->offsetSet('trace', !$trace);
            $this->io->newLine(2);

            if($this->envModes->get('trace')) {
                $this->white('> Error trace has been activated');
            }else{
                $this->white('> Error message has been activated');
            }
        });
    }

    /**
     * Check any error
     *
     * @param  mixed $result
     * @return bool
     */
    protected function checkParseError($result)
    {
        $lastErr = error_get_last();

        if(! $result && sizeof($lastErr) > 0) {
            $this->io->error($lastErr['message']);
            return true;;
        }
        return false;
    }

}
