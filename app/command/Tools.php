<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\console\input\Argument;
use think\console\input\Option;

use Carbon\Carbon;
use think\facade\Log;
use app\model\Traffic;
use app\model\AzureServer;
use app\controller\AzureApi;
use app\controller\UserAzureServer;

class Tools extends Command
{
    protected function configure()
    {
        $this->setName('tools')
            ->addOption('action', null, Option::VALUE_REQUIRED, 'action')
            ->setDescription('Website Toolbox');
    }

    public static function statisticsTraffic()
    {
        $servers = AzureServer::where('status', '<>', 'PowerState/deallocated')
        ->where('skip', '0')
        ->select();

        $start_time = date('Y-m-d\T 16:00:00\Z', strtotime(Carbon::parse('+2 days ago')->toDateTimeString()));
        $stop_time = date('Y-m-d\T 16:00:00\Z', strtotime(Carbon::parse('+1 days ago')->toDateTimeString()));

        // dump($start_time);
        // dump($stop_time);

        foreach ($servers as $server)
        {
            try {
                $statistics = AzureApi::getVirtualMachineStatistics($server, $start_time, $stop_time);
                $network_in_total  = $statistics['value']['3']['timeseries']['0']['data'];
                $network_out_total = $statistics['value']['4']['timeseries']['0']['data'];

                $in_total = UserAzureServer::processNetworkData($network_in_total, true);
                $out_total = UserAzureServer::processNetworkData($network_out_total, true);

                $statistic = new Traffic;
                $statistic->u = $in_total;
                $statistic->d = $out_total;
                $statistic->date = date('Y-m-d', strtotime(Carbon::parse('+1 days ago')->toDateTimeString()));
                $statistic->uuid = $server->vm_id;
                $statistic->created_at = time();
                $statistic->save();
            } catch (\Exception $e) {
                $text = 'The virtual machine '. $server->vm_id .' or its resource group does not exist.';
                Log::write($text, 'error');
                $server->skip = 1;
                $server->save();
            }
        }
    }

    protected function execute(Input $input, Output $output)
    {
        if ($input->hasOption('action')) {
            if ($input->getOption('action') == 'statisticsTraffic') {
                self::statisticsTraffic();
                $output->writeln("<info>All azure virtual machine traffic statistics are completed.</info>");
            } else {
                $output->writeln("<error>Unsupported command.</error>");
            }
        } else {
            $output->writeln('php think Tools --action <action>');
            $output->writeln('statisticsTraffic - 统计流量');
        }
    }
}
