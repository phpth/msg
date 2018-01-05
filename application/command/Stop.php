<?php
namespace app\command;
use app\common\controller\Mobile;
use Process\Share;
use think\console\Command;
use think\console\Input;
use think\console\Input\Option;
use think\console\Output;
class Stop extends Command
{
    protected function configure()
    {
        $this->setName('sms:stop')
            ->setDescription('停止短信服务 ');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(Input $input, Output $output)
    {
        $tmp_path   = dirname(dirname(__DIR__)) . '/runtime/mobile';
        $share_path = $tmp_path . '/tmp/';
        $pid_path   = $tmp_path . '/pid/';
        $share      = new Share($share_path);
        $run_param  = array(
            'pid_path' => $pid_path,
        );
        $share->remove();
        $send_obj = new Mobile($run_param);
        $send_obj->stop();
    }
}