<?php
namespace app\controller;

use think\helper\Str;
use think\facade\Env;
use think\facade\View;
use Carbon\Carbon;
use app\model\Azure;
use app\model\User;
use app\model\Config;
use app\model\Traffic;
use app\model\AzureServer;
use app\controller\Tools;
use app\controller\AzureApi;
use app\controller\AzureList;

class UserAzureServer extends UserBase
{
    public function index()
    {
        $limit = Env::get('APP.paginate') ?? '15';
        $pages_num = (input('page') == '') ? '1' : input('page');
        $servers_num = AzureServer::where('user_id', session('user_id'))->count();
        $servers = AzureServer::where('user_id', session('user_id'))
        ->order('id', 'desc')
        ->paginate($limit);

        foreach($servers as $server)
        {
            // 刷新服务器状态
            if ($server->status == 'PowerState/starting' || $server->status == 'PowerState/stopping') {
                $vm_status = AzureApi::getAzureVirtualMachineStatus($server->account_id, $server->request_url);
                $server->status = $vm_status['statuses']['1']['code'] ?? 'null';
                $server->save();
            }
        }

        $page = $servers->render();
        $count = $servers_num - (($pages_num - 1) * $limit);

        View::assign('page', $page);
        View::assign('servers', $servers);
        View::assign('count', $count);
        return View::fetch('../app/view/user/azure/server/index.html');
    }

    public function create()
    {
        $accounts = Azure::where('user_id', session('user_id'))
        ->where('az_sub_status', 'Enabled')
        ->order('id', 'desc')
        ->select();

        $user         = User::find(session('user_id'));
        $personalise  = json_decode($user->personalise, true);
        $disk_sizes   = ['32', '64', '128', '256', '512', '1024'];

        if (!$accounts->isEmpty()) {
            foreach ($accounts as $account)
            {
                $count = AzureServer::where('account_id', $account->id)
                ->where('vm_size', '<>', 'Standard_B1s')
                ->count();

                $has_vm_num[$account->id] = $count;
            }

            View::assign('has_vm_num', $has_vm_num);
        }

        View::assign('locations',   AzureList::locations());
        View::assign('images',      AzureList::images());
        View::assign('sizes',       AzureList::sizes());
        View::assign('personalise', $personalise);
        View::assign('disk_sizes',  $disk_sizes);
        View::assign('accounts',    $accounts);
        return View::fetch('../app/view/user/azure/server/create.html');
    }

    public function save()
    {
        $vm_remark    = input('vm_remark/s');
        $vm_name      = input('vm_name/s');
        $vm_number    = (int) input('vm_number/s');
        $vm_user      = input('vm_user/s');
        $vm_passwd    = input('vm_passwd/s');
        $vm_script    = input('vm_script/s');
        $vm_location  = input('vm_location/s');
        $vm_size      = input('vm_size/s');
        $vm_account   = (int) input('vm_account/s');
        $vm_disk_size = (int) input('vm_disk_size/s');
        $vm_image     = input('vm_image/s');

        // 空账户检查
        if ($vm_account == '') {
            return json(Tools::msg('0', '创建失败', '你还没有添加账户'));
        }

        // 账户所属关系检查
        $account = Azure::find($vm_account);
        if ($account->user_id != session('user_id')) {
            return json(Tools::msg('0', '创建失败', '你不是此账户的持有者'));
        }

        // 用户名检查
        $prohibit_user = ['root', 'admin', 'centos', 'debian', 'ubuntu'];
        if (in_array($vm_user, $prohibit_user)) {
            return json(Tools::msg('0', '创建失败', '不能使用常见用户名'));
        }

        if (!preg_match('/^[a-zA-Z0-9]+$/', $vm_user)) {
            return json(Tools::msg('0', '创建失败', '用户名只允许使用大小写字母与数字的组合'));
        }

        // 密码检查
        $uppercase = preg_match('@[A-Z]@', $vm_passwd);
        $lowercase = preg_match('@[a-z]@', $vm_passwd);
        $number    = preg_match('@[0-9]@', $vm_passwd);
        // $symbol    = preg_match('@[^\w]@', $vm_passwd);

        if (!$uppercase || !$lowercase || !$number || strlen($vm_passwd) < 12 || strlen($vm_passwd) > 72) {
            return json(Tools::msg('0', '创建失败', '密码不符合规范，请阅读使用说明'));
        }

        // 虚拟机名称设置检查
        $names   = explode(',', $vm_name);
        $remarks = explode(',', $vm_remark);

        if (count($names) != $vm_number || count($remarks) != $vm_number || count($names) != count($remarks)) {
            return json(Tools::msg('0', '创建失败', '请检查创建数量、备注和虚拟机名称是否正确分隔'));
        }

        // 虚拟机名称检查
        foreach ($names as $name)
        {
            if ($name == '') {
                return json(Tools::msg('0', '创建失败', '虚拟机名称不能为空'));
            }

            if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
                return json(Tools::msg('0', '创建失败', '虚拟机名称只允许使用大小写字母与数字的组合'));
            }

            if (strlen($name) > 64) {
                return json(Tools::msg('0', '创建失败', '虚拟机名称长度不能超过 64 个字符串'));
            }

            $name_exist = AzureServer::where('account_id', $account->id)
            ->where('name', $name)
            ->find();

            if ($name_exist != null) {
                return json(Tools::msg('0', '创建失败', '此账户下存在同名虚拟机'));
            }
        }

