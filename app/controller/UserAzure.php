<?php

namespace app\controller;

use app\controller\AzureApi;
use app\controller\Tools;
use app\controller\UserAzureServer;
use app\controller\UserTask;
use app\model\Azure;
use app\model\AzureRecycle;
use app\model\AzureServer;
use app\model\Share;
use GuzzleHttp\Client;
use think\facade\Db;
use think\facade\View;
use think\helper\Str;

class UserAzure extends UserBase
{
    public function index()
    {
        $accounts = Azure::where('user_id', session('user_id'))
            ->order('id', 'desc')
            ->select();

        View::assign('count', $accounts->count());
        View::assign('accounts', $accounts);
        return View::fetch('../app/view/user/azure/index.html');
    }

    public function searchAccount()
    {
        $user_id = session('user_id');
        $s_name = input('s_name/s');
        $s_mark = input('s_mark/s');
        $s_type = input('s_type/s');
        $s_status = input('s_status/s');

        $condition = [];
        $condition[] = ['user_id', '=', $user_id];
        ($s_name !== '') && $condition[] = ['az_email', 'like', '%' . $s_name . '%'];
        ($s_mark !== '') && $condition[] = ['user_mark', 'like', '%' . $s_mark . '%'];
        ($s_type !== 'all') && $condition[] = ['az_sub_type', '=', $s_type];
        ($s_status !== 'all') && $condition[] = ['az_sub_status', '=', $s_status];

        $data = Azure::where($condition)
            ->field('id')
            ->select();

        // $sql = Db::getLastSql();

        return json(['result' => $data]);
    }

    public function shareAccount()
    {
        try {
            $set = [];
            $accounts = input('account_set/a');
            foreach ($accounts as $account) {
                // query
                $details = Azure::where('user_id', session('user_id'))
                    ->where('id', $account)
                    ->find();
                // check
                if (!isset($details)) {
                    throw new \Exception('此账户不存在或不属于你');
                }
                // encode
                $az_api = json_decode($details->az_api, true);
                $set[] = [
                    'login_user' => $details->az_email,
                    'login_passwd' => $details->az_passwd,
                    'subscription_id' => $details->az_sub_id,
                    'appId' => $az_api['appId'],
                    'password' => $az_api['password'],
                    'tenant' => $az_api['tenant'],
                ];
                // delete
                AzureServer::where('account_id', $details->id)->delete();
                $details->delete();
            }

            $task = new Share();
            $token = substr(md5(Str::random($length = 32)), 8, 24);
            $task->user_id = session('user_id');
            $task->content = json_encode($set);
            $task->created_at = time();
            $task->count = count($set);
            $task->token = $token;
            $task->save();

            $share_link = 'https://' . $_SERVER['HTTP_HOST'] . '/share?token=' . $token;
            $share_text = '<div class="mdui-typo"><code>' . $share_link . '</code></div>';
            return json([
                'status' => 1,
                'title' => '分享成功',
                'content' => $share_text,
                'share_link' => $share_link,
            ]);
        } catch (\Exception $e) {
            return json(Tools::msg('0', '分享失败', $e->getMessage()));
        }
    }

    public function processShare()
    {
        try {
            $url = input('share_link/s');
            $user_mark = input('user_mark/s');
            $remark_filling = input('remark_filling/s');
            // https://www.jianshu.com/p/074f96f9d005
            // 忽略证书问题
            $stream_opts = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ];
            // get & decode
            $content = file_get_contents($url, false, stream_context_create($stream_opts));
            if (Str::contains($content, 'thinkphp_show_page_trace')) {
                throw new \Exception('共享方站点未关闭调试模式，因此不能正确解码数据');
            }
            $content = json_decode($content, true);
            if ($content['msg'] !== 'ok') {
                throw new \Exception('无效的分享链接');
            }
            // save
            foreach ($content['content'] as $api) {
                $az_api = [
                    'appId' => $api['appId'],
                    'password' => $api['password'],
                    'tenant' => $api['tenant'],
                ];

                $account = new Azure();
                $account->user_id = session('user_id');
                $account->az_email = $api['login_user'];
                $account->az_passwd = $api['login_passwd'];
                $account->user_mark = $remark_filling === 'input' ? $user_mark : $remark_filling;
                $account->az_api = json_encode($az_api);
                $account->created_at = time();
                $account->updated_at = time();
                $account->save();

                $sub_info = AzureApi::getAzureSubscription($account->id); // array
                if ($sub_info['count']['value'] !== '0') {
                    $account->az_sub = json_encode($sub_info);
                    $account->az_sub_id = $sub_info['value']['0']['subscriptionId'];
                    $account->az_sub_status = $sub_info['value']['0']['state'];
                    $account->az_sub_type = self::discern($sub_info['value']['0']['subscriptionPolicies']['quotaId']);
                    $account->az_sub_updated_at = time();
                    $account->save();
                }

                $client = new Client();
                $count = AzureApi::getAzureVirtualMachines($account->id);
                if ($count !== 0) {
                    $account->providers_register = 1;
                    $account->save();
                } else {
                    AzureApi::registerMainAzureProviders($client, $account, 'Microsoft.Compute');
                    AzureApi::registerMainAzureProviders($client, $account, 'Microsoft.Network');
                }

                if ($sub_info['value']['0']['state'] === 'Enabled') {
                    AzureApi::registerMainAzureProviders($client, $account, 'Microsoft.Capacity');
                }
            }
            $ajax_content = '通过此链接添加了 ' . $content['count'] . ' 个账户';
        } catch (\Exception $e) {
            return json(Tools::msg('0', '添加失败', $e->getMessage()));
        }

