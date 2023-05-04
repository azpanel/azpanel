<?php
declare(strict_types=1);

namespace app\command;

use app\controller\AzureApi;
use app\controller\UserAzureServer;
use app\model\AzureServer;
use app\model\Config;
use app\model\Traffic;
use Carbon\Carbon;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Log;

class Tools extends Command
{
    public static function statisticsTraffic()
    {
        $servers = AzureServer::where('status', '<>', 'PowerState/deallocated')
            ->where('skip', 0)
            ->select();

        $start_time = date('Y-m-d\T 16:00:00\Z', strtotime(Carbon::parse('+2 days ago')->toDateTimeString()));
        $stop_time = date('Y-m-d\T 16:00:00\Z', strtotime(Carbon::parse('+1 days ago')->toDateTimeString()));

        // dump($start_time);
        // dump($stop_time);

        foreach ($servers as $server) {
            try {
                $statistics = AzureApi::getVirtualMachineStatistics($server, $start_time, $stop_time);
                foreach ($statistics['value'] as $key => $value) {
                    if ($value['name']['value'] === 'Network In Total') {
                        $network_in_total = $statistics['value'][$key]['timeseries']['0']['data'];
                    }
                    if ($value['name']['value'] === 'Network Out Total') {
                        $network_out_total = $statistics['value'][$key]['timeseries']['0']['data'];
                    }
                }

                $in_total = UserAzureServer::processNetworkData($network_in_total, true);
                $out_total = UserAzureServer::processNetworkData($network_out_total, true);

                $statistic = new Traffic();
                $statistic->u = $in_total;
                $statistic->d = $out_total;
                $statistic->date = date('Y-m-d', strtotime(Carbon::parse('+1 days ago')->toDateTimeString()));
                $statistic->uuid = $server->vm_id;
                $statistic->created_at = time();
                $statistic->save();
            } catch (\Exception $e) {
                $text = 'The virtual machine ' . $server->vm_id . ' or its resource group does not exist.';
                Log::write($text, 'error');
                $server->skip = 1;
                $server->save();
            }
        }
    }

    protected function configure()
    {
        $this->setName('tools')
            ->addOption('action', null, Option::VALUE_REQUIRED, 'action')
            ->addOption('newVersion', null, Argument::OPTIONAL, 'new version')
            ->setDescription('Website Toolbox');
    }

    protected function execute(Input $input, Output $output)
    {
        if ($input->hasOption('action')) {
            if ($input->getOption('action') === 'statisticsTraffic') {
                self::statisticsTraffic();
                $output->writeln("<info>All azure virtual machine traffic statistics are completed.</info>");
            } elseif ($input->getOption('action') === 'setVersion') {
                $version = trim($input->getOption('newVersion'));
                // 更新版本号
                $current = Config::where('item', 'version')->find();
                $current->value = $version;
                $current->save();
                // 输出提示语
                $output->writeln("<info>The version number has been updated to {$version}.</info>");
            } else {
                $output->writeln("<error>Unsupported command.</error>");
            }
        } else {
            $output->writeln('php think tools --action <action>');
            $output->writeln('statisticsTraffic - 统计流量');
            $output->writeln('setVersion <version> - 设置版本号');
        }
    }
}
