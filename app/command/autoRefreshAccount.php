<?php
declare(strict_types=1);

namespace app\command;

use app\controller\AzureApi;
use app\controller\Notify;
use app\controller\UserAzure;
use app\model\AutoRefresh;
use app\model\Azure;
use app\model\AzureServer;
use app\model\Config;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

class autoRefreshAccount extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('autoRefreshAccount')
            ->setDescription('Automatically refresh account subscription status');
    }

    protected function execute(Input $input, Output $output)
    {
        $hour = date('H'); // 00-23
        $telegram_notify = Config::obtain('telegram_notify');

        $all_tasks = AutoRefresh::where('function_swicth', '1')->select();

        foreach ($all_tasks as $task) {
            if ($hour % $task->rate === 0) {
                $count = 0;
                $text = '以下账户订阅状态发生了变动：';

                $accounts = Azure::where('user_id', $task->user_id)
                    ->where('az_sub_status', '<>', 'Disabled')
                    ->where('az_sub_status', '<>', 'Warned')
                    ->where('az_sub_status', '<>', 'Invalid')
                    ->select();

                foreach ($accounts as $account) {
                    try {
                        $sub_info = AzureApi::getAzureSubscription($account->id); // array
                        $account->az_sub = json_encode($sub_info);
                        $account->az_sub_status = $sub_info['value']['0']['state'];
                        $account->az_sub_type = UserAzure::discern($sub_info['value']['0']['subscriptionPolicies']['quotaId']);
                        $account->az_sub_updated_at = time();
                        $account->updated_at = time();
                        $account->save();

                        if ($sub_info['value']['0']['state'] !== 'Enabled') {
                            $count += 1;
                            $text .= PHP_EOL . $account->az_email . ' (' . $account->az_sub_type . ') ' . ' [' . $account->az_sub_status . ']';
                            $text .= PHP_EOL . '└';

                            $servers = AzureServer::where('account_id', $account->id)->select();
                            foreach ($servers as $server) {
                                $text .= $server->name . ',';
                            }

                            // $text .= '合计' . $servers->count() . '台虚拟机';

                            UserAzure::refreshTheResourceStatusUnderTheAccount($account);
                        }
                    } catch (\Exception $e) {
                        Log::write($e->getMessage(), 'refresh_error');
                    }
                }

                if ($telegram_notify === true && $count !== 0 && $task->push_swicth === 1) {
                    $user = User::where('id', $task->user_id)->find();
                    if (isset($user->notify_tgid)) {
                        try {
                            Notify::telegram($user->notify_tgid, $text);
                        } catch (\Exception $e) {
                            Log::write($e->getMessage(), 'push_error');
                        }
                    }
                }
            }
        }

        $output->writeln("<info>All tasks completed.</info>");
    }
}
