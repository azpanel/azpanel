<?php
namespace app\controller;

use think\helper\Str;
use think\facade\Env;
use think\facade\Log;
use think\facade\View;
use Carbon\Carbon;
use GuzzleHttp\Client;
use app\model\Azure;
use app\model\User;
use app\model\Config;
use app\model\Traffic;
use app\model\AzureServer;
use app\model\AzureServerResize;
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

        $designated_id = (int) input('id');
        if ($designated_id != '') {
            $designated_account = Azure::where('user_id', session('user_id'))->where('id', $designated_id)->find();
            if ($designated_account == null) {
                return View::fetch('../app/view/user/reject.html');
            }
            View::assign('designated_account', $designated_account);
        }

        $user         = User::find(session('user_id'));
        $personalise  = json_decode($user->personalise, true);

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
        View::assign('disk_sizes',  AzureList::diskSizes());
        View::assign('images',      AzureList::images());
        View::assign('sizes',       AzureList::sizes());
        View::assign('personalise', $personalise);
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
            return json(Tools::msg('0', '创建失败', '密码不符合要求，请阅读使用说明'));
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
                return json(Tools::msg('0', '创建失败', '此账户下存在同名虚拟机，请修改虚拟机名称 ' . $name));
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
        $vm_script = ($vm_script == '') ? null : base64_encode($vm_script);

        // 硬盘大小检查
        $images = AzureList::images();
        if (Str::contains($vm_image, 'Win') && !Str::contains($images[$vm_image]['sku'], 'smalldisk') && $vm_disk_size < '127') {
            return json(Tools::msg('0', '创建失败', '此 Windows 系统镜像要求硬盘大小不低于 127 GB'));
        }

        // 记录创建参数
        $params = [
            'account' => [
                'id'     => $account->id,
                'status' => $account->az_sub_status,
                'type'   => $account->az_sub_type,
                'email'  => $account->az_email,
            ],
            'server' => [
                'name'        => $vm_name,
                'mark'        => $vm_remark,
                'count'       => $vm_number,
                'disk_size'   => $vm_disk_size,
                'user'        => $vm_user,
                'image'       => $vm_image,
                'location'    => $vm_location,
                'size'        => $vm_size,
                'script'      => $vm_script,
            ]
        ];

        // 初始化创建任务
        $progress = 0;
        $client   = new Client();
        $steps    = ($vm_number * 6) + 3;
        $task_id  = UserTask::create(session('user_id'), '创建虚拟机', json_encode($params));

        // 限额检查
        UserTask::update($task_id, (++$progress / $steps), '正在检查创建任务可行性');
        $limits = AzureApi::getResourceSkusList($client, $account, $vm_location);
        Log::write(json_encode($limits), 'limits');
        foreach ($limits['value'] as $limit)
        {
            if ($limit['name'] == $vm_size) {
                if (!empty($limit['restrictions']['0']['reasonCode'])) {
                    if ($limit['restrictions']['0']['reasonCode'] == 'NotAvailableForSubscription') {
                        UserTask::end($task_id, true, json_encode(
                            ['msg' => 'This subscription cannot create VMs of this size in this region']
                        ));
                        return json(Tools::msg('0', '创建失败', '此订阅不能在此区域创建此规格虚拟机'));
                    }
                }
            }
        }

        // 资源组检查
        $resource_groups = AzureApi::getAzureResourceGroupsList($account->id, $account->az_sub_id);
        foreach ($resource_groups['value'] as $resource_group)
        {
            foreach ($names as $name) {
                $resource_group_name = $name . '_group';
                if (Str::lower($resource_group['name']) == Str::lower($resource_group_name)) {
                    UserTask::end($task_id, true, json_encode(['msg' => 'A resource group with the same name exists: ' . $name]));
                    return json(Tools::msg('0', '创建失败', '存在同名资源组，请修改虚拟机名称 ' . $name));
                }
            }
        }

        // 核心数检查
        $sizes = AzureList::sizes();
        $quotas = AzureApi::getQuota($account, $vm_location);
        $cores_total = $sizes[$vm_size]['cpu'] * $vm_number;

        foreach ($quotas['value'] as $quota)
        {
            if ($quota['properties']['name']['value'] == 'cores') {
                $quota_limit = $quota['properties']['limit'];
                $quota_usage = $quota['properties']['currentValue'];
            }
        }

        if ($cores_total + $quota_usage > $quota_limit) {
            $available = $quota_limit - $quota_usage;
            UserTask::end($task_id, true, json_encode(
                ['msg' => "The total number of virtual machine CPU cores created is $cores_total, which exceeds the available limit of $available for this subscription in this region."]
            ));
            return json(Tools::msg('0', '创建失败', "创建的虚拟机 CPU 核心数总计为 $cores_total 个，这超过了此订阅在此区域的可用限额 $available 个"));
        }

        // return json(Tools::msg('0', '检查结果', '检查完成'));

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
                    UserTask::update($task_id, (++$progress / $steps), '向资源提供程序注册订阅');
                    AzureApi::registerMainAzureProviders($client, 'Microsoft.Compute', $account->id);
                    AzureApi::registerMainAzureProviders($client, 'Microsoft.Network', $account->id);

                    $account->providers_register = 1;
                    $account->save();
                    $steps += 1;
                }

                // 创建资源组
                sleep(1);
                UserTask::update($task_id, (++$progress / $steps), '创建资源组 ' . $vm_resource_group_name);
                AzureApi::createAzureResourceGroup(
                    $client, $account, $vm_resource_group_name, $vm_location
                );

                // 创建公网地址
                sleep(2);
                UserTask::update($task_id, (++$progress / $steps), '在资源组 ' . $vm_resource_group_name . ' 中创建公网地址');
                $ip = AzureApi::createAzurePublicNetworkIpv4(
                    $client, $account, $vm_ip_name, $vm_resource_group_name, $vm_location
                );

                // 创建虚拟网络
                UserTask::update($task_id, (++$progress / $steps), '在资源组 ' . $vm_resource_group_name . ' 中创建虚拟网络');
                AzureApi::createAzureVirtualNetwork(
                    $client, $account, $vm_virtual_network_name, $vm_resource_group_name, $vm_location
                );

                // 创建子网
                UserTask::update($task_id, (++$progress / $steps), '在虚拟网络 ' . $vm_virtual_network_name . ' 中创建子网');
                $subnets = AzureApi::createAzureVirtualNetworkSubnets(
                    $client, $account, $vm_virtual_network_name, $vm_resource_group_name, $vm_location
                );

                // 创建网络接口
                sleep(2);
                UserTask::update($task_id, (++$progress / $steps), '在资源组 ' . $vm_resource_group_name . ' 中创建网络接口');
                $interfaces = AzureApi::createAzureVirtualNetworkInterfaces(
                    $client, $account, $vm_name, $ip, $subnets, $vm_location, $vm_size
                );

                // 创建虚拟机
                sleep(3);
                UserTask::update($task_id, (++$progress / $steps), '在资源组 ' . $vm_resource_group_name . ' 中创建虚拟机');
                $vm_url = AzureApi::createAzureVm(
                    $client, $account, $vm_name, $vm_config, $vm_image, $interfaces, $vm_location
                );
            } catch (\Exception $e) {
                $error = $e->getResponse()->getBody()->getContents();
                UserTask::end($task_id, true, $error);
                return json(Tools::msg('0', '创建失败', $error));
            }
        }

        UserTask::update($task_id, (++$progress / $steps), '等待创建完成');

        // 直到最后一个创建的虚拟机运行状态变为 running 再将所创建的虚拟机加入到列表中
        $count = 0;
        do {
            sleep(1);
            ++$count;
            $vm_status = AzureApi::getAzureVirtualMachineStatus($account->id, $vm_url);
            $status = $vm_status['statuses']['1']['code'] ?? 'null';
        } while ($status != 'PowerState/running' && $count < 120);

        if ($count >= 120) {
            UserTask::end($task_id, true);
            return json(Tools::msg('0', '创建失败', '原因未知。建议前往账户列表，进入创建账户的资源组列表中，将创建失败的资源组删除，在删除完成后重试。重新创建时，建议设定与之前不同的虚拟机名称。如若仍然失败，可记录创建参数，在 github issue 区寻求帮助'));
        }

        // 加载到虚拟机列表
        try {
            AzureApi::getAzureVirtualMachines($account->id);
        } catch (\Exception $e) {
            $error = $e->getMessage();
            UserTask::end($task_id, true, $error);
            return json(Tools::msg('0', '创建失败', $error));
        }

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

        $vm_sizes = AzureList::sizes();
        $disk_sizes = AzureList::diskSizes();
        $disk_tiers = AzureList::diskTiers();
        unset($vm_sizes[$server->vm_size]);

        foreach ($disk_sizes as $key => $value)
        {
            if ($server->disk_size >= $value) {
                unset($disk_sizes[$key]);
            }
        }

        if ($server->disk_details == null) {
            $disk_details = json_encode(AzureApi::getDisks($server));
            $server->disk_details = $disk_details;
            $server->save();
        }

        $vm_details       = json_decode($server->vm_details, true);
        $disk_details     = ($server->disk_details == null) ? $disk_details : json_decode($server->disk_details, true);
        $network_details  = json_decode($server->network_details, true);
        $instance_details = json_decode($server->instance_details, true);
        $vm_disk_created  = strtotime($instance_details['disks']['0']['statuses']['0']['time']);
        $vm_disk_tier     = $disk_details['properties']['tier'] ?? 'P4';

        $vm_dialog       = json_encode($vm_details, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        $disk_dialog     = json_encode($disk_details, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        $network_dialog  = json_encode($network_details, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        $instance_dialog = json_encode($instance_details, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

        View::assign('server', $server);
        View::assign('vm_sizes', $vm_sizes);
        View::assign('disk_sizes', $disk_sizes);
        View::assign('disk_tiers', $disk_tiers);
        View::assign('vm_dialog', $vm_dialog);
        View::assign('vm_details', $vm_details);
        View::assign('disk_dialog', $disk_dialog);
        View::assign('vm_disk_tier', $vm_disk_tier);
        View::assign('disk_details', $disk_details);
        View::assign('network_dialog', $network_dialog);
        View::assign('vm_disk_created', $vm_disk_created);
        View::assign('network_details', $network_details);
        View::assign('instance_dialog', $instance_dialog);
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

    public function resize($uuid)
    {
        $new_size = input('new_size/s');
        $server = AzureServer::where('vm_id', $uuid)->find();

        try {
            AzureApi::virtualMachinesResize($new_size, $server->location, $server->account_id, $server->request_url);
        } catch (\Exception $e) {
            return json(Tools::msg('0', '变配失败', $e->getMessage()));
        }

        $log = new AzureServerResize;
        $log->user_id     = session('user_id');
        $log->vm_id       = $server->vm_id;
        $log->before_size = $server->vm_size;
        $log->after_size  = $new_size;
        $log->created_at  = time();
        $log->save();

        $server->vm_size = $new_size;
        $server->save();

        return json(Tools::msg('1', '变配结果', '变配成功'));
    }

    public function redisk($uuid)
    {
        $count    = 0;
        $new_disk = (int) input('new_disk/s');
        $new_tier = input('new_tier/s');
        $server   = AzureServer::where('vm_id', $uuid)->find();
        $task_id  = UserTask::create(session('user_id'), '更换硬盘大小');

        UserTask::update($task_id, (++$count / 4), '正在等待计算资源释放完成');
        AzureApi::virtualMachinesDeallocate($server->account_id, $server->request_url);

        do {
            sleep(1);
            $vm_status = AzureApi::getAzureVirtualMachineStatus($server->account_id, $server->request_url);
            $status = $vm_status['statuses']['1']['code'] ?? 'null';
        } while ($status != 'PowerState/deallocated');

        try {
            AzureApi::virtualMachinesRedisk($new_disk, $new_tier, $server);
        } catch (\Exception $e) {
            $error = $e->getResponse()->getBody()->getContents();
            UserTask::end($task_id, true, $error);
            return json(Tools::msg('0', '变配失败', $error));
        }

        UserTask::update($task_id, (++$count / 4), '正在启动虚拟机');
        AzureApi::manageVirtualMachine('start', $server->account_id, $server->request_url);

        do {
            sleep(1);
            $vm_status = AzureApi::getAzureVirtualMachineStatus($server->account_id, $server->request_url);
            $status = $vm_status['statuses']['1']['code'] ?? 'null';
        } while ($status != 'PowerState/running');

        UserTask::update($task_id, (++$count / 4), '正在获取新的公网地址');

        $log = new AzureServerResize;
        $log->user_id     = session('user_id');
        $log->vm_id       = $server->vm_id;
        $log->before_size = $server->disk_size;
        $log->after_size  = $new_disk;
        $log->created_at  = time();
        $log->save();

        $server->disk_size = $new_disk;
        $server->disk_details = AzureApi::getDisks($server);
        $server->ip_address = AzureApi::getAzureVirtualMachinePublicIpv4($server);
        $server->save();

        UserTask::end($task_id, false);
        return json(Tools::msg('1', '变配结果', '变配成功'));
    }

    public function status($action, $uuid)
    {
        $server = AzureServer::where('vm_id', $uuid)->find();

        try {
            AzureApi::manageVirtualMachine($action, $server->account_id, $server->request_url);
        } catch (\Exception $e) {
            return json(Tools::msg('0', '操作失败', $e->getMessage()));
        }

        sleep(1);
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
            $error = $e->getResponse()->getBody()->getContents();
            UserTask::end($task_id, true, $error);
            return json(Tools::msg('0', '操作失败', $error));
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
