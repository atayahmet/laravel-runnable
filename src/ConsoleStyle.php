<?php

namespace Runnable;

class ConsoleStyle {

    /**
     * [$consoleStyle description]
     * @var [type]
     */
    protected $consoleStyle;

    public function __construct()
    {
        $this->consoleStyle = app('ConsoleStyle');
    }

    public function erase()
    {
        $this->consoleStyle->write("\033[1D");
    }

    public function moveToLeft($column = 0, $i = 0)
    {
        do {
            $i++;
            $this->consoleStyle->write("\033[1D");
        }while ($i < $column);
    }

    public function moveToRight($column = 0, $i = 0)
    {
        do {
            $i++;
            $this->consoleStyle->write("\033[1C");
        }while ($i < $column);
    }

    public function __call($method, $args)
    {
        if(method_exists($this->consoleStyle, $method)) {
            $this->consoleStyle->{$method}(current($args));
        }
    }

}
