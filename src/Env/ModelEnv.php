<?php

namespace Runnable\Env;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Symfony\Component\Console\Input\InputOption;

use Runnable\BaseEnvironment;

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
