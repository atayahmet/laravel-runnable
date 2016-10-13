<?php

namespace Runnable;

use Illuminate\Support\ServiceProvider;

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

      $this->app->bind('RunnableModes', 'Illuminate\Contracts\Console\Kernel');
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
            \App\Parsers\ArtisanEnv::class,
            \App\Parsers\LiveEnv::class,
            \App\Parsers\ModelEnv::class,
            \App\Parsers\SqlEnv::class,
            \App\Parsers\RawEnv::class
        ];
    }
}
