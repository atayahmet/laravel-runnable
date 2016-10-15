<?php

namespace Runnable;

use Illuminate\Console\Command;
use Runnable\BaseEnvironment;
use Runnable\EnvNotFoundException;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\ArrayInput;

use ReflectionClass;

class Runnable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'runnable {env?}';
    private static $stty;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pretty runnable environment';

    protected $environments = [];

    protected $history = [];

    protected $commands = [
        'current' => [

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

            if (! $this->hasSttyAvailable()) {

                $this->_newLine(false);
                $this->error('Console stty command not available');
            }else {
                $command = '';
                $commandLength = 0;
                $i = 0;

                $this->setInputs();
                $this->getOutput()->write("\033[K");

                // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
                shell_exec('stty -icanon -echo');

                $this->_stdOut("\n".$this->cursor());

                $j = 0;
                $leftPos = 0;
                $rightPos = 0;

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
                            $this->parse($command, $c);
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

                    if($commandLength > 0) {
                        $this->getOutput()->write("\033[K");
                    }
                }
            }

            // Reset stty so it behaves normally again
           shell_exec(sprintf('stty %s', $sttyMode));

        }catch(CommandNotFoundException $e) {
            $this->_newLine(false);
            $this->error($e->getMessage());
        }
    }

    protected function bootModes()
    {
        foreach(app()->make('RunnableModes')->runnable as $env) {

            // make the mode class
            $envIns = app($env);

            $envIns->register();

            // Uses the reflection class to  be accessable
            // the protected properties.
            $refEnv = new ReflectionClass($env);

            // only name  properties get from mode class
            // because it's properties will using the showcase info
            foreach(['name'] as $prop) {

                // enabled the free usage mode current class
                $refProp = $refEnv->getProperty($prop);
                $refProp->setAccessible(true);

                if($prop == 'name') {
                    $envName = $refProp->getValue($envIns);

                    $this->saveEnvClass($envName, $envIns);
                    $this->saveEnvProps($envName.'.refClass', $envIns);
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
    protected function saveEnvClass($env = null, BaseEnvironment $envClass = null)
    {
        if(!$env || !$envClass) return;

        $this->saveEnvProps($env.'.class', $envClass);
    }

    /**
     * Save the modes container properties from given child mode classes
     *
     * @param  string $path  modes array nested path
     * @param  mixed $value
     * @return void
     */
    protected function saveEnvProps($path = null, $value = null)
    {
        if($path && $value) {
            array_set($this->environments, $path, $value);
        }
    }

    protected function getEnv($name = null)
    {
        return array_get($this->environments, $name ?: $this->getCurrentEnvName());
    }

    private function hasSttyAvailable()
    {
        if (null !== self::$stty) {
            return self::$stty;
        }
        exec('stty 2>&1', $output, $exitcode);
        return self::$stty = $exitcode === 0;
    }

    private function setInputs()
    {
        // Get the env
        $env = $this->argument('env');
        // Get the first env.
        // if boot mode of init moment is fail pass the default mode
        $firstEnv = current(array_keys($this->environments));

        // Save default mode if mode params exists
        $this->bootEnvIfExists($env, true, $defaultEnv = $firstEnv);
    }

    protected function bootEnvIfExists($env = null, $exception = false, $defaultEnv = null)
    {
        // if $exception parameter is true throw the exception error
        // if mode booting is not success
        $booted = false;

        // Check the mode name and exists
        if($env && array_has($this->environments, $env)) {

            // change the env
            $this->setEnv($env);

            // success boot mode process
            $booted = true;
        }

        // boot mode failure process
        if(! $booted) {

            // if mode parameter is valid go to error steps
            if($env) {

                $message = $env . ' environment was not found';

                if($exception) {
                    throw new EnvNotFoundException($message);
                }

                $this->_error($message);
            }

            // if mode parameter is not valid and
            // passed default mode go to set default mode step
            elseif($defaultEnv) {
                $this->bootEnvIfExists($defaultEnv);
            }
        }
    }

    private function _stdOut($text)
    {
        $text = '<fg=red;options=bold>'.$text;
        $this->getOutput()->write($text);
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

    private function parse($command = null, $tab = false)
    {
        if($command) {
            if(preg_match('/^cm\s+(.*?)$/', $command)) {
                preg_match('/^cm\s+(.*?)$/', $command, $matches);

                if(count($matches) > 1) {
                    list($command, $value) = $matches;

                    $this->bootEnvIfExists($value);
                }
            }else{

                try {
                    $class = $this->getEnv()['class'];
                    // if enter key press run the command
                    if(! $tab) {
                        $isMode = call_user_func_array([$class, 'runMode'], [$command]);
                        if(! $isMode) {
                            call_user_func_array([$class, 'handle'], [$command]);
                        }
                    }

                    // run the tab method in custom env class
                    // if tab method exists
                    elseif(method_exists($class, 'tab')){
                        call_user_func_array([$class, 'tab'], [$command]);
                    }
                }
                catch(RuntimeException $e) {
                    $this->error($e->getMessage());
                }
            }

            return true;
        }

        return false;
    }

    private function cursor()
    {
        $envName = $this->getCurrentEnvName();
        $env = $this->getEnv($envName);
        $line = $env['class']->lineText;

        return "</><fg=white>(".$env['class']->name.") " . ($line ? '<fg=red;options=bold>'.$line.' ' : ''). "<fg=white>➜ </><fg=white>";
    }

    protected function getCurrentEnvName()
    {
        return array_get($this->commands, $this->current('mode'));
    }

    private function setEnv($mode)
    {
        array_set($this->commands, $this->current('mode'), $mode);
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
