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
     * pid 文件目录
     * @var string
     */
    protected $process_pid_tmp_path ;

    /**
     * 父进程是各个子进程的pid 数组， 子进程里面是此进程的pid文件字串
     * @var
     */
    protected $work_lock_file ;

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
     * 子进程运行时创建的文件，结束时会自动删除
     * @var
     */
    public $child_runing_pid_file ;

    /**
     * Process constructor.
     * @param $process_num
     * @param $process_title
     * @param $callbacks
     * @param int $memory_limit
     * @param bool|string $process_pid_tmp_path
     * @throws Exception
     */
    public function __construct( $process_num , $process_title , $callbacks , $memory_limit =200 ,$process_pid_tmp_path = false)
    {
        if (stripos(PHP_SAPI,'cli' ) === false)
        {
            throw new Exception('多进程模式请在cli模式下运行!');
        }
        self::$new_count +=1;
        $this->memory_limit = $memory_limit ;
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

        $this->process_pid_tmp_path   =( $process_pid_tmp_path? : __DIR__.'/run/');
        if(!is_dir($this->process_pid_tmp_path))
        {
            try{
                mkdir($this->process_pid_tmp_path,0777,true);
            }
            catch(Exception $e)
            {
                throw new Exception( 'pid 文件目录创建失败!' );
            }
        }
        $this->createChildFile();
    }

    /**
     * 开启进程和设置进程执行体
     * @param bool $repeat
     * @param float $sleep
     * @return bool
     */
    public function run($repeat=true,$sleep = 0.1)
    {
        #创建子进程的阻塞文件
        try{
            $this->block();
        }
        catch(Exception $e){
            $this->last_error = $e->getMessage() ;
            return false ;
        }
        # 父进程名称设置
        $this->processTitle('master');
        # 父进程信号处理
        $this->registerSignal();
        $this->run_param = array($repeat,$sleep);
        $pid_file_arr = array_keys($this->work_lock_file);
        for ($i=0;$i<$this->process_num;$i++)
        {
            $this->process_list[$i] = pcntl_fork() ;
            if($this->process_list[$i]==0)
            {
                # 注册信号处理
                $this->registerSignal();
                # 设置进程名称
                $this->processTitle("work{$i}");
                # 设置子进程pid文件
                $this->work_lock_file = current($pid_file_arr) ;
                # 设置子进程执行体
                $this->run_param[3] = $this->callbacks[$i];
                goto work ;
            }
            else if($this->process_list[$i] == -1)
            {
                goto error;
            }
            next($pid_file_arr);
        }
        master:
        return true ;
        work:
        $this->work(...$this->run_param );
        return true;
        error:
        $this->stop();
        return false ;
    }

    /**
     * 子进程执行空间变量清理
     */
    protected function cleanWork()
    {
        $this->process_type = 0;
        unset($this->process_list);
        unset($this->process_num);
        unset($this->last_error);
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
            $this->getLock($this->work_lock_file);
            $pid = getmypid();
            $this->child_runing_pid_file = $this->process_pid_tmp_path."child_run_pid_{$pid}.pid";
            file_put_contents($this->child_runing_pid_file,microtime(true));

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
                usleep($sleep*1000000);
            }while($repeat);
        }
        catch(ErrorException|Exception|\RuntimeException $e)
        {
            echo $e->getMessage(),PHP_EOL;
        }
        exit;
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
        pcntl_signal(SIGHUP, array($this, 'signalHandle'));
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
            case SIGHUP:
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
            default:
        }
    }

    /**
     * 锁定进程
     * @throws Exception
     */
    protected function block()
    {
        foreach($this->work_lock_file  as $k=>$v)
        {
            if(!flock($this->work_lock_file[$k], LOCK_EX))
            {
                foreach($this->work_lock_file as $v)
                {
                    flock($v, LOCK_UN);
                }
                throw new Exception('堵塞遇到失败') ;
            }
        }
        //todo：用信号实现阻塞 ；
    }

    /**
     * 创建阻塞文件
     */
    protected function createChildFile()
    {
        for ($i=0;$i<$this->process_num;$i++)
        {
            $path = $this->pidFilePath($i) ;
            $this->work_lock_file[$path]  = fopen($path,'a+') ;
        }
    }

    /**
     * 释放进程
     * @throws Exception
     */
    protected function  unBlock()
    {
        if(is_array($this->work_lock_file)) // 父进程执行，子进程不执行
        {
            foreach( $this->work_lock_file as $k=>$v)
            {
                if(!flock($this->work_lock_file[$k], LOCK_UN))
                {
                    throw new Exception('解堵塞遇到失败') ;
                }
            }
        }
        //todo：用信号实现解阻塞 ；
    }

    /**
     * 子进程
     * @param bool $lock_file
     */
    protected function getLock($lock_file = false )
    {
        if($lock_file)
        {
            $handle = fopen($lock_file,'a+');
            flock($handle,LOCK_EX);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        //todo: 用信号实现阻塞；
    }


    /**
     * 开始所有子进程执行
     * @return bool
     */
    public function start()
    {
        try{
            $this->unBlock();
            $this->wait();
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
            echo "stoped!",PHP_EOL;
            return true ;
        }
        # 检测进程是否足够数 ，不足则从新fork
        $this->reCreate();
        if(count($this->process_list)> 0)
        {
            echo 'hang!' ,PHP_EOL ;
            goto repeat;
        }
    }

    /**
     *  pid文件目录
     */
    protected function pidFilePath($id)
    {
        return $this->process_pid_tmp_path."/{$this->process_title}_work{$id}.lock";
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
                    $this->work_lock_file = $this->pidFilePath($v);
                    goto work;
                }
                else if($this->process_list[$v] > 0)
                {
                    echo "create work{$v}!",PHP_EOL ;
                }
            }
        }
        master:
        return true ;
        work:
        $this->work(...$this->run_param);
        return true ;
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
            }
        }
    }

    /**
     * 平滑重启
     */
    public function reload()
    {

    }

    public function isRun()
    {

    }

    public function __destruct()
    {
        try{
            if($this->process_type == 1)
            {
                $this->turnStop();
                $this->unBlock();
                if(is_array($this->work_lock_file))
                {
                    foreach($this->work_lock_file as $k=>$v)
                    {
                        fclose($this->work_lock_file[$k]);
                    }
                }
            }
            else
            {
                unlink($this->child_runing_pid_file);
            }
        }
        catch(Exception $e) {}
    }
}