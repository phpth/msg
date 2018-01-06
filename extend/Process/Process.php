<?php
/**
 * maybe it's wrong way
 */
namespace Process;
use Exception;
use think\exception\ErrorException;
pcntl_async_signals(true);
class Process
{
    /**
     * 进程数
     * @var int
     */
    protected $process_num;

    /**
     * 运行时文件目录
     * @var string
     */
    protected $run_tmp_file_path ;

    /**
     * 进程标题头
     * @var string
     */
    protected $process_title ;

    /**
     * 停止信号
     * @var
     */
    protected $stop_signal ;
    /**
     * work执行参数
     * @var
     */
    protected $run_param ;

    /**
     * 被实例化次数
     * @var int
     */
    protected static $new_count = 0;

    /**
     * 执行模式
     * @var
     */
    protected $model ;

    /**
     * 当前进程类型 （父进程是1，子进程是0）
     * @var
     */
    public $process_type = 1;

    /**
     * 子进程工作内存限制
     * @var int
     */
    public $memory_limit ;

    /**
     * work进程执行体
     * @var array
     */
    public $callbacks  ;

    /**
     * 遇到的错误
     * @var
     */
    public $last_error ;

    /**
     * work 进程列表
     * @var
     */
    public $process_list;

    /**
     * 程序运行日志文件
     * @var
     */
    public $log_file ;

    /**
     * 当前进程号
     * @var
     */
    public $pid ;

    /**
     * 工作进程编号
     * @var
     */
    protected $work_id ;

    protected $master_pid;

    protected $ready_num  = 0;

    /**
     * Process constructor.
     * @param $process_num
     * @param $process_title
     * @param $callbacks
     * @param int $model 1代表一直执行 ， 0 代表只执行一次
     * @param int $memory_limit
     * @param bool $run_tmp_file_path
     * @throws Exception
     */
    public function __construct( $process_num , $process_title , $callbacks ,$model = 1 , $memory_limit =200 ,$run_tmp_file_path = false)
    {
        if (stripos(PHP_SAPI,'cli' ) === false)
        {
            throw new Exception('多进程模式请在cli模式下运行!');
        }
        $this->model = $model ;
        self::$new_count +=1;
        $this->memory_limit = $memory_limit ;
        $this->pid = $this->master_pid = getmypid() ;
        $this->process_title = "{$process_title}".self::$new_count;
        is_numeric( $process_num ) ?$this->process_num = $process_num  : $this->process_num = 1;
        $this->process_num > 0? : $this->process_num = 1 ;
        if(is_array($callbacks))
        {
            if(is_array(current($callbacks)))//类的回调方法
            {
                if(count($callbacks) < $this->process_num )
                {
                    throw new Exception('为每一个work 进程指定单独运行程序时，必须指定跟进程数相符数量的回调');
                }
                $this->callbacks = $callbacks ;
            }
            else // 普通的回调方法
            {
                for($i = 1 ; $i<=$this->process_num ; $i++ )
                {
                    $this->callbacks[] = $callbacks ;
                }
            }
        }
        else
        {
            if(!is_callable($callbacks))
            {
                throw new Exception('必须传入可调用类型');
            }
            for($i =1 ; $i<=$this->process_num ; $i++ )
            {
                $this->callbacks[] = $callbacks ;
            }
        }
        $this->run_tmp_file_path   =( $run_tmp_file_path? : __DIR__.'/run/');
        if(!is_dir($this->run_tmp_file_path))
        {
            try{
                mkdir($this->run_tmp_file_path,0777,true);
            }
            catch(Exception $e)
            {
                throw new Exception( 'pid 文件目录创建失败!' );
            }
        }
        $this->run_tmp_file_path = realpath($this->run_tmp_file_path);
        $this->log_file = $this->run_tmp_file_path.'/'.self::$new_count."_{$process_title}.log" ;
    }

