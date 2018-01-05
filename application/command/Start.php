<?php
namespace app\command;
use app\common\controller\Mobile;
use think\console\Command;
use think\console\Input;
use think\console\Input\Option;
use think\console\Output;
use Process\Share ;
use think\Exception;
use think\exception\ErrorException;

class Start extends Command
{
    protected $send_obj ;

    protected function configure()
    {
        $this->setName('sms:start')
            ->addOption('process', null, Option::VALUE_OPTIONAL, '开启进程数', 6)
            ->addOption('memory', null, Option::VALUE_OPTIONAL, '最大内存限制', 300)
            ->addOption('repeat', null, Option::VALUE_OPTIONAL, '是否无限执行任务，1 无限执行，0执行一次就从新开启一次进程执行', 1)
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, '执行完成一次任务休眠时间，可以传入小数秒', 1)
            ->setDescription('开始其中短信服务并且设置启动参数');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(Input $input, Output $output)
    {
        # 启动参数保存到内存里面
        $tmp_path   = dirname(dirname(__DIR__)) . '/runtime/mobile';
        $share_path = $tmp_path . '/tmp/';
        $pid_path   = $tmp_path . '/pid/';
        $run_param  = array(
            'repeat'   => $input->getOption('repeat'),
            'sleep'    => $input->getOption('sleep'),
            'memory'   => $input->getOption('memory'),
            'process'  => $input->getOption('process'),
            'pid_path' => $pid_path,
        );
        $share      = new Share($share_path);
        if (!$share->set('run_param', $run_param)) {
            throw new \Exception('启动参数保存失败！');
        }
        $this->send_obj = new Mobile($run_param);
        $this->send_obj->start();
        # sleep(1000);
    }

}