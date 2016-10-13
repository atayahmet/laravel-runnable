<?php

namespace Runnable;

use Illuminate\Console\Command;

use Runnable\BaseEnvironment;
use Runnable\ModeNotFoundException;

use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\RuntimeException;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputDefinition;
use ReflectionClass;

class Runnable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'runnable {mode?}';
    private static $stty;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pretty runnable environment';

    protected $allModes = [];
    protected $namespace = null;
    protected $modes = [
        /*'model',
        'eval',
        'raw',
        'artisan'*/
    ];

    protected $baseKernel;
    protected $views = ['table', 'json', 'array'];
    protected $history = [];

    protected $commands = [
        'current' => [
            'mode'      => 'model'
        ]
    ];

    //--fields=id,name,user_id --table=3 --db=user-blog => (?:--([a-zA-Z\=\,0-9\.\_\-]+))
    //with('product') orderBy('rank') groupBy('campaign_id') => (?:([a-zA-Z\(\'\)\_]+))

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->bootModes();
        $this->history = collect([]);
    }

    protected function bootModes()
    {
        foreach(app()->make('RunnableModes')->runnable as $mode) {

            // make the mode class
            $modeIns = app($mode);

            // Uses the reflection class to  be accessable
            // the protected properties.
            $refMode = new ReflectionClass($mode);

            // only name  properties get from mode class
            // because it's properties will using the showcase info
            foreach(['name'] as $prop) {

                // enabled the free usage mode current class
                $refProp = $refMode->getProperty($prop);
                $refProp->setAccessible(true);

                if($prop == 'name') {
                    $modeName = $refProp->getValue($modeIns);

                    $this->saveModeClass($modeName, $modeIns);
                    $this->saveModeProps($modeName.'.refClass', $modeIns);
                }
            }

        }
    }

    /**
     * Only save the mode class to modes container
     *
     * @param  string $mode      mode name
     * @param  object $modeClass Class instance of extended to the BaseEnvironment Class
     * @return void
     */
    protected function saveModeClass($mode = null, BaseEnvironment $modeClass = null)
    {
        if(!$mode || !$modeClass) return;

        $this->saveModeProps($mode.'.class', $modeClass);
    }

    /**
     * Save the modes container properties from given child mode classes
     *
     * @param  string $path  modes array nested path
     * @param  mixed $value
     * @return void
     */
    protected function saveModeProps($path = null, $value = null)
    {
        if($path && $value) {
            array_set($this->modes, $path, $value);
        }
    }

    private function hasSttyAvailable()
    {
        if (null !== self::$stty) {
            return self::$stty;
        }
        exec('stty 2>&1', $output, $exitcode);
        return self::$stty = $exitcode === 0;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Save existing tty configuration

        // Make lots of drastic changes to the tty

        // Reset the tty back to the original configuration
        //system("stty '" . $term . "'");

        $sttyMode = shell_exec('stty -g');

        try {
            $inputStream = STDIN;

            if (!$this->hasSttyAvailable()) {

                $this->line('evet');
            }else {
                $command = '';
                $commandLength = 0;
                $i = 0;

                $this->setInputs();

                $this->getOutput()->write("\033[K");

                // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
                shell_exec('stty -icanon -echo');

               //shell_exec("history | grep 'php artisan'");

               //fputs(STDOUT, "\n".$this->cursor());

               $this->_stdOut("\n".$this->cursor());

               $j = 0;
               $leftPos = 0;
               $rightPos = 0;

               //$this->_newLine(false);
               //$this->_newLine(false);
               //$this->call('route:list');
               //$this->_newLine();
                while (!feof($inputStream)) {

                    $rawInput = fread($inputStream, 1);
                    $c = strtolower($rawInput);

                    if($i < 0) $i = 0;

                    if ("\177" === $c && $command !== '' && $i >= 0) {
                        if($i > 0) {
                            --$i;

                            $commandLength = $i;
                            $leftPos = ($i - $rightPos);

                            // delete string from string area
                            if($rightPos > 0) {
                                // replace the delete string
                                $command = substr_replace($command, '', $leftPos, 1);

                                // replace command at cursor
                                $this->replaceCommand($command);

                                // cursor move to left
                                $this->_moveToLeft($rightPos);
                                $leftPos--;
                                continue;
                            }else{
                                $command = substr($command, 0, $i);
                                $this->_erase();
                            }
                        }

                        if($i == 0) {
                            $this->replaceCommand('');
                        }
                    }
                    // arrows
                    elseif ("\033" === $c && $this->history->count() > 0) {
                        $c .= strtolower(fread($inputStream, 2));

                        // arrow up
                        if($c[2] === 'a') {
                            if($this->history->has($j)) {
                                $command = $this->history->get($j);
                                $i = strlen($command);
                                $leftPos = $i;
                                $this->replaceCommand($command);
                                $j++;
                            }
                        }
                        // arrow down
                        if($c[2] === 'b' && $j >= 0) {
                           $command = '';
                           $key = ($j-2);

                           if($this->history->has($key) && $key >= 0) {
                               $command = $this->history->get($key);
                               $i = strlen($command);
                               $leftPos = strlen($command);
                           }

                           if($j > 0) $j--;

                           $this->replaceCommand($command);
                        }

                        // arrow right
                        if($c[2] == 'c') {
                            $rightPos = $rightPos < 0 ? 0 : $rightPos;

                            if($i > 0 && $rightPos >= 0 && $leftPos < $commandLength && $commandLength > 0) {
                                $leftPos++;
                                $rightPos = ($commandLength - $leftPos);
                                $this->_moveToRight(1);
                            }
                            continue;
                        }

                        // arrow left
                        if($c[2] == 'd') {
                            $leftPos = $leftPos < 0 ? 0 : $leftPos;

                            if($i > 0 && $leftPos >= 0 && $rightPos < $commandLength) {
                                $rightPos++;
                                $leftPos = ($commandLength - $rightPos);
                                $this->_moveToLeft(1);
                            }
                            continue;
                        }
                    }
                    elseif(ord($c) < 32) {
                        if($c === "\n") {
                            $command = ltrim($command);

                            if(! empty($command)) {
                                $this->history->prepend($command);
                                $this->parse($command);
                                $rightPos = 0;
                                $leftPos = 0;
                                $i = 0;
                            }

                            $j = 0;
                            $this->_newLine();
                            $command = '';
                        }
                        elseif($c === "\t") {
                           $this->_newLine();
                        }
                    }else{
                        if((empty($command) && $rawInput !== "\177") || !empty($command)) {
                            if($rightPos > 0) {
                                // one step move to right for insert space
                                $moveOneStep = $rawInput === " " ? 0 : 1;

                                // replace the delete string
                                $command = substr_replace($command, $rawInput, ($leftPos+$moveOneStep), 0);

                                // replace command at cursor
                                $this->replaceCommand($command);

                                ++$leftPos;
                                // cursor move to left
                                $this->_moveToLeft($rightPos);
                            }else{
                                $this->getOutput()->write($rawInput);
                                $command .= $rawInput;
                            }
                            $commandLength = strlen($command);
                            $i++;
                            continue;
                        }else{
                            $i = strlen($command);

                            if($i == 0 && strlen(trim($command)) > 0) {
                                $stop = true;
                                $this->_erase();
                                $i = 0;
                            }
                        }
                   }

                  // $this->getOutput()->write(str_replace('➜', '',$c[0]));
                    if($commandLength > 0) {
                        $this->getOutput()->write("\033[K");
                    }
                }

            }

            // $this->callSilent('runnable');

            //$this->setInputs();

            // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
            //shell_exec('stty -icanon -echo');

            /*while (1) {

                fputs(STDOUT, $this->cursor());

                //$key = fread(STDIN, 1);
                $response = strtolower(trim(fgets(STDIN)));


                if($response) {

                    if($this->parse($response)) {
                        continue;
                    }

                }
            }*/

            // Reset stty so it behaves normally again
           shell_exec(sprintf('stty %s', $sttyMode));
        }catch(CommandNotFoundException $e) {
            $this->_newLine(false);
            //$this->error($e->getMessage());
        }
    }

    private function setInputs()
    {
        // Get the mode
        $mode = $this->argument('mode');
        // Get the first mode.
        // if boot mode of init moment is fail pass the default mode
        $firstMode = current(array_keys($this->modes));

        // Save default mode if mode params exists
        $this->bootModeIfExists($mode, true, $defaultMode = $firstMode);
    }

    protected function bootModeIfExists($mode = null, $exception = false, $defaultMode = null)
    {
        // if $exception parameter is true throw the exception error
        // if mode booting is not success
        $booted = false;

        // Check the mode name and exists
        if($mode && array_has($this->modes, $mode)) {

            // change the new mode
            $this->setMode($mode);

            // success boot mode process
            $booted = true;
        }

        // boot mode failure process
        if(! $booted) {

            // if mode parameter is valid go to error steps
            if($mode) {

                $message = $mode . ' mode was not found';

                if($exception) {
                    throw new ModeNotFoundException($message);
                }

                $this->_error($message);
            }

            // if mode parameter is not valid and
            // passed default mode go to set default mode step
            elseif($defaultMode) {
                $this->bootModeIfExists($defaultMode);
            }
        }

    }

    private function _stdOut($text)
    {
        $text = '<fg=red;options=bold>'.$text;
        $this->getOutput()->write($text);

        // <fg=white>'.$text.'</>'
    }

    private function _error($text = null)
    {
        $this->_newLine(false);
        $this->_newLine(false);
        $this->error($text);
    }

    private function _writeln($text, $type = 'mode' )
    {
        if(starts_with($type, '!')) {
            if(! empty($text)) {
                $text = '<fg=white>> </><fg=green;options=bold>'.$text.'</>';
            }else{
                $text = '<fg=white>> </><fg=red;options=bold>Not defined!</>';
            }
        }
        elseif(ends_with($type, ':model')) {
            $text = '<fg=green;options=bold>> model changed: </><fg=white>'.$text.'</>';
        }
        elseif(ends_with($type, ':mode')) {
            $text = '<fg=green;options=bold>> mode changed: </><fg=white>'.$text.'</>';
        }

        $this->getOutput()->newLine(2);
        $this->getOutput()->writeln($text);
        //$this->getOutput()->newLine(1);
        //$this->getOutput()->write($this->cursor());
    }

    private function _newLine($cursor = true)
    {
        //$this->getOutput()->writeln();
        if($cursor) {
            $this->_stdOut("\n".$this->cursor());
        }else{
            $this->getOutput()->write("\n");
        }
    }

    private function _moveToLeft($column = 0, $i = 0)
    {
        do {
            $i++;
            $this->getOutput()->write("\033[1D");
        }while ($i < $column);
    }

    private function _moveToRight($column = 0, $i = 0)
    {
        do {
            $i++;
            $this->getOutput()->write("\033[1C");
        }while ($i < $column);
    }

    private function _erase()
    {
        $this->getOutput()->write("\033[1D");
    }

    private function parse($command = null)
    {
        if($command) {
            if(preg_match('/^cm\s+(.*?)$/', $command)) {
                preg_match('/^cm\s+(.*?)$/', $command, $matches);

                if(count($matches) > 1) {
                    list($command, $value) = $matches;

                    $this->bootModeIfExists($value);
                }
            }else{

                try {
                  $definition = new InputDefinition(array(
                      new InputArgument('command', InputArgument::REQUIRED, 'test'),
                      new InputArgument('value', InputArgument::REQUIRED),
                      new InputOption('foo', 'f', InputOption::VALUE_REQUIRED),
                  ));

                  $input = new ArrayInput(array('command' => 'set', 'value' => 'app.models.permission.role', '-f' => 'foobar'), $definition);


                  //['test', InputArgument::REQUIRED, 'HelloWorld', null]

                  // dump($input);
                  //dump($this->getMode()['class']);
                  call_user_func_array([$this->getMode()['class'], 'handle'], [$command]);
                }
                catch(RuntimeException $e) {
                    //dump($e->getMessage());
                }

            }

            return true;
        }

        return false;
    }

    private function cursor()
    {
        $model = $this->getModel();
        return ($model?$model." ":"")."</><fg=white>(".$this->getModeName().") ➜ </>";
    }

    private function getModel($exception = false)
    {
        $model = array_get($this->commands, $this->current('model'));

        try {
            if(! $model && $exception) {
                throw new ModelNotFoundException("Model not found", 404);
            }

            return $model;
        }
        catch(ModelNotFoundException $e) {
            $this->error('Model not found! Please set a model.');
        }
    }

    private function setModel($model)
    {
        $model = $this->setNamespace($model);

        if(! class_exists('\\'.$model)) {

            throw new ModelNotFoundException('Error: '.$model. ' model not found!');
        }else{
            $modelInstance = new \ReflectionClass($model);
            $isModelSubClass = is_subclass_of($modelInstance->newInstanceWithoutConstructor(), Model::class);

            if(! $isModelSubClass) {

                throw new ModelNotFoundException('Error: '.$model. ' model not found!');
            }

            array_set($this->commands, $this->current('model'), $model);
        }
    }

    private function changeModelOrMode($value)
    {
        try {
            $this->setModel($value);

        }catch(ModelNotFoundException $e) {

            if(in_array($value, $this->modes)) {
                $this->setMode($value);
            }else{
                $this->_error('Error: model or mode not found');
            }
        }
    }

    protected function getMode()
    {
        return array_get($this->modes, $this->getModeName());
    }

    protected function getModeName()
    {
        return array_get($this->commands, $this->current('mode'));
    }

    private function setMode($mode)
    {
        array_set($this->commands, $this->current('mode'), $mode);
    }

    private function setNamespace($path, $fromCamel = false)
    {
        $path = $this->replacePath($path);

        if(! $fromCamel) {
            return implode(array_map('ucfirst', preg_split('/'.'\\\\'.'/', $path)), '\\');
        }
        return implode(array_map('ucfirst', preg_split('/_/', snake_case($path))), '\\');
    }

    private function existsModel($model)
    {
        return class_exists('\\'.$model);
    }

    private function hasCommand($command)
    {
        return array_has($this->commands, str_replace(':', '.', $command));
    }

    private function setCommand($command, $value = null)
    {
        array_set($this->commands, str_replace(':', '.', $command), $value);
    }

    private function replaceCommand($command)
    {
        // Erase to the end of the line
        $erased = "\033[K\033[K\r";

        // default cursor string
        $cursor = $this->cursor();

        // Return to the beginning of the line
        $this->_stdOut($erased.$cursor.$command);
    }

    private function verifyAction($command, $value, $exit = false)
    {
        if($command == 'set:model') {
            $value = $this->setNamespace($value);

            if(! $this->existsModel($value)) {
                $this->_newLine(false);
                $this->error('Error: '.$value. ' model not found!');
                if($exit)
                    exit;
                else
                    return false;
            }
        }
        elseif($command == 'set:mode' && !in_array($value, $this->modes)) {
            $this->_newLine(false);
            $this->error('Error: '.$value. ' mode not found!');
            if($exit)
                exit;
            else
                return false;
        }

        return $value;
    }

    private function current($key)
    {
        return 'current.'.$key;
    }

    /**
     * Replace class path from shortcut pattern
     *
     * @param  string $path Class path shortcut
     * @return string
     */
    private function replacePath($path)
    {
        return str_replace(['.'], ['\\'], $path);
    }

}
