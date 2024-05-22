<?php

namespace app\controller;

use app\controller\AwsApi;
use app\controller\AwsList;
use app\controller\Tools;
use app\controller\UserTask;
use app\model\Aws;
use app\model\User;
use Aws\Ec2\Ec2Client;
use think\facade\View;
use think\helper\Str;

class UserAwsServer extends UserBase
{
    public function index()
    {
        $accounts = Aws::where('user_id', session('user_id'))
            ->where('disable', 0) // 只要没有被禁用的
            ->order('id', 'desc')
            ->select();

        View::assign([
            'accounts' => $accounts,
            'locations' => AwsList::instanceRegion(),
        ]);
        return View::fetch('../app/view/user/aws/server/index.html');
    }

    public function create()
    {
        $accounts = Aws::where('user_id', session('user_id'))
            ->where('disable', 0) // 只要没有被禁用的
            ->order('id', 'desc')
            ->select();

        $designated_id = (int) input('id');
        if ($designated_id !== 0) {
            $designated_account = Aws::where('user_id', session('user_id'))->find($designated_id);
            if ($designated_account === null) {
                return View::fetch('../app/view/user/reject.html');
            }
            View::assign('designated_account', $designated_account);
        }

        $user = User::find(session('user_id'));
        $personalise = json_decode($user->personalise, true);

        View::assign([
            'accounts' => $accounts,
            'personalise' => $personalise,
            'disk_sizes' => [16, 32, 64, 128],
            'sizes' => AwsList::instanceSizes(),
            'images' => AwsList::instanceImage(),
            'locations' => AwsList::instanceRegion(),
        ]);
        return View::fetch('../app/view/user/aws/server/create.html');
    }

    private static function getIpPermissions(): array
    {
        return [
            [
                'IpProtocol' => -1,
                'IpRanges' => [
                    [
                        'CidrIp' => '0.0.0.0/0',
                    ],
                ],
                'Ipv6Ranges' => [
                    [
                        'CidrIpv6' => '::/0',
                    ],
                ],
            ],
        ];
    }