        return json(Tools::msg('1', '添加结果', $ajax_content));
    }

    public function create()
    {
        $notes = Db::table('azure')
            ->where('user_id', session('user_id'))
            ->where('user_mark', '<>', '')
            ->distinct(true)
            ->field('user_mark')
            ->select();

        View::assign('notes', $notes);
        return View::fetch('../app/view/user/azure/create.html');
    }

    public function read($id)
    {
        $account = Azure::where('user_id', session('user_id'))->find($id);
        $az_sub = json_decode($account->az_sub, true);

        if ($account === null) {
            return View::fetch('../app/view/user/reject.html');
        }

        View::assign('az_sub', $az_sub);
        View::assign('account', $account);
        View::assign('locations', AzureList::locations());
        return View::fetch('../app/view/user/azure/read.html');
    }

    public function edit($id)
    {
        $account = Azure::where('user_id', session('user_id'))->find($id);
        $az_api = json_decode($account->az_api, true);

        if ($account === null) {
            return View::fetch('../app/view/user/reject.html');
        }

        $share = [
            'login_user' => $account->az_email,
            'login_passwd' => $account->az_passwd,
            'subscription_id' => $account->az_sub_id,
            'appId' => $az_api['appId'],
            'password' => $az_api['password'],
            'tenant' => $az_api['tenant'],
        ];

        View::assign('az_api', $az_api);
        View::assign('account', $account);
        View::assign('share', json_encode($share, JSON_PRETTY_PRINT));
        return View::fetch('../app/view/user/azure/edit.html');
    }

    public static function discern($quotaId)
    {
        if (strpos($quotaId, 'Students') !== false) {
            return 'Students';
        }
        if (strpos($quotaId, 'PayAsYouGo') !== false) {
            return 'PayAsYouGo';
        }
        if (strpos($quotaId, 'FreeTrial') !== false) {
            return 'FreeTrial';
        }
        if (strpos($quotaId, 'Sponsorship') !== false) {
            return 'Azure 3500';
        }
        if (strpos($quotaId, 'BizSpark') !== false) {
            return 'VS Enterprise: BizSpark';
        }
        if (strpos($quotaId, 'MSDN') !== false) {
            return 'MSDN Platforms Subscription';
        }

        return 'Unknown';
    }

    public function save()
    {
        $user_mark = input('user_mark/s');
        $az_email = input('az_email/s');
        $az_passwd = input('az_passwd/s');
        $az_app_id = input('az_app_id/s');
        $az_secret = input('az_secret/s');
        $az_tenant_id = input('az_tenant_id/s');
        $az_configs = input('az_configs/s');
        $ignore_status = input('ignore_status/s');
        $remark_filling = input('remark_filling/s');

        // 如果没填 api 信息
        if ($az_app_id === '' && $az_secret === '' && $az_tenant_id === '' && $az_configs === '') {
            return json(Tools::msg('0', '添加失败', '请根据页面提示填写所需参数'));
        }

        // 如果 json 信息不规范
        if ($az_configs !== '') {
            $configs = json_decode($az_configs, true);
            $decode_error = json_last_error();
            if ($decode_error !== 0) {
                $az_email = Tools::getMailAddress($az_configs);
                $json_text = Tools::getJsonContent($az_configs);
                $configs = json_decode($json_text, true);
                $decode_error = json_last_error();
                if ($decode_error !== 0) {
                    return json(Tools::msg('0', '添加失败', '此 json 内容格式不规范'));
                }
            }
        }

        $az_api_app_id = $configs['appId'] ?? $az_app_id ?? null;
        $az_api_secret = $configs['password'] ?? $az_secret ?? null;
        $az_api_tenant_id = $configs['tenant'] ?? $az_tenant_id ?? null;

        if (isset($configs['login_user'])) {
            $az_email = $configs['login_user'];
        }
        if (isset($configs['login_passwd'])) {
            $az_passwd = $configs['login_passwd'];
        }

        // 如果邮箱不规范
        if (!filter_var($az_email, FILTER_VALIDATE_EMAIL)) {
            return json(Tools::msg('0', '添加失败', '此邮箱格式不规范'));
        }

        // 如果账户已经添加
        $exist = Azure::where('az_email', $az_email)->find();
        if ($exist !== null) {
            return json(Tools::msg('0', '添加失败', '此账户已添加'));
        }

        // 如果长度不符
        if (strlen($az_api_app_id) !== 36) {
            return json(Tools::msg('0', '添加失败', 'app_id 长度应为36位'));
        }
        if (strlen($az_api_tenant_id) !== 36) {
            return json(Tools::msg('0', '添加失败', 'tenant_id 长度应为36位'));
        }

        $az_api = [
            'appId' => $az_api_app_id,
            'password' => $az_api_secret,
            'tenant' => $az_api_tenant_id,
        ];

        $account = new Azure();
        $account->user_id = session('user_id');
        $account->az_email = $az_email;
        $account->az_passwd = $az_passwd;
        $account->user_mark = $remark_filling === 'input' ? $user_mark : $remark_filling;
        $account->az_api = json_encode($az_api);
        $account->created_at = time();
        $account->updated_at = time();
        $account->save();

        try {
            $sub_info = AzureApi::getAzureSubscription($account->id); // array
            if ((int) $sub_info['count']['value'] === 0) {
                throw new \Exception('此账户无有效订阅。若有，建议使用以下命令获取 Api 参数 <div class="mdui-typo"><code>az ad sp create-for-rbac --role contributor --scopes /subscriptions/$(az account list --query [].id -o tsv)</code></div>');
            }
            if ($sub_info['value']['0']['state'] !== 'Enabled') {
                if ($ignore_status === 'false') {
                    throw new \Exception('此账户订阅状态异常，若有需要，请勾选忽略订阅状态');
                }
            }
        } catch (\Exception $e) {
            Azure::destroy($account->id);
            return json(Tools::msg('0', '添加失败', $e->getMessage()));
        }

        $account->az_sub = json_encode($sub_info);
        $account->az_sub_id = $sub_info['value']['0']['subscriptionId'];
        $account->az_sub_status = $sub_info['value']['0']['state'];
        $account->az_sub_type = self::discern($sub_info['value']['0']['subscriptionPolicies']['quotaId']);
        $account->az_sub_updated_at = time();
        $account->save();

        $client = new Client();
        $count = AzureApi::getAzureVirtualMachines($account->id);
        $content = $count !== 0 ? '加载了 ' . $count . ' 个资源' : '添加成功';

        if ($count !== 0) {
            $account->providers_register = 1;
            $account->save();
        } else {
            AzureApi::registerMainAzureProviders($client, $account, 'Microsoft.Compute');
            AzureApi::registerMainAzureProviders($client, $account, 'Microsoft.Network');
        }

        if ($sub_info['value']['0']['state'] === 'Enabled') {
            AzureApi::registerMainAzureProviders($client, $account, 'Microsoft.Capacity');
        }

        return json(Tools::msg('1', '添加结果', $content));
    }

    public function update($id)
    {
        $user_mark = input('user_mark/s');
        $az_email = input('az_email/s');
        $az_passwd = input('az_passwd/s');

        // 如果邮箱不规范
        if (!filter_var($az_email, FILTER_VALIDATE_EMAIL)) {
            return json(Tools::msg('0', '添加失败', '此邮箱格式不规范'));
        }

        $account = Azure::where('user_id', session('user_id'))->find($id);
        $account->az_email = $az_email;
        $account->az_passwd = $az_passwd;
        $account->user_mark = $user_mark;
        $account->save();

        return json(Tools::msg('1', '修改成功', '将返回账户列表'));
    }

    public function delete($id)
    {
        $account = Azure::where('user_id', session('user_id'))->find($id);
        $servers = AzureServer::where('user_id', session('user_id'))
            ->where('account_id', $id)
            ->select();

        if ($servers->count() > 0) {
            try {
                $cycle = new AzureRecycle();
                $cycle->user_id = session('user_id');
                $cycle->az_email = $account->az_email;
                $cycle->az_sub_type = $account->az_sub_type;
                $cycle->user_mark = $account->user_mark;
                $cycle->bill_charges = self::estimatedCost($id, true);
                $cycle->life_cycle = self::getEarliestTime($id);
                $cycle->created_at = time();
                $cycle->save();
            } catch (\Exception $e) {
                // to do
            }
        }

        $account->delete();
        $servers->delete();
        return json(Tools::msg('1', '删除成功', '将返回账户列表'));
    }

    public static function getEarliestTime($id)
    {
        $time_set = [];
        $account = Azure::find($id);
        $servers = AzureServer::where('account_id', $id)->select();

        foreach ($servers as $server) {
            if (!isset($server->disk_details)) {
                $server->disk_details = json_encode(AzureApi::getDisks($server));
                $server->save();
            }
            $disk_details = json_decode($server->disk_details, true);
            $vm_disk_created = strtotime($disk_details['properties']['timeCreated']);
            $time_set[] = $vm_disk_created;
        }

        $min_value = $account->updated_at > min($time_set) ? min($time_set) : $account->updated_at;
        return round((time() - $min_value) / 86400, 2);
    }

    public static function estimatedCost($id, $api = false)
    {
        try {
            $vm_charges = 0;
            $traffic_charges = 0;
            $cumulative_traffic_usage = 0;
            $cumulative_startup_time = 0;
            $sizes = AzureList::sizes();
            $servers = AzureServer::where('user_id', session('user_id'))
                ->where('account_id', $id)
                ->select();
            foreach ($servers as $server) {
                if (!isset($server->disk_details)) {
                    $server->disk_details = json_encode(AzureApi::getDisks($server));
                    $server->save();
                }
                $disk_details = json_decode($server->disk_details, true);
                $vm_disk_created = strtotime($disk_details['properties']['timeCreated']);
                $start_time = date('Y-m-d\T H:i:00\Z', $vm_disk_created - 28800);
                $stop_time = date('Y-m-d\T H:i:00\Z', time() - 28800);
                $cumulative_running_time = (time() - $vm_disk_created) / 2592000;
                $statistics = AzureApi::getVirtualMachineStatistics($server, $start_time, $stop_time);
                foreach ($statistics['value'] as $key => $value) {
                    if ($value['name']['value'] === 'Network In Total') {
                        $network_in_total = $statistics['value'][$key]['timeseries']['0']['data'];
                        break;
                    }
                }
                $network_in_traffic = UserAzureServer::processNetworkData($network_in_total, true);
                $traffic_charges += 0.08 * $network_in_traffic;
                $cumulative_traffic_usage += $network_in_traffic;
                $cumulative_startup_time += $cumulative_running_time;
                if (isset($sizes[$server->vm_size])) {
                    $vm_charges += $sizes[$server->vm_size]['cost'] * $cumulative_running_time;
                }
            }
        } catch (\Exception $e) {
            return json(Tools::msg('0', '统计失败', $e->getMessage()));
        }

        $text = '总虚拟机数：<span style="float: right">' . $servers->count() . '</span>' . '<br/>'
        . '累计出向流量：<span style="float: right">' . round($cumulative_traffic_usage, 2) . ' GB</span>' . '<br/>'
        . '累计开机时长：<span style="float: right">' . round($cumulative_startup_time * 30) . ' Day</span>' . '<br/>'
        . '平均开机时长：<span style="float: right">' . round($cumulative_startup_time * 30 / $servers->count()) . ' Day</span>' . '<br/>'
        . '<div class="mdui-typo"><hr/></div>' . '<br/>'
        . '预估虚拟机费用：<span style="float: right">' . round($vm_charges, 2) . ' USD</span>' . '<br/>'
        . '预估流量费用：<span style="float: right">' . round($traffic_charges, 2) . ' USD</span>' . '<br/>'
        . '累计费用：<span style="float: right">' . round($vm_charges + $traffic_charges, 2) . ' USD</span>';

        if ($api) {
            return round($vm_charges + $traffic_charges, 2);
        }
        return json(Tools::msg('0', '统计结果', $text));
    }

    public static function refreshTheResourceStatusUnderTheAccount($account)
    {
        $servers = AzureServer::where('account_id', $account->id)->select();
        foreach ($servers as $server) {
            $vm_status = AzureApi::getAzureVirtualMachineStatus($server->account_id, $server->request_url);
            $server->status = $vm_status['statuses']['1']['code'];
            $server->save();
        }
    }

    public function refreshAzureSubscriptionStatus($id)
    {
        $account = Azure::where('user_id', session('user_id'))->find($id);

        try {
            $sub_info = AzureApi::getAzureSubscription($id); // array
        } catch (\Exception $e) {
            if (Str::contains($e->getMessage(), '401 Unauthorized')) {
                $account->az_sub_status = 'Invalid';
                $account->save();
                return json(Tools::msg('0', '刷新失败', $e->getMessage()));
            }
            return json(Tools::msg('0', '刷新失败', $e->getMessage()));
        }

        $account->az_sub = json_encode($sub_info);
        $account->az_sub_status = $sub_info['value']['0']['state'];
        $account->az_sub_type = self::discern($sub_info['value']['0']['subscriptionPolicies']['quotaId']);
        $account->az_sub_updated_at = time();
        $account->updated_at = time();
        $account->save();

        if ($sub_info['value']['0']['state'] !== 'Enabled') {
            self::refreshTheResourceStatusUnderTheAccount($account);
        }

        return json(Tools::msg('1', '刷新结果', '刷新成功'));
    }

    public function refreshAllAzureSubscriptionStatus()
    {
        $count = 0;
        $user_id = session('user_id');
        $task_uuid = input('task_uuid/s');
        $refresh_action = input('action/a');
        $refresh_account_type = input('type/a');

        $query_set = [];
        $type_set = ['PayAsYouGo', 'FreeTrial', 'Students', 'Unknown'];
        foreach ($type_set as $type_name) {
            if (in_array($type_name, $refresh_account_type)) {
                $query_set[] = $type_name;
            }
        }

        if (in_array('Unknown', $query_set)) {
            array_push($query_set, 'MSDN Platforms Subscription', 'VS Enterprise: BizSpark', 'Azure 3500', 'Unknown');
        }

        $accounts = Azure::where('user_id', $user_id)
            ->where('az_sub_status', '<>', 'Disabled')
            ->where('az_sub_status', '<>', 'Invalid')
            ->whereIn('az_sub_type', $query_set)
            ->select();

        $params = [
            'refresh_action' => $refresh_action,
            'refresh_account_type' => $refresh_account_type,
        ];

        $task_id = UserTask::create(session('user_id'), '刷新账户订阅状态', $params, $task_uuid);
        $steps = $accounts->count() + 1;

        foreach ($accounts as $account) {
            $count += 1;

            try {
                UserTask::update($task_id, $count / $steps, '正在刷新 ' . $account->az_email);
                $sub_info = AzureApi::getAzureSubscription($account->id); // array
                if (in_array('resources', $refresh_action)) {
                    AzureApi::getAzureVirtualMachines($account->id);
                }
            } catch (\Exception $e) {
                if (Str::contains($e->getMessage(), '401 Unauthorized')) {
                    $account->az_sub_status = 'Invalid';
                    $account->save();
                    continue;
                }
                UserTask::end($task_id, true);
                return json(Tools::msg('0', '刷新失败', $e->getMessage()));
            }

            $account->az_sub = json_encode($sub_info);
            $account->az_sub_status = $sub_info['value']['0']['state'];
            $account->az_sub_type = self::discern($sub_info['value']['0']['subscriptionPolicies']['quotaId']);
            $account->az_sub_updated_at = time();
            $account->updated_at = time();
            $account->save();

            if ($sub_info['value']['0']['state'] !== 'Enabled') {
                self::refreshTheResourceStatusUnderTheAccount($account);
            }
        }

        UserTask::end($task_id, false);
        return json(Tools::msg('1', '刷新结果', '刷新成功'));
    }

    public function updateAzureSubscriptionResources($id)
    {
        $account = Azure::where('user_id', session('user_id'))->find($id);

        try {
            $count = AzureApi::getAzureVirtualMachines($account->id);
        } catch (\Exception $e) {
            return json(Tools::msg('0', '更新失败', $e->getMessage()));
        }

        $content = $count !== 0 ? '加载了 ' . $count . ' 个新资源' : '没有新的资源需要加载';

        return json(Tools::msg('1', '更新结果', $content));
    }

    public function deleteAzureDisabledSubscription()
    {
        $accounts = Azure::where('user_id', session('user_id'))
            ->where('az_sub_status', '<>', 'Enabled')
            ->select();

        $count = $accounts->count();
        $content = $count !== 0 ? '删除了 ' . $count . ' 个账户' : '没有需要删除的账户';

        if ($count === 0) {
            return json(Tools::msg('0', '删除结果', $content));
        }

        foreach ($accounts as $account) {
            $servers = AzureServer::where('account_id', $account->id)->select();
            if ($servers->count() > 0) {
                try {
                    $cycle = new AzureRecycle();
                    $cycle->user_id = session('user_id');
                    $cycle->az_email = $account->az_email;
                    $cycle->az_sub_type = $account->az_sub_type;
                    $cycle->user_mark = $account->user_mark;
                    $cycle->bill_charges = self::estimatedCost($account->id, true);
                    $cycle->life_cycle = self::getEarliestTime($account->id);
                    $cycle->created_at = time();
                    $cycle->save();
                } catch (\Exception $e) {
                    // to do
                }
            }

            $servers->delete();
            Azure::destroy($account->id);
        }

        return json(Tools::msg('1', '删除结果', $content));
    }

    public function deleteResourceGroup()
    {
        $url = input('url/s');

        try {
            AzureApi::deleteAzureResourcesGroupByUrl($url);
        } catch (\Exception $e) {
            return json(Tools::msg('0', '删除失败', $e->getMessage()));
        }

        $resource_group = explode('/', $url);
        $subscriptions = $resource_group['2'];
        $resource_group = end($resource_group);

        AzureServer::where('at_subscription_id', $subscriptions)
            ->where('resource_group', $resource_group)
            ->delete();

        return json(Tools::msg('1', '删除结果', '删除所有资源需要 3~5 分钟完成'));
    }

    public function readResourceGroupsList($id)
    {
        $account = Azure::find($id);
        if ($account === null || $account->user_id !== (int) session('user_id')) {
            return View::fetch('../app/view/user/reject.html');
        }

        $count = 0;
        $ip_set = [];
        $resources = AzureApi::getAzureResourceGroupsList($id, $account->az_sub_id);
        $virtual_machines = AzureApi::readAzureVirtualMachinesList($id, $account->az_sub_id);

        View::assign('count', $count);
        View::assign('resources', $resources);
        View::assign('virtual_machines', $virtual_machines);

        foreach ($virtual_machines as $vm) {
            $vm_id = $vm['properties']['vmId'];
            $server = AzureServer::where('vm_id', $vm_id)->find();
            if ($server === null) {
                $details = explode('/', $vm['properties']['networkProfile']['networkInterfaces']['0']['id']);
                $network = AzureApi::getAzureNetworkInterfacesDetails($id, $details['8'], $details['4'], $details['2']);
                $ip_set[$vm_id] = $network['properties']['ipConfigurations']['0']['properties']['publicIPAddress']['properties']['ipAddress'] ?? 'null';
            } else {
                $ip_set[$vm_id] = $server->ip_address ?? 'null';
            }
        }

        View::assign('ip_set', $ip_set);
        return View::fetch('../app/view/user/azure/resources.html');
    }

    public function readResourceGroup($id, $name)
    {
        $account = Azure::find($id);
        if ($account === null || $account->user_id !== (int) session('user_id')) {
            return View::fetch('../app/view/user/reject.html');
        }

        $groups = AzureApi::getAzureResourceGroup($account, $name);

        View::assign('groups', $groups);
        return View::fetch('../app/view/user/azure/groups.html');
    }

    public function queryAccountQuota($id)
    {
        $account = Azure::find($id);
        $location = input('location');

        if ($account->reg_capacity === 0) {
            $client = new Client();
            AzureApi::registerMainAzureProviders($client, $account, 'Microsoft.Capacity');
            $account->reg_capacity = 1;
            $account->save();
        }

        $data = [];
        $array = [];
        $result = AzureApi::getQuota($account, $location);
        foreach ($result['value'] as $item) {
            $array['note'] = $item['properties']['name']['localizedValue'];
            $array['name'] = $item['properties']['name']['value'];
            $array['usage'] = $item['properties']['currentValue'];
            $array['limit'] = $item['properties']['limit'];
            array_push($data, $array);
        }

        array_multisort(array_column($data, 'limit'), SORT_DESC, $data);
        return json(['result' => $data]);
    }
}
