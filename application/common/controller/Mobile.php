<?php
namespace app\common\controller;
use think\Db;
use think\Config;
use \Redis ;
use Process\Process;
use Process\Queue;
use Sms\Sms;

class Mobile
{
    public  $run_param;

    public $queue;

    public $sms_api;

    public $api_list;

    public function __construct($run_param )
    {
        $this->run_param = $run_param;
    }

    /**
     * @throws \Exception
     */
    public function start()
    {
        if($this->run_param)
        {
            #设置pid文件
            $pid_path = dirname($this->run_param['pid_path']).'/master.pid';
            if(file_exists($pid_path))
            {
                $pid = file_get_contents($pid_path);
                if(posix_kill($pid, 0))
                {
                    throw new \Exception("已有服务已运行!pid:{$pid}");
                }
            }
            if($this->makeFile($pid_path, getmypid()))
            {
                $process = new Process($this->run_param['process'], 'mobile', array($this,'work'),$this->run_param['memory'],$this->run_param['pid_path']);
                if($process->run($this->run_param['repeat'],$this->run_param['sleep']))
                {
                    $process->start();
                }
            }
            else
            {
                throw new \Exception('pid  文件创建失败!');
            }
        }
        else
        {
            throw new \Exception('启动参数未设置！');
        }
    }

    /**
     * @throws \Exception
     */
    public function reload()
    {
        if($this->run_param)
        {
            $this->stop();
            $this->start();
        }
        else
        {
            throw new \Exception('启动参数未设置！');
        }
    }

    /**
     * @throws \Exception
     */
    public function stop()
    {
        if($this->run_param)
        {
            $pid_path = dirname($this->run_param['pid_path']).'/master.pid';
            if(is_file($pid_path))
            {
                $pid = file_get_contents($pid_path);
                if(posix_kill($pid, SIGINT))
                {
                    do{
                        sleep(1);
                    }
                    while(posix_kill($pid, 0));
                    unlink($pid_path);
                    echo PHP_EOL,'mobile service is stoped!',PHP_EOL;
                }
            }
            else
            {
                throw new \Exception("can not open {$pid_path}");
            }
        }
        else
        {
            throw new \Exception('启动参数未设置！');
        }
    }

    /**
     * 子进程执行代码
     */
    public function work()
    {
        if(!$this->queue)
        {
            $this->queue = new Queue(Config::get('redis'));
        }
        if(!$this->api_list)
        {
            $this->api_list = Config::get('sms_api_list') ;
        }
        api_change_repeat:
        $api = key($this->api_list) ;
        $api_able = current($this->api_list);
        next($this->api_list);
        if(!$this->sms_api)
        {
            $this->sms_api = new Sms($api, $api_able['hangye']);
        }
        $json_data = $this->queue->pop(MOBILE_QUEUE);
        if($json_data)
        {
            $this->sms_api->send($to, $content);
        }
        else
        {
            sleep(1);
        }

        //json格式数据
        # $data = $this->queue->pop(MOBILE_QUEUE);
    }

    /**
     * 给定一文件路径自动创建目录和文件
     * @param $path
     * @param $data
     * @return bool
     */
    protected function makeFile($path,$data)
    {
        if(!is_dir(dirname($path)))
        {
            mkdir(dirname($path),0777,true);
        }
        if(file_put_contents($path, $data)===false)
        {
            return false ;
        }
        else{
            return true ;
        }
    }
}



