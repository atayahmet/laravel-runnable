<?php

namespace Runnable;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunnableServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands(\Runnable\Runnable::class);

        //$this->app->bind(\Runnable\Shell::class);

        $this->app->bind('RunnableModes', 'Illuminate\Contracts\Console\Kernel');

        $this->app->bind('InputInterface', function() {
            return new ArrayInput([]);
        });

        $this->app->bind('OutputInterface', function() {
            return new ConsoleOutput();
        });

        $this->app->tag(['InputInterface', 'OutputInterface'], 'inout');

        $this->app->bind('ConsoleStyle', function ($app) {
            $tagged = $app->tagged('inout');
            $input  = $tagged[0];
            $output = $tagged[1];
            return new SymfonyStyle($input, $output);
        });

        $this->app->singleton(\Runnable\Shell::class, function ($app) {
            return new \Runnable\Shell($app->make(\Runnable\ConsoleStyle::class));
        });

        $this->saveDefaultModes();
    }

    protected function saveDefaultModes()
    {
        $runnableModes = app()->make('RunnableModes');
        if(! property_exists($runnableModes, 'runnable')) {
            $runnableModes->runnable = [];
        }

        foreach ($this->defaultModes() as $mode) {
            array_push($runnableModes->runnable, $mode);
        }
    }

    protected function defaultModes()
    {
        return [
            \Runnable\Env\ArtisanEnv::class,
            \Runnable\Env\LiveEnv::class,
            \Runnable\Env\ModelEnv::class,
            \Runnable\Env\SqlEnv::class,
            \Runnable\Env\RawEnv::class
        ];
    }
}
