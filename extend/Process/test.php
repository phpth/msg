<?php
    include  './Process.php';
   $process = new \Process\Process(1000, 'test+process', function(){echo 'run'.PHP_EOL;} , 0 ,200 , './test');
    $process ->run(0 , 1);
    $process->start();
/*echo getmypid() ,PHP_EOL ;
#pcntl_async_signals(true);
pcntl_signal(SIGHUP, function(){

    echo 'sdfsfasdfasdf';
});
pcntl_sigprocmask(SIG_BLOCK, array(SIGHUP));


sleep(30);

pcntl_sigprocmask(SIG_UNBLOCK, array(SIGHUP));

sleep(900);*/