        // 虚拟机备注检查
        foreach ($remarks as $remark)
        {
            if ($remark == '') {
                return json(Tools::msg('0', '创建失败', '虚拟机备注不能为空'));
            }
        }

        // 脚本检查
        $vm_script = ($vm_script == '') ? 'null' : base64_encode($vm_script);

        // 硬盘大小检查
        $images = AzureList::images();
        if (Str::contains($vm_image, 'Win') && !Str::contains($images[$vm_image]['sku'], 'smalldisk') && $vm_disk_size < '127') {
            return json(Tools::msg('0', '创建失败', '此 Windows 系统镜像要求硬盘大小不低于 127 GB'));
        }

        // return json(Tools::msg('0', '创建检查完成', '没有发现问题'));

        $create_step_count = 0;
        // 步骤数 = (创建数量 * 创建一台的流程数) + 将虚拟机加入列表这一步骤
        $number_of_steps = (count($names) * 6) + 2;
        // 创建任务
        $task_id = UserTask::create(session('user_id'), '创建虚拟机');

        foreach ($names as $vm_name)
        {
            $vm_ip_name              = $vm_name . '_ipv4';
            $vm_resource_group_name  = $vm_name . '_group';
            $vm_virtual_network_name = $vm_name . '_vnet';

            $vm_config = [
                'vm_size'      => $vm_size,
                'vm_disk_size' => $vm_disk_size,
                'vm_user'      => $vm_user,
                'vm_passwd'    => $vm_passwd,
                'vm_script'    => $vm_script
            ];

            try {
                // 向资源提供程序注册订阅
                if ($account->providers_register == '0') {
                    $number_of_steps += 1;
                    UserTask::update($task_id, (++$create_step_count / $number_of_steps), '向资源提供程序注册订阅');
                    AzureApi::registerMainAzureProviders($account->id);
                    // 保存
                    $account->providers_register = 1;
                    $account->save();
                }

                // (1/6) 创建资源组
                sleep(1);
                UserTask::update($task_id, (++$create_step_count / $number_of_steps), '创建资源组 ' . $vm_resource_group_name);

                AzureApi::createAzureResourceGroup(
                    $account, $vm_resource_group_name, $vm_location
                );

                sleep(2);

                // (2/6) 创建公网地址
                $text = '在资源组 ' . $vm_resource_group_name . ' 中创建公网地址';
                UserTask::update($task_id, (++$create_step_count / $number_of_steps), $text);

                $ip = AzureApi::createAzurePublicNetworkIpv4(
                    $account, $vm_ip_name, $vm_resource_group_name, $vm_location
                );

                // (3/6) 创建虚拟网络
                $text = '在资源组 ' . $vm_resource_group_name . ' 中创建虚拟网络';
                UserTask::update($task_id, (++$create_step_count / $number_of_steps), $text);

                AzureApi::createAzureVirtualNetwork(
                    $account, $vm_virtual_network_name, $vm_resource_group_name, $vm_location
                );

                // (4/6) 创建子网
                $text = '在虚拟网络 ' . $vm_virtual_network_name . ' 中创建子网';
                UserTask::update($task_id, (++$create_step_count / $number_of_steps), $text);

                $subnets = AzureApi::createAzureVirtualNetworkSubnets(
                    $account, $vm_virtual_network_name, $vm_resource_group_name, $vm_location
                );

                // 避免 http 429 error
                sleep(1);

                // (5/6) 创建网络接口
                $text = '在资源组 ' . $vm_resource_group_name . ' 中创建网络接口';
                UserTask::update($task_id, (++$create_step_count / $number_of_steps), $text);

                $interfaces = AzureApi::createAzureVirtualNetworkInterfaces(
                    $account, $vm_name, $ip, $subnets, $vm_location
                );

                sleep(2);

                // (6/6) 创建虚拟机
                $text = '在资源组 ' . $vm_resource_group_name . ' 中创建虚拟机 ' . $vm_name;
                UserTask::update($task_id, (++$create_step_count / $number_of_steps), $text);

                $vm_url = AzureApi::createAzureVm(
                    $account, $vm_name, $vm_config, $vm_image, $interfaces, $vm_location
                );
            } catch (\Exception $e) {
                UserTask::end($task_id, true);
                return json(Tools::msg('0', '创建失败', $e->getResponse()->getBody()->getContents()));
            }
        }

