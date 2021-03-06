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

        $condition[] = ['user_id', '=', $user_id];
        ($s_name != '')      && $condition[] = ['az_email',      'like', '%'.$s_name.'%'];
        ($s_mark != '')      && $condition[] = ['user_mark',     'like', '%'.$s_mark.'%'];
        ($s_type != 'all')   && $condition[] = ['az_sub_type',   '=', $s_type];
        ($s_status != 'all') && $condition[] = ['az_sub_status', '=', $s_status];

        $data = Azure::where($condition)
        ->field('id')
        ->select();

        // $sql = Db::getLastSql();

        return json(['result' => $data]);
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
        $user_mark    = input('user_mark/s');
        $az_email     = input('az_email/s');
        $az_passwd    = input('az_passwd/s');
        $az_app_id    = input('az_app_id/s');
        $az_secret    = input('az_secret/s');
        $az_tenant_id = input('az_tenant_id/s');
        $az_configs   = input('az_configs/s');

        // ???????????? api ??????
        if ($az_app_id == '' && $az_secret == '' && $az_tenant_id == '' && $az_configs == '') {
            return json(Tools::msg('0', '????????????', '???????????????????????????????????????'));
        }

        // ?????? json ???????????????
        if ($az_configs != '') {
            $configs = json_decode($az_configs, true);
            $decode_error = json_last_error();
            if ($decode_error != 'JSON_ERROR_NONE') {
                return json(Tools::msg('0', '????????????', '??? json ?????????????????????'));
            }
        }

        $az_api_app_id    = $configs['appId']    ?? $az_app_id    ?? null;
        $az_api_secret    = $configs['password'] ?? $az_secret    ?? null;
        $az_api_tenant_id = $configs['tenant']   ?? $az_tenant_id ?? null;

        if (!empty($configs['login_user'])) {
            $az_email = $configs['login_user'];
        }
        if (!empty($configs['login_passwd'])) {
            $az_passwd = $configs['login_passwd'];
        }

        // ?????????????????????
        if (!filter_var($az_email, FILTER_VALIDATE_EMAIL)) {
            return json(Tools::msg('0', '????????????', '????????????????????????'));
        }

        // ????????????????????????
        $exist = Azure::where('az_email', $az_email)->find();
        if ($exist != null) {
            return json(Tools::msg('0', '????????????', '??????????????????'));
        }

        // ??????????????????
        if (strlen($az_api_app_id) != 36) {
            return json(Tools::msg('0', '????????????', 'app_id ????????????36???'));
        }
        if (strlen($az_api_tenant_id) != 36) {
            return json(Tools::msg('0', '????????????', 'tenant_id ????????????36???'));
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
                throw new \Exception('?????????????????????????????????????????????????????????????????? Api ?????? <div class="mdui-typo"><code>az ad sp create-for-rbac --role contributor --scopes /subscriptions/$(az account list --query [].id -o tsv)</code></div>');
            }
            if ($sub_info['value']['0']['state'] != 'Enabled') {
                throw new \Exception('??????????????????????????????????????? Enabled');
            }
        } catch (\Exception $e) {
            Azure::destroy($account->id);
            return json(Tools::msg('0', '????????????', $e->getMessage()));
        }

        $account->az_sub            = json_encode($sub_info);
        $account->az_sub_id         = $sub_info['value']['0']['subscriptionId'];
        $account->az_sub_status     = $sub_info['value']['0']['state'];
        $account->az_sub_type       = self::discern($sub_info['value']['0']['subscriptionPolicies']['quotaId']);
        $account->az_sub_updated_at = time();
        $account->save();

        $count = AzureApi::getAzureVirtualMachines($account->id);
        $content = ($count != 0) ? '????????? ' . $count . ' ?????????' : '????????????';

        if ($count != 0) {
            $account->providers_register = '1';
            $account->save();
        }

        $client = new Client();
        AzureApi::registerMainAzureProviders($client, $account, 'Microsoft.Capacity');

        return json(Tools::msg('1', '????????????', $content));
    }

    public function update($id)
    {
        $user_mark = input('user_mark/s');
        $az_email  = input('az_email/s');
        $az_passwd = input('az_passwd/s');

        // ?????????????????????
        if (!filter_var($az_email, FILTER_VALIDATE_EMAIL)) {
            return json(Tools::msg('0', '????????????', '????????????????????????'));
        }

        $account = Azure::where('user_id', session('user_id'))->find($id);
        $account->az_email   = $az_email;
        $account->az_passwd  = $az_passwd;
        $account->user_mark  = $user_mark;
        $account->save();

        return json(Tools::msg('1', '????????????', '?????????????????????'));
    }

    public function delete($id)
    {
        Azure::where('user_id', session('user_id'))
        ->where('id', $id)
        ->delete();

        AzureServer::where('user_id', session('user_id'))
        ->where('account_id', $id)
        ->delete();

        return json(Tools::msg('1', '????????????', '?????????????????????'));
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
            return json(Tools::msg('0', '????????????', $e->getMessage()));
        }

        $text = '??????????????????<span style="float: right">' . $servers->count() . '</span>' . '<br/>'
        . '?????????????????????<span style="float: right">' . round($cumulative_traffic_usage, 2) . ' GB</span>' . '<br/>'
        . '?????????????????????<span style="float: right">' . round($cumulative_startup_time * 30) . ' Day</span>' . '<br/>'
        . '?????????????????????<span style="float: right">' . round($cumulative_startup_time * 30 / $servers->count()) . ' Day</span>' . '<br/>'
        . '<div class="mdui-typo"><hr/></div>' . '<br/>'
        . '????????????????????????<span style="float: right">' . round($vm_charges, 2) . ' USD</span>' . '<br/>'
        . '?????????????????????<span style="float: right">' . round($traffic_charges, 2) . ' USD</span>' . '<br/>'
        . '???????????????<span style="float: right">' . round($vm_charges + $traffic_charges, 2) . ' USD</span>';

        return json(Tools::msg('0', '????????????', $text));
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
            return json(Tools::msg('0', '????????????', $e->getMessage()));
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

        return json(Tools::msg('1', '????????????', '????????????'));
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

        $accounts = Azure::where('user_id', $user_id)
        ->where('az_sub_status', '<>', 'Disabled')
        ->whereIn('az_sub_type', $query_set)
        ->select();

        $params = [
            'refresh_action' => $refresh_action,
            'refresh_account_type' => $refresh_account_type,
        ];

        $task_id = UserTask::create(session('user_id'), '????????????????????????', $params, $task_uuid);
        $steps = $accounts->count() + 1;

        foreach ($accounts as $account)
        {
            $count += 1;

            try {
                UserTask::update($task_id, ($count / $steps), '???????????? ' . $account->az_email);
                $sub_info = AzureApi::getAzureSubscription($account->id); // array
                if (in_array('resources', $refresh_action)) {
                    AzureApi::getAzureVirtualMachines($account->id);
                }
            } catch (\Exception $e) {
                UserTask::end($task_id, true);
                return json(Tools::msg('0', '????????????', $e->getMessage()));
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
        return json(Tools::msg('1', '????????????', '????????????'));
    }

    public function updateAzureSubscriptionResources($id)
    {
        $account = Azure::where('user_id', session('user_id'))->find($id);

        try {
            $count = AzureApi::getAzureVirtualMachines($account->id);
        } catch (\Exception $e) {
            return json(Tools::msg('0', '????????????', $e->getMessage()));
        }

        $content = ($count != 0) ? '????????? ' . $count . ' ????????????' : '??????????????????????????????';

        return json(Tools::msg('1', '????????????', $content));
    }

    public function deleteAzureDisabledSubscription()
    {
        $accounts = Azure::where('user_id', session('user_id'))
        ->where('az_sub_status', '<>', 'Enabled')
        ->select();

        $count = $accounts->count();

        $content = ($count != 0) ? '????????? ' . $count . ' ?????????' : '???????????????????????????';

        if ($count == '0') {
            return json(Tools::msg('0', '????????????', $content));
        }

        foreach ($accounts as $account) {
            AzureServer::where('account_id', $account->id)->delete();
            Azure::destroy($account->id);
        }

        return json(Tools::msg('1', '????????????', $content));
    }

    public function deleteResourceGroup()
    {
        $url = input('url/s');

        try {
            AzureApi::deleteAzureResourcesGroupByUrl($url);
        } catch (\Exception $e) {
            return json(Tools::msg('0', '????????????', $e->getMessage()));
        }

        $resource_group = explode('/', $url);
        $subscriptions = $resource_group['2'];
        $resource_group = end($resource_group);

        AzureServer::where('at_subscription_id', $subscriptions)
        ->where('resource_group', $resource_group)
        ->delete();

        return json(Tools::msg('1', '????????????', '???????????????????????? 3~5 ????????????'));
    }

    public function readResourceGroupsList($id)
    {
        $account = Azure::find($id);
        if ($account == null || $account->user_id != session('user_id')) {
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
            if ($server == null) {
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
        if ($account == null || $account->user_id != session('user_id')) {
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
