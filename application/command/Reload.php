<?php
namespace app\command;
use app\common\controller\Mobile;
use Process\Share;
use think\console\Command;
use think\console\Input;
use think\console\Input\Option;
use think\console\Output;
class Reload extends Command
{

    protected function configure()
    {
        $this->setName('sms:reload')
            ->setDescription('重新启动服务 ');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(Input $input, Output $output)
    {
        # 获取启动参数
        $share_path = dirname(dirname(__DIR__)).'/runtime/tmp/';
        $shar_obj = new Share($share_path);
        $run_param = $shar_obj->get('run_param');
        if(!$run_param)
        {
            throw new \Exception('无法获取运行参数，请使用sms:stop，和sms:start命令');
        }
        $obj = new Mobile($run_param);
        $obj->reload();
    }
}
