<?php
namespace app\controller;

use think\facade\Env;
use think\facade\View;
use GuzzleHttp\Client;
use app\controller\Tools;
use app\controller\AzureApi;
use app\controller\UserTask;
use app\controller\UserAzureServer;
use app\model\Azure;
use app\model\AzureServer;

class UserAzure extends UserBase
{
    public function index()
    {
        $limit = Env::get('APP.paginate') ?? '15';
        $pages_num = (input('page') == '') ? '1' : input('page');
        $accounts_num = Azure::where('user_id', session('user_id'))->count();
        $accounts = Azure::where('user_id', session('user_id'))
        ->order('id', 'desc')
        ->paginate($limit);

        $page = $accounts->render();
        $count = $accounts_num - (($pages_num - 1) * $limit);

        View::assign('page', $page);
        View::assign('count', $count);
        View::assign('accounts', $accounts);
        return View::fetch('../app/view/user/azure/index.html');
    }

    public function create()
    {
        return View::fetch('../app/view/user/azure/create.html');
    }

    public function read($id)
    {
        $account = Azure::where('user_id', session('user_id'))->find($id);
        $az_sub = json_decode($account->az_sub, true);

        if ($account == null) {
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

        if ($account == null) {
            return View::fetch('../app/view/user/reject.html');
        }

        $share = [
            'login_user' => $account->az_email,
            'login_passwd' => $account->az_passwd,
            'subscription_id' => $account->az_sub_id,
            'appId' => $az_api['appId'],
            'password' => $az_api['password'],
            'tenant' => $az_api['tenant']
        ];

        View::assign('az_api', $az_api);
        View::assign('account', $account);
        View::assign('share', json_encode($share));
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
            return 'VS Enterprise：BizSpark';
        }

        if (strpos($quotaId, 'MSDN') !== false) {
            return 'MSDN Platforms Subscription';
        }

        return 'Unknown';
    }

    public function save()
    {
        $user_mark    = input('user_mark/s');
        $az_email     = input('az_email/s');
        $az_passwd    = input('az_passwd/s');
        $az_app_id    = input('az_app_id/s');
        $az_secret    = input('az_secret/s');
        $az_tenant_id = input('az_tenant_id/s');
        $az_configs   = input('az_configs/s');

        // 如果账户已经添加
        $exist = Azure::where('az_email', $az_email)->find();
        if ($exist != null) {
            return json(Tools::msg('0', '添加失败', '此账户已添加'));
        }

        // 如果邮箱不规范
        if (!filter_var($az_email, FILTER_VALIDATE_EMAIL)) {
            return json(Tools::msg('0', '添加失败', '此邮箱格式不规范'));
        }

        // 如果没填 api 信息
        if ($az_app_id == '' && $az_secret == '' && $az_tenant_id == '' && $az_configs == '') {
            return json(Tools::msg('0', '添加失败', '请根据页面提示填写所需参数'));
        }

        // 如果 json 信息不规范
        if ($az_configs != '') {
            $configs = json_decode($az_configs, true);
            $decode_error = json_last_error();
            if ($decode_error != 'JSON_ERROR_NONE') {
                return json(Tools::msg('0', '添加失败', '此 json 内容格式不规范'));
            }
        }

        $az_api_app_id    = $configs['appId']    ?? $az_app_id    ?? null;
        $az_api_secret    = $configs['password'] ?? $az_secret    ?? null;
        $az_api_tenant_id = $configs['tenant']   ?? $az_tenant_id ?? null;

        // 如果长度不符
        if (strlen($az_api_app_id) != 36) {
            return json(Tools::msg('0', '添加失败', 'app_id 长度应为36位'));
        }
        if (strlen($az_api_tenant_id) != 36) {
            return json(Tools::msg('0', '添加失败', 'tenant_id 长度应为36位'));
        }

        $az_api = [
            'appId'     => $az_api_app_id,
            'password'  => $az_api_secret,
            'tenant'    => $az_api_tenant_id
        ];

        $account = new Azure;
        $account->user_id    = session('user_id');
        $account->az_email   = $az_email;
        $account->az_passwd  = $az_passwd;
        $account->user_mark  = $user_mark;
        $account->az_api     = json_encode($az_api);
        $account->created_at = time();
        $account->updated_at = time();
        $account->save();

        try {
            $sub_info = AzureApi::getAzureSubscription($account->id); // array
            if ($sub_info['count']['value'] == '0') {
                throw new \Exception('此账户无有效订阅。若有，建议使用以下命令获取 Api 参数 <div class="mdui-typo"><code>az ad sp create-for-rbac --role contributor --scopes /subscriptions/$(az account list --query [].id -o tsv)</code></div> ');
            }
        } catch (\Exception $e) {
            Azure::destroy($account->id);
            return json(Tools::msg('0', '添加失败', $e->getMessage()));
        }

        $account->az_sub            = json_encode($sub_info);
        $account->az_sub_id         = $sub_info['value']['0']['subscriptionId'];
        $account->az_sub_status     = $sub_info['value']['0']['state'];
        $account->az_sub_type       = self::discern($sub_info['value']['0']['subscriptionPolicies']['quotaId']);
        $account->az_sub_updated_at = time();
        $account->save();

        $count = AzureApi::getAzureVirtualMachines($account->id);
        $content = ($count != 0) ? '加载了 ' . $count . ' 个资源' : '添加成功';

        if ($count != 0) {
            $account->providers_register = '1';
            $account->save();
        }

        $client = new Client();
        AzureApi::registerMainAzureProviders($client, $account, 'Microsoft.Capacity');

        return json(Tools::msg('1', '添加结果', $content));
    }

