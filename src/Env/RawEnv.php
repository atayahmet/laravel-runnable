<?php

namespace Runnable\Env;

use Runnable\BaseEnvironment;

class RawEnv extends BaseEnvironment {

    protected $name = 'raw';

    protected $description = 'Run raw sql';

    protected $lineText = '';

    protected $model;

    public function __construct(\App\User $model)
    {

    }

    public function handle($command)
    {
        dump($command);
    }

    protected function inputOption()
    {
        return [
            'set' => [

            ]
        ];
    }

}
