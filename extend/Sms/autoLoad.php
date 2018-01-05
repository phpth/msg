<?php
namespace Sms;
class autoLoad
{
    public function __construct()
    {
        spl_autoload_register(__NAMESPACE__.'\autoLoad::load',true,false);
    }

    public function load($class)
    {
        $file_path =  dirname(__DIR__).'/'.$class;
        $class = str_replace('\\', '/', $file_path).'.php';
        if(file_exists($class))
        {
            include_once $class;
        }
    }
}
new autoLoad();