        UserTask::update($task_id, (++$create_step_count / $number_of_steps), '等待创建完成');

        // 直到最后创建的虚拟机运行状态变为 running 再将所创建的虚拟机加入到列表中
        do {
            sleep(1);
            $vm_status = AzureApi::getAzureVirtualMachineStatus($account->id, $vm_url);
            $status = $vm_status['statuses']['1']['code'] ?? 'null';
        } while ($status != 'PowerState/running');

        AzureApi::getAzureVirtualMachines($account->id);

        // 将设置的备注应用
        $pointer = 0;
        foreach($names as $name) {
            $server = AzureServer::where('name', $name)->order('id', 'desc')->limit(1)->find();
            $server->user_remark = $remarks[$pointer];
            $server->save();
            $pointer += 1;
        }

        UserTask::end($task_id, false);
        return json(Tools::msg('1', '创建结果', '创建成功'));
    }

    public function read($id)
    {
        $server = AzureServer::where('user_id', session('user_id'))->find($id);
        if ($server == null) {
            return View::fetch('../app/view/user/reject.html');
        }

        $vm_details = json_decode($server->vm_details, true);
        $network_details = json_decode($server->network_details, true);
        $instance_details = json_decode($server->instance_details, true);

        View::assign('vm_details', $vm_details);
        View::assign('network_details', $network_details);
        View::assign('instance_details', $instance_details);
        return View::fetch('../app/view/user/azure/server/read.html');
    }

    public function delete($uuid)
    {
        $server = AzureServer::where('vm_id', $uuid)->delete();

        return json(Tools::msg('1', '移出结果', '移出成功'));
    }

    public function destroy($uuid)
    {
        $server = AzureServer::where('vm_id', $uuid)->find();

        try {
            AzureApi::deleteAzureResourcesGroup($server->account_id, $server->at_subscription_id, $server->resource_group);
        } catch (\Exception $e) {
            return json(Tools::msg('0', '销毁失败', $e->getMessage()));
        }

        $server->delete();

        return json(Tools::msg('1', '销毁结果', '已销毁此虚拟机'));
    }

    public function remark($uuid)
    {
        $remark = input('remark/s');
        if ($remark == '') {
            return json(Tools::msg('0', '修改结果', '备注不能为空'));
        }

        $server = AzureServer::where('vm_id', $uuid)->find();
        $server->user_remark = $remark;
        $server->save();

        return json(Tools::msg('1', '修改结果', '修改成功'));
    }

    public function status($action, $uuid)
    {
        $server = AzureServer::where('vm_id', $uuid)->find();

        try {
            AzureApi::manageVirtualMachine($action, $server->account_id, $server->request_url);
        } catch (\Exception $e) {
            return json(Tools::msg('0', '操作失败', $e->getMessage()));
        }

        self::refresh($server->vm_id);

        return json(Tools::msg('1', '执行结果', '成功'));
    }

    public static function refresh($uuid)
    {
        $server = AzureServer::where('vm_id', $uuid)->find();

        try {
            $vm_status = AzureApi::getAzureVirtualMachineStatus($server->account_id, $server->request_url);
        } catch (\Exception $e) {
            return json(Tools::msg('0', '操作失败', $e->getMessage()));
        }

        $server->status = $vm_status['statuses']['1']['code'];
        $server->save();

        return json(Tools::msg('1', '执行结果', '成功'));
    }

    public function change($uuid)
    {
        $count = 0;
        $server = AzureServer::where('vm_id', $uuid)->find();
        $task_id = UserTask::create(session('user_id'), '更换公网地址');

        UserTask::update($task_id, (++$count / 5), '正在关闭虚拟机并释放计算资源');
        sleep(1);

        try {
            $vm_status = AzureApi::virtualMachinesDeallocate($server->account_id, $server->request_url);
        } catch (\Exception $e) {
            UserTask::end($task_id, true);
            return json(Tools::msg('0', '操作失败', $e->getMessage()));
        }

        UserTask::update($task_id, (++$count / 5), '正在等待计算资源释放完成');

        do {
            sleep(1);
            $vm_status = AzureApi::getAzureVirtualMachineStatus($server->account_id, $server->request_url);
            $status = $vm_status['statuses']['1']['code'] ?? 'null';
        } while ($status != 'PowerState/deallocated');

        UserTask::update($task_id, (++$count / 5), '正在启动虚拟机');

        AzureApi::manageVirtualMachine('start', $server->account_id, $server->request_url);

        do {
            sleep(1);
            $vm_status = AzureApi::getAzureVirtualMachineStatus($server->account_id, $server->request_url);
            $status = $vm_status['statuses']['1']['code'] ?? 'null';
        } while ($status != 'PowerState/running');

        UserTask::update($task_id, (++$count / 5), '正在获取新的公网地址');

        sleep(1);
        $server->ip_address = AzureApi::getAzureVirtualMachinePublicIpv4($server);
        $server->save();

        UserTask::end($task_id, false);
        return json(Tools::msg('1', '执行结果', '成功'));
    }

    public function check($ipv4)
    {
        // http://4563.org/?p=368746
        
        try {
            $result = file_get_contents('https://api-v2.50network.com/modules/ipcheck/icmp?ipv4=' . $ipv4);
            $result = json_decode($result, true);
            $cn_net = ($result['firewall-enable'] == true) ? '<p>中国节点 -> <span style="color: green">正常</span>' : '中国节点 -> <span style="color: red">异常</span></p>';
            $intl_net = ($result['firewall-disable'] == true) ? '<p>外国节点 -> <span style="color: green">正常</span>' : '外国节点 -> <span style="color: red">异常</span></p>';

            return json(Tools::msg('1', '检查成功', $cn_net . $intl_net));
        } catch (\Exception $e) {
            return json(Tools::msg('0', '检查失败', $e->getMessage()));
        }
    }

    public static function processGeneralData($array, $convert = false)
    {
        $text = '';

        if ($convert) {
            foreach ($array as $data)
            {
                $date = date('d日H时', strtotime($data['timeStamp']));
                $text .= '["' . $date . '", ' . round(round($data['average'] ?? '0', 2)  / 1048576) . '],';
            }
        } else {
            foreach ($array as $data)
            {
                $date = date('d日H时', strtotime($data['timeStamp']));
                $text .= '["' . $date . '", ' . round($data['average'] ?? '0', 2) . '],';
            }
        }

        return $text;
    }

    public static function processNetworkData($array, $total = false)
    {
        $text = '';
        $usage = 0;

        foreach ($array as $data)
        {
            $date = date('d日H时', strtotime($data['timeStamp']));
            $bytes = round(($data['total'] ?? '0') / 1000000000, 2);
            $text .= '["' . $date . '", ' . $bytes . '],';
            $usage += $bytes;
        }

        return ($total == false) ? $text : $usage;
    }

    public function chart($id)
    {
        $gap = (int) input('gap');
        $server = AzureServer::find($id);
        if ($server == null || $server->user_id != session('user_id')) {
            return View::fetch('../app/view/user/reject.html');
        }

        if ($gap == '') {
            $statistics = AzureApi::getVirtualMachineStatistics($server);
        } else {
            $timestamp = strtotime(Carbon::parse("+$gap days ago")->toDateTimeString());
            $start_time = date('Y-m-d\T 16:00:00\Z', $timestamp);
            $stop_time = date('Y-m-d\T 16:00:00\Z', $timestamp + 86400);
            $chart_day = date('Y-m-d', $timestamp + 86400);

            $statistics = AzureApi::getVirtualMachineStatistics($server, $start_time, $stop_time);
        }

        $cpu_credits       = $statistics['value']['1']['timeseries']['0']['data'];
        $percentage_cpu    = $statistics['value']['0']['timeseries']['0']['data'];
        $available_memory  = $statistics['value']['2']['timeseries']['0']['data'];
        $network_in_total  = $statistics['value']['3']['timeseries']['0']['data'];
        $network_out_total = $statistics['value']['4']['timeseries']['0']['data'];

        $traffic_usage = Traffic::where('uuid', $server->vm_id)->order('id', 'desc')->select();
        $chart_day = (empty($chart_day)) ? null : $chart_day;

        View::assign('server', $server);
        View::assign('chart_day', $chart_day);
        View::assign('count', $traffic_usage->count());
        View::assign('traffic_usage', $traffic_usage);
        View::assign('cpu_credits_text', self::processGeneralData($cpu_credits));
        View::assign('percentage_cpu_text', self::processGeneralData($percentage_cpu));
        View::assign('network_in_total_text', self::processNetworkData($network_in_total));
        View::assign('network_out_total_text', self::processNetworkData($network_out_total));
        View::assign('network_in_traffic', self::processNetworkData($network_in_total, true));
        View::assign('network_out_traffic', self::processNetworkData($network_out_total, true));
        View::assign('available_memory_text', self::processGeneralData($available_memory, true));
        return View::fetch('../app/view/user/azure/server/chart.html');
    }
}