    /**
     * 开启进程和设置进程执行体
     * @param bool $repeat
     * @param float $sleep
     * @return bool
     */
    public function run($repeat=true,$sleep = 0.1 )
    {
        # 父进程名称设置
        $this->processTitle('master');
        # 父进程信号处理
        $this->registerSignal();
        pcntl_signal(SIGUSR2, array($this, 'signalHandle'));
        $this->run_param = array($repeat,$sleep);
        for ($i=0;$i<$this->process_num;$i++)
        {
            $this->process_list[$i] = pcntl_fork() ;
            if($this->process_list[$i]==0)
            {
                # 注册信号处理
                $this->registerSignal();
                $this->pid = getmypid();
                # 设置进程名称
                $this->processTitle("work{$i}");
                # 设置子进程执行体
                $this->run_param[3] = $this->callbacks[$i];
                $this->work_id      = $i;
                goto work ;
            }
            else if($this->process_list[$i] == -1)
            {
                goto error;
            }
        }
        master:
        $this->waitChildReady();
        return true ;
        work:
        $this->work(...$this->run_param );
        exit;
        error:
        $this->stop();
        return false ;
    }

    /**
     * 等待子进程就绪
     */
    protected function waitChildReady()
    {
        while ($this->ready_num < $this->process_num)
        {
            $this->sleep(0.01);
        }
    }

    /**
     * 子进程执行空间变量清理
     */
    protected function cleanWork()
    {
        $this->process_type = 0;
        $this->last_error = 0 ;
        unset($this->process_list);
        unset($this->process_num);
        unset($this->callbacks);
        unset($this->run_param);
    }

    /**
     * 子进程执行逻辑
     * @param $repeat
     * @param $sleep
     * @param $callback
     */
    protected function work($repeat, $sleep, $callback )
    {
        try{
            # 工作进程清理
            $this->cleanWork();
            posix_kill($this->master_pid, SIGUSR2 );
            pcntl_sigwaitinfo(array(SIGHUP),$sig_info);
            do{
                call_user_func($callback);
                if($this->stop_signal)# 信号停止
                {
                    break;
                }
                if((memory_get_usage() / 1024 / 1024) >= $this->memory_limit) # 内存过大停止
                {
                    break;
                }
                $this->sleep($sleep);
            }while($repeat && $this->model );
            $this->last_error = "no error ! child process exited by normal!";
        }
        catch(Exception|\RuntimeException $e)
        {
            #记录日志文件
            $this->last_error = " {$e->getMessage()} on file {$e->getFile()} in line {$e->getLine()} {$e->getTraceAsString()} {$e->getCode()}" ;
        }
    }

    /**
     * 增强sleep 可以小数
     * @param $sleep
     */
    public function sleep($sleep)
    {
        usleep($sleep*1000000);
    }

    /**
     * 设置进程名称
     * @param $process_type
     */
    protected function processTitle( $process_type )
    {
        cli_set_process_title("{$this->process_title}:{$process_type}");
    }

    /**
     * 错误抛出和设置
     * @param $error_str
     * @throws Exception
     */
    protected function errorSet($error_str)
    {
        $this->last_error = $error_str ;
        throw new Exception($error_str) ;
    }

    /**
     * 信号注册
     */
    protected function registerSignal()
    {
        #pcntl_signal(SIGCHLD, array($this, 'signalHandle'));
        pcntl_signal(SIGUSR1, array($this, 'signalHandle'));
        pcntl_signal(SIGINT, array($this, 'signalHandle'));
        pcntl_signal(SIGTERM, array($this, 'signalHandle'));
    }

    /**
     * 信号处理
     * @param $signal
     */
    public function signalHandle($signal)
    {
        switch ($signal)
        {
            case SIGTERM :
                $this->stop_signal = 1 ;
                break;
            case SIGUSR1:
                if($this->process_type != 1 )
                {
                    $this->stop_signal = 1 ;
                }
                break;
            case SIGINT:
                $this->stop_signal = 1 ;
                if($this->process_type == 1)
                {
                    $this->turnStop();
                }
                break;
            case SIGCHLD:
                break ;
            case SIGUSR2:
                if($this->process_type)
                {
                    $this->ready_num += 1;
                }
            default:
        }
    }

    /**
     * 解阻塞
     * @throws Exception
     */
    protected function  unBlock()
    {
        if($this->process_list)
        {
            foreach($this->process_list as $v)
            {
                posix_kill($v, SIGHUP);
            }
        }
    }

    /**
     * 开始所有子进程执行
     * @return bool
     */
    public function start()
    {
        try{
            $this->unBlock();
            $this->last_error = 0 ;
            $this->wait();
            $this->last_error = "no error mast process exited by normal !";
            return true;
        }
        catch(Exception $e)
        {
            $this->last_error = $e->getMessage();
            return false ;
        }
    }

