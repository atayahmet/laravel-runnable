<?php

namespace Runnable\Env;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Symfony\Component\Console\Input\InputOption;

use App\Parsers\BaseMode;

class ModelEnv extends BaseEnvironment {

    protected $name = 'model';

    protected $description = 'Go interactive with your project models';

    protected $lineText = '';

    public function __construct()
    {

    }

    public function handle()
    {
        return 'hello';
    }
    //new InputArgument('command', InputArgument::REQUIRED, 'test'),
    //new InputArgument('value', InputArgument::REQUIRED),
    //new InputOption('foo', 'f', InputOption::VALUE_REQUIRED),

    protected function boot()
    {
        return [
            'set' => [
                $this->addArgument('command', self::REQUIRED, 'Change the current model'),
                $this->addArgument('value', self::REQUIRED, 'Model path'),
                $this->addOption('foo', 'f', self::VALUE_REQUIRED)
            ]
        ];
    }
}