    private static function getAWSClient(string $region, string $access_key, string $secret_key): object
    {
        return new Ec2Client([
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $access_key,
                'secret' => $secret_key,
            ],
        ]);
    }

    private static function generateScriptContent(string $name, string $passwd, string $custom_script): string
    {
        $text = "#!/bin/bash\necho root:" . $passwd . "|sudo chpasswd root\nsudo rm -rf /etc/ssh/sshd_config\nsudo tee /etc/ssh/sshd_config <<EOF\nClientAliveInterval 120\nSubsystem       sftp    /usr/lib/openssh/sftp-server\nX11Forwarding yes\nPrintMotd no\nChallengeResponseAuthentication no\nPasswordAuthentication yes\nPermitRootLogin yes\nUsePAM yes\nAcceptEnv LANG LC_*\nEOF\nsudo systemctl restart sshd\n";
        $text .= "sudo hostnamectl set-hostname " . $name . "\n";
        if ($custom_script !== '') {
            $text .= $custom_script;
        }
        return $text;
    }

    public function save()
    {
        $vm_name = input('vm_name/s');
        $vm_remark = input('vm_remark/s');
        $vm_passwd = input('vm_passwd/s');
        $specified_size = input('specified_size/s');
        $vm_script = input('vm_script/s');
        $vm_location = input('vm_location/s');
        $vm_size = input('vm_size/s');
        $vm_account = input('vm_account/d');
        $vm_disk_size = input('vm_disk_size/d');
        $vm_image = input('vm_image/s');
        $task_uuid = input('task_uuid/s');
        $ipv6_network = input('ipv6_network/s');

        // 空账户检查
        if ($vm_account === '') {
            return json(Tools::msg('0', '创建失败', '你还没有添加账户'));
        }
        // 所有权检查
        $account = Aws::find($vm_account);
        if ($account->user_id !== (int) session('user_id')) {
            return json(Tools::msg('0', '创建失败', '你不是此账户的持有者'));
        }
        // 虚拟机名称分隔
        if ($vm_remark === '') {
            $vm_remark = $vm_name;
        }
        $names = explode(',', $vm_name);
        $remarks = explode(',', $vm_remark);
        $vm_number = count($names);
        if (count($names) !== $vm_number || count($remarks) !== $vm_number || count($names) !== count($remarks)) {
            return json(Tools::msg('0', '创建失败', '请检查创建数量、备注和虚拟机名称是否正确分隔'));
        }
        // 虚拟机名称检查
        foreach ($names as $name) {
            if ($name === '') {
                return json(Tools::msg('0', '创建失败', '虚拟机名称不能为空'));
            }
        }
        foreach ($remarks as $remark) {
            if ($remark === '') {
                return json(Tools::msg('0', '创建失败', '虚拟机备注不能为空'));
            }
        }
        // 检查自定义脚本
        if (Str::contains($vm_script, '#!/bin/bash') || Str::contains($vm_script, '#!/bin/sh')) {
            return json(Tools::msg('0', '创建失败', '自定义脚本不需要以 #!/bin/bash 或 #!/bin/sh 开头，因为已经包含。可直接输入需要执行的代码。注意：部分命令可能需要 sudo'));
        }
        // 检查区域权限
        $quota = AwsApi::getQuota($vm_location, $account->ak, $account->sk);
        if ($quota === 'null') {
            return json(Tools::msg('0', '创建失败', '此账户在此区域可能未开通'));
        }
        // 记录创建参数
        $params = [
            'account' => [
                'id' => $account->id,
                'email' => $account->email,
                'quota' => $quota,
            ],
            'server' => [
                'name' => $vm_name,
                'mark' => $vm_remark,
                'size' => $vm_size,
                'count' => $vm_number,
                'image' => $vm_image,
                'location' => $vm_location,
                'disk_size' => $vm_disk_size,
                'script' => base64_encode($vm_script),
            ],
        ];
        // 初始化创建任务
        $progress = 0;
        $steps = $vm_number * 3;
        $task_id = UserTask::create(session('user_id'), '创建AWS虚拟机', $params, $task_uuid);
        // 开始创建
        foreach ($names as $vm_name) {
            $name = $vm_name . date('YmdHis', time());
            try {
                $controller_params = [
                    'name' => $name,
                    'disk_size' => $vm_disk_size,
                    'size' => $specified_size === '' ? $vm_size : $specified_size,
                    'userDataRaw' => $this->generateScriptContent($vm_name, $vm_passwd, $vm_script),
                    'imageName' => AwsList::instanceImage()[$vm_image]['imageName'],
                    'imageOwner' => AwsList::instanceImage()[$vm_image]['imageOwner'],
                    'IpPermissions' => $this->getIpPermissions(),
                ];
                UserTask::update($task_id, (++$progress / $steps), '正在创建会话');
                $client = AwsApi::createAWSClient($vm_location, $account->ak, $account->sk, false, 'ec2');
                if ($ipv6_network === 'true' && AwsApi::countRegionVpc($client, $vm_location) <= 4) {
                    UserTask::update($task_id, (++$progress / $steps), '正在创建具有 IPv6 的 EC2');
                    AwsApi::createIpv6EC2($client, $controller_params);
                } else {
                    UserTask::update($task_id, (++$progress / $steps), '正在创建 EC2');
                    AwsApi::createOnlyIpv4EC2($client, $controller_params);
                }
                //AwsApi::createOnlyIpv4EC2($client, $controller_params);
            } catch (\Exception $e) {
                $error = $e->getLine() . ':' . $e->getMessage();
                UserTask::end($task_id, true, ['msg' => $error]);
                return json(Tools::msg('0', '创建失败', $error));
            }
        }

        UserTask::end($task_id, false);
        sleep(1);
        return json(Tools::msg('1', '创建结果', '创建成功'));
    }

    public function read($id)
    {
        $account = Aws::where('user_id', session('user_id'))->find($id);
        try {
            $client = $this->getAWSClient(input('location/s'), $account->ak, $account->sk);
            return json($client->describeInstances()->toArray());
        } catch (\Exception $e) {
            return json([
                'ret' => 0,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function edit()
    {
        return false;
    }

    public function update($id)
    {
        $account = Aws::where('user_id', session('user_id'))->find($id);
        try {
            $action = input('action/s');
            $location = input('location/s');
            $instances = input('instances/a');

            $client = $this->getAWSClient($location, $account->ak, $account->sk);
            return json($client->$action(['InstanceIds' => $instances])->toArray());
        } catch (\Exception $e) {
            return json([
                'ret' => 0,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function delete()
    {
        return false;
    }
}