    /**
     * 检测子进程状态
     */
    protected function wait()
    {
        repeat :
        foreach ( $this->process_list as $k=>$v)
        {
            $pid = pcntl_waitpid($v, $status,WNOHANG);
            if($pid > 0 || $pid == -1)
            {
                /*if(pcntl_wifexited($status))
                {
                    echo pcntl_wexitstatus($status),'正常退出',PHP_EOL;
                }
                if(pcntl_wifstopped($status)) # 检查进程是否退出
                {
                    if(pcntl_wifexited($status))
                    {
                        echo pcntl_wexitstatus($status),'正常退出',PHP_EOL;
                    }
                }
                else
                {
                    if(pcntl_wifexited($status))
                    {
                        echo pcntl_wexitstatus($status),'正常退出',PHP_EOL;
                    }
                    if(pcntl_wifsignaled ($status))
                    {
                        echo pcntl_wstopsig ( $status ) ,'信号停止！',PHP_EOL;
                    }
                    echo '异常退出',PHP_EOL;
                }*/
                unset($this->process_list[$k]) ;
            }
        }

        sleep(1);
        if($this->stop_signal)
        {
            if(count($this->process_list)>0)
            {
                goto repeat;
            }
            $this->last_error = "work_id:master,pid:{$this->pid},error:stoped by signal !";
            echo "stoped!",PHP_EOL;
            return true ;
        }
        # 检测进程是否足够数 ，不足则从新fork
        $this->model && $this->reCreate();
        if(count($this->process_list)> 0)
        {
            goto repeat;
        }
    }

    /**
     * @param $message
     * @throws Exception
     */
    protected function logFile($message)
    {
        $handle = fopen($this->log_file,'ab+');
        if($handle)
        {
            flock($handle,LOCK_EX);
            fwrite($handle, $message);
        }
        else
        {
            throw new Exception('无法记录错误信息');
        }
    }

    /**
     * 异常停止的进程进行重启
     * @return bool
     */
    protected function reCreate()
    {
        # 查找丢失的进程列表编号
        $exists_id = array_keys($this->process_list);
        $full_list_id = range(0,$this->process_num-1);
        $create_list_id = array_diff($full_list_id, $exists_id);
        if(is_array($create_list_id))
        {
            foreach($create_list_id as $v)
            {
                $this->process_list[$v] = pcntl_fork();
                if($this->process_list[$v] == 0)
                {
                    # 注册信号处理
                    $this->registerSignal();
                    # 设置进程名称
                    $this->processTitle("work{$v}");
                    # 配对回调
                    $this->run_param[3] = $this->callbacks[$v];
                    goto work;
                }
                else if($this->process_list[$v] > 0)
                {
                    $error_string = "child process [work{$v}] has stoped , master is reCreated , new [work{$v}] pid is {$this->process_list[$v]}" .date('Y-m-d H:i:s').PHP_EOL;
                    file_put_contents($this->log_file, $error_string ,FILE_APPEND | LOCK_EX) ;
                }
            }
        }
        master:
        return true ;
        work:
        $this->work(...$this->run_param);
        exit;
    }

    /**
     * 强行停止程序
     * @return bool
     */
    public function stop()
    {
        if($this->process_list)
        {
            foreach($this->process_list as $k=>$v)
            {
                if($v>0)
                {
                    if(posix_kill($v ,SIGKILL))
                    {
                        unset($this->process_list[$k]);
                    }else
                    {
                        return false ;
                    }
                }
            }
        }
        return true;
    }

    /**
     * 平滑停止
     */
    public function turnStop()
    {
        if(!empty($this->process_list))
        {
            foreach($this->process_list as $k=>$v)
            {
                posix_kill($v,SIGHUP);
                posix_kill($v,SIGUSR1);
            }
        }
    }

    public function __destruct()
    {
        $error_string = '';
        try{
            if($this->process_type == 1)
            {
                // master process finished do this
                $error_string = "work_id:master,pid:{$this->pid},error:[{$this->last_error}],".date('Y-m-d H:i:s').PHP_EOL;
                $this->turnStop();
                $this->unBlock();
            }
            else
            {
                // child process finished do this
                if($this->last_error)
                {
                    $error_string = "work_id:{$this->work_id},pid:{$this->pid},error:[{$this->last_error}],".date('Y-m-d H:i:s').PHP_EOL;
                }
            }
            if($error_string)
            {
                file_put_contents($this->log_file, $error_string,FILE_APPEND | LOCK_EX);
            }
        }
        catch(Exception $e) {}
    }
}