    public function update($id)
    {
        $user_mark = input('user_mark/s');
        $az_email  = input('az_email/s');
        $az_passwd = input('az_passwd/s');

        // 如果邮箱不规范
        if (!filter_var($az_email, FILTER_VALIDATE_EMAIL)) {
            return json(Tools::msg('0', '添加失败', '此邮箱格式不规范'));
        }

        $account = Azure::where('user_id', session('user_id'))->find($id);
        $account->az_email   = $az_email;
        $account->az_passwd  = $az_passwd;
        $account->user_mark  = $user_mark;
        $account->save();

        return json(Tools::msg('1', '修改成功', '将返回账户列表'));
    }

    public function delete($id)
    {
        Azure::where('user_id', session('user_id'))
        ->where('id', $id)
        ->delete();

        AzureServer::where('user_id', session('user_id'))
        ->where('account_id', $id)
        ->delete();

        return json(Tools::msg('1', '删除成功', '将返回账户列表'));
    }

    public function estimatedCost($id)
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
            foreach ($servers as $server)
            {
                $instance_details = json_decode($server->instance_details, true);
                $vm_disk_created = strtotime($instance_details['disks']['0']['statuses']['0']['time']);
                $start_time = date('Y-m-d\T H:i:00\Z', $vm_disk_created - 28800);
                $stop_time = date('Y-m-d\T H:i:00\Z', time() - 28800);
                $cumulative_running_time = (time() - $vm_disk_created) / 2592000;
                $statistics = AzureApi::getVirtualMachineStatistics($server, $start_time, $stop_time);
                $network_in_total = $statistics['value']['3']['timeseries']['0']['data'];
                $network_in_traffic = UserAzureServer::processNetworkData($network_in_total, true);
                $traffic_charges += 0.08 * $network_in_traffic;
                $cumulative_traffic_usage += $network_in_traffic;
                $cumulative_startup_time += $cumulative_running_time;
                if (!empty($sizes[$server->vm_size])) {
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

        return json(Tools::msg('0', '统计结果', $text));
    }

    public static function refreshTheResourceStatusUnderTheAccount($account)
    {
        $servers = AzureServer::where('account_id', $account->id)->select();
        foreach ($servers as $server)
        {
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
            return json(Tools::msg('0', '刷新失败', $e->getMessage()));
        }

        $account->az_sub            = json_encode($sub_info);
        $account->az_sub_status     = $sub_info['value']['0']['state'];
        $account->az_sub_type       = self::discern($sub_info['value']['0']['subscriptionPolicies']['quotaId']);
        $account->az_sub_updated_at = time();
        $account->updated_at        = time();
        $account->save();

        if ($sub_info['value']['0']['state'] != 'Enabled') {
            self::refreshTheResourceStatusUnderTheAccount($account);
        }

        return json(Tools::msg('1', '刷新结果', '刷新成功'));
    }

    public function refreshAllAzureSubscriptionStatus()
    {
        $count = 0;
        $accounts = Azure::where('user_id', session('user_id'))
        ->where('az_sub_status', '<>', 'Disabled')
        ->select();

        $task_id = UserTask::create(session('user_id'), '刷新账户订阅状态');
        $accounts_count = $accounts->count() + 1;

        foreach ($accounts as $account)
        {
            $count += 1;

            try {
                UserTask::update($task_id, ($count / $accounts_count), '正在刷新 ' . $account->az_email);
                $sub_info = AzureApi::getAzureSubscription($account->id); // array
            } catch (\Exception $e) {
                UserTask::end($task_id, true);
                return json(Tools::msg('0', '刷新失败', $e->getMessage()));
            }

            $account->az_sub            = json_encode($sub_info);
            $account->az_sub_status     = $sub_info['value']['0']['state'];
            $account->az_sub_type       = self::discern($sub_info['value']['0']['subscriptionPolicies']['quotaId']);
            $account->az_sub_updated_at = time();
            $account->updated_at        = time();
            $account->save();

            if ($sub_info['value']['0']['state'] != 'Enabled') {
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

        $content = ($count != 0) ? '加载了 ' . $count . ' 个新资源' : '没有新的资源需要加载';

        return json(Tools::msg('1', '更新结果', $content));
    }

    public function deleteAzureDisabledSubscription()
    {
        $accounts = Azure::where('user_id', session('user_id'))
        ->where('az_sub_status', '<>', 'Enabled')
        ->select();

        $count = $accounts->count();

        $content = ($count != 0) ? '删除了 ' . $count . ' 个账户' : '没有需要删除的账户';

        if ($count == '0') {
            return json(Tools::msg('0', '删除结果', $content));
        }

        foreach ($accounts as $account) {
            AzureServer::where('account_id', $account->id)->delete();
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
        if ($account == null || $account->user_id != session('user_id')) {
            return View::fetch('../app/view/user/reject.html');
        }

        $count = 0;
        $resources = AzureApi::getAzureResourceGroupsList($id, $account->az_sub_id);
        $virtual_machines = AzureApi::readAzureVirtualMachinesList($id, $account->az_sub_id);

        View::assign('count', $count);
        View::assign('resources', $resources);
        View::assign('virtual_machines', $virtual_machines);
        return View::fetch('../app/view/user/azure/resources.html');
    }

    public function readResourceGroup($id, $name)
    {
        $account = Azure::find($id);
        if ($account == null || $account->user_id != session('user_id')) {
            return View::fetch('../app/view/user/reject.html');
        }

        $groups = AzureApi::getAzureResourceGroup($account, $name);

        View::assign('groups', $groups);
        return View::fetch('../app/view/user/azure/groups.html');
    }

    public function QueryAccountQuota($id)
    {
        $account = Azure::find($id);
        $location = input('location');

        if ($account->reg_capacity == 0) {
            $client = new Client();
            AzureApi::registerMainAzureProviders($client, $account, 'Microsoft.Capacity');
            $account->reg_capacity = 1;
            $account->save();
        }

        $data = [];
        $result = AzureApi::getQuota($account, $location);
        foreach ($result['value'] as $item) {
            $array['note']  = $item['properties']['name']['localizedValue'];
            $array['name']  = $item['properties']['name']['value'];
            $array['usage'] = $item['properties']['currentValue'];
            $array['limit'] = $item['properties']['limit'];
            array_push($data, $array);
        }

        array_multisort(array_column($data, 'limit'), SORT_DESC, $data);
        return json(['result' => $data]);
    }
}
