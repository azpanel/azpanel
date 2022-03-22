<?php
declare (strict_types = 1);

namespace app\command;

use think\facade\Log;
use app\model\User;
use app\model\AzureServer;
use app\model\ControlLog;
use app\model\ControlRule;
use app\model\ControlTask;
use app\controller\Notify;
use app\controller\AzureApi;
use app\controller\UserAzureServer;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class trafficControlStop extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('trafficControlStop')
            ->setDescription('Detect server metrics and enforce preset rules');
    }

    protected function execute(Input $input, Output $output)
    {
        $servers = AzureServer::where('rule', '<>', '0')
        ->where('status', 'PowerState/running')
        ->where('skip', '<>', '1')
        ->select();

        foreach ($servers as $server)
        {
            $rule = ControlRule::find($server->rule);

            if ($rule->switch == '1')
            {
                $stop_time  = time() - 28800;
                $start_time = time() - ($rule->interval * 3600) - 28800;
                $stop_time  = date('Y-m-d\T H:i:s\Z', $stop_time);
                $start_time = date('Y-m-d\T H:i:s\Z', $start_time);

                try {
                    $pointer = ($rule->index == 'traffic_in') ? '3' : '4';
                    $statistics = AzureApi::getVirtualMachineStatistics($server, $start_time, $stop_time);
                    $indicator_usage_raw = $statistics['value'][$pointer]['timeseries']['0']['data'];
                    $indicator_usage = UserAzureServer::processNetworkData($indicator_usage_raw, true);

                    if ($indicator_usage > $rule->limit) {
                        AzureApi::manageVirtualMachine('stop', $server->account_id, $server->request_url);
                        // set vm status
                        $server->status = 'PowerState/stopped';
                        $server->save();

                        if ($rule->execute_push == '1') {
                            $user = User::where('id', $rule->user_id)->find();
                            if (!empty($user->notify_tgid)) {
                                try {
                                    $text = "虚拟机 $server->name 触发流量控制规则 $rule->name ，计划 $rule->time 小时后重新启动";
                                    Notify::telegram($user->notify_tgid, $text);
                                } catch (\Exception $e) {
                                    Log::write($e->getMessage(), 'push_error');
                                }
                            }
                        }

                        $log = new ControlLog;
                        $log->user_id    = $server->user_id;
                        $log->rule_id    = $server->rule;
                        $log->rule_name  = $rule->name;
                        $log->vm_id      = $server->vm_id;
                        $log->vm_name    = $server->name;
                        $log->action     = 'stop';
                        $log->created_at = time();
                        $log->save();

                        $task = new ControlTask;
                        $task->user_id      = $server->user_id;
                        $task->rule_id      = $server->rule;
                        $task->vm_id        = $server->vm_id;
                        $task->status       = 'wait';
                        $task->recover_push = $rule->recover_push;
                        $task->created_at   = time();
                        $task->execute_at   = time() + ($rule->time * 3600);
                        $task->save();
                    }
                } catch (\Exception $e) {
                    Log::write($e->getMessage(), 'traffic_control_error');
                }
            }
        }

        $output->writeln("<info>All tasks have been completed.");
    }
}
