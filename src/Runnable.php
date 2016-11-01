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

        app(\Runnable\Shell::class)->run($argument);
    }
}
