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

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pretty runnable environment';

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
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $argument = $this->argument('env');
        dd(app(\Runnable\Shell::class)->run($argument));
        // Save existing tty configuration

        // Make lots of drastic changes to the tty

        // Reset the tty back to the original configuration
        //system("stty '" . $term . "'");


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
