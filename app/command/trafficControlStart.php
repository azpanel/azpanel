<?php
declare(strict_types=1);

namespace app\command;

use app\controller\AzureApi;
use app\controller\Notify;
use app\model\AzureServer;
use app\model\ControlLog;
use app\model\ControlRule;
use app\model\ControlTask;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

class trafficControlStart extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('trafficControlStart')
            ->setDescription('Perform virtual machine power-on tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        $tasks = ControlTask::where('status', 'wait')
            ->where('execute_at', '<', time())
            ->select();

        foreach ($tasks as $task) {
            try {
                $server = AzureServer::where('vm_id', $task->vm_id)->find();
                $rule = ControlRule::find($server->rule);
                AzureApi::manageVirtualMachine('start', $server->account_id, $server->request_url);
                // set task status
                $task->status = 'done';
                $task->save();
                // set vm status
                $server->status = 'PowerState/running';
                $server->save();

                $log = new ControlLog();
                $log->user_id = $server->user_id;
                $log->rule_id = $server->rule;
                $log->rule_name = $rule->name;
                $log->vm_id = $server->vm_id;
                $log->vm_name = $server->name;
                $log->action = 'start';
                $log->created_at = time();
                $log->save();

                if ($task->recover_push === 1) {
                    $user = User::where('id', $task->user_id)->find();
                    if (isset($user->notify_tgid)) {
                        try {
                            $text = "虚拟机 {$server->name} 已重新启动";
                            Notify::telegram($user->notify_tgid, $text);
                        } catch (\Exception $e) {
                            Log::write($e->getMessage(), 'push_error');
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::write($e->getLine() . ':' . $e->getMessage(), 'traffic_control_start_error');
            }
        }

        $output->writeln("<info>All tasks have been completed.");
    }
